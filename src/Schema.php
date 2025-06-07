<?php

namespace DancasDev\DQB;

use DancasDev\DQB\Adapters\CacheAdapter;
use DancasDev\DQB\Exceptions\CacheAdapterException;
use DancasDev\DQB\Exceptions\SchemaException;
use DancasDev\DQB\Exceptions\SchemaTableConfigBuildException;
use DancasDev\DQB\Exceptions\SchemaFieldConfigBuildException;

class Schema {
    public $cacheAdapter;
    /**
     * Listado de tablas con la configuración sin procesar
     * 
     * @var array
     */
    protected array $tablesConfig = [];

    /**
     * Listado de campos con la configuración  sin procesar
     * 
     * @var array
     */
    protected array $fieldsConfig = [];

    /**
     * Listado de callback de solicitudes extra
     * 
     * @var array
     */
    protected array $extraCallbacks = [];

    /**
     * Nivel acceso con el que se estara descartando lops campos restringido
     * 
     * @var int
     */
    protected $accesslevel = 0;
    
    /**
     * Lista de tipos de joins permitidos
     * 
     * @var array
     */
    private array $typeJoinsAllowed = ['INNER','LEFT','RIGHT','OUTER','LEFT OUTER','RIGHT OUTER'];

    function __construct(array $tablesConfig = [], array $fieldsConfig = []) {
        if (!empty($tablesConfig)) {
            $this ->addTableConfig($tablesConfig);
        }
        if (!empty($fieldsConfig)) {
            $this ->addFieldConfig($fieldsConfig);
        }
    }

    public function setAccessLevel(int $value = 0) : Schema {
        $this ->accesslevel = $value;

        return $this;
    }

    public function getAccessLevel() : int {
        return $this ->accesslevel;
    }

    // --Methodos para manipular la configuracion--
    public function getConfig() : array {
        return [
            'tables' => $this ->tablesConfig,
            'fields' => $this ->fieldsConfig
        ];
    }

    public function getTableConfig(string $key) : array|null {
        if (!array_key_exists($key, $this ->tablesConfig)) {
            return null;
        }
        elseif (!($this ->tablesConfig[$key]['_built_'] ?? false)) {
            $this ->tablesConfig[$key] = $this ->buildTableConfig($key, $this ->tablesConfig[$key]);
        }
        
        return $this ->tablesConfig[$key];
    }

    public function getFieldConfig(string $key) : array|null {
        if (!array_key_exists($key, $this ->fieldsConfig)) {
            return null;
        }
        elseif (!($this ->fieldsConfig[$key]['_built_'] ?? false)) {
            $this ->fieldsConfig[$key] = $this ->buildFieldConfig($key, $this ->fieldsConfig[$key]);
        }
        
        return $this ->fieldsConfig[$key];
    }
    
    public function addTableConfig(array|string $table, null|array $value = null) : Schema {
        $this ->tablesConfig = $this ->addToConfigArray($this ->tablesConfig, $table, $value);
        return $this;
    }

    public function addFieldConfig(array|string $field, null|array $value = null) : Schema {
        $this ->fieldsConfig = $this ->addToConfigArray($this ->fieldsConfig, $field, $value);
        return $this;
    }

    public function removeTableConfig(string $table) : Schema {
        unset($this ->tablesConfig[$table]);
        return $this;
    }

    public function removeFieldConfig(string $field) : Schema {
        unset($this ->fieldsConfig[$field]);
        return $this;
    }
    
    public function getTablesList() : array {
        return array_keys($this ->tablesConfig);
    }

    public function getFieldsList() : array {
        return array_keys($this ->fieldsConfig);
    }
    
    // --Methodos para la construcion de la configuracion--
    /**
     * Validacion general de la configuracion
     * 
     * @throws SchemaTableConfigBuildException
     * @throws SchemaFieldConfigBuildException
     * 
     * @return bool
     */
    public function validConfig() : bool {
        // Tablas
        $this ->validTablesConfig();
        // Campos
        $this ->validFieldsConfig();

        return true;
    }

    /**
     * Validacion de la configuracion de la tablas
     * 
     * @throws SchemaTableConfigBuildException
     * 
     * @return bool
     */
    public function validTablesConfig() : bool {
        foreach ($this ->tablesConfig as $key => $configOriginal) {
            $configOriginal = (array) $configOriginal;
            $configBuilt = $this ->buildTableConfig($key, (array) $configOriginal);
            // Validaciones
            if (array_key_exists('name', $configOriginal) && !is_string($configOriginal['name'])) {
                throw new SchemaTableConfigBuildException("The 'name' configuration for table '{$key}' must be a string.");
            }
            
            if (!$configBuilt['is_primary']) {
                if (!$configBuilt['is_extra']) {
                    // join
                    if (!array_key_exists('join', $configOriginal)) {
                        throw new SchemaTableConfigBuildException("Missing 'join' configuration for table '{$key}'.");
                    }
                    if (!is_array($configOriginal['join'])) {
                        throw new SchemaTableConfigBuildException("The 'join' configuration for table '{$key}' must be an array.");
                    }
                    if (!array_key_exists('on', $configOriginal['join'])) { // Corrected to check 'on' within $configOriginal['join']
                        throw new SchemaTableConfigBuildException("Missing join condition for table '{$key}'. (key = 'on')");
                    }
                    if (!is_string($configOriginal['join']['on'])) {
                        throw new SchemaTableConfigBuildException("The join condition for table '{$key}' must be a string.");
                    }
                    if (array_key_exists('type', $configOriginal['join']) && !is_string($configOriginal['join']['type'])) {
                        throw new SchemaTableConfigBuildException("The 'join' type for table '{$key}' must be a string.");
                    }
                    if (!in_array($configBuilt['join']['type'], $this->typeJoinsAllowed)) {
                        $joinType = @(string) $configOriginal['join']['type'] ?? $configBuilt['join']['type'];
                        throw new SchemaTableConfigBuildException("Invalid join type '{$joinType}' for table '{$key}'. Allowed types: " . implode(', ', $this->typeJoinsAllowed));
                    }
                }
                // campos de dependencia
                if (!array_key_exists('dependency', $configBuilt)) {
                    throw new SchemaTableConfigBuildException("Missing 'dependency' configuration for table '{$key}'.");
                }
                if (!is_array($configBuilt['dependency'])) {
                    throw new SchemaTableConfigBuildException("The 'dependency' configuration for table '{$key}' must be an string or array.");
                }
                foreach ($configBuilt['dependency'] as $index => $field) {
                    if (!is_string($field)) {
                        throw new SchemaTableConfigBuildException("Dependency field at index '{$index}' for table '{$key}' must be a string.");
                    }
                    elseif (!array_key_exists($field, $this ->fieldsConfig)) {
                        throw new SchemaTableConfigBuildException("Dependency field '{$field}' for table '{$key}' not found in exposed fields.");
                    }
                }
            }

            $this ->tablesConfig[$key] = $configBuilt;
        }

        return true;
    }

    /**
     * Validacion de la configuracion de los campos
     * 
     * @throws SchemaFieldConfigBuildException
     * 
     * @return bool
     */
    public function validFieldsConfig() : bool {
        foreach ($this ->fieldsConfig as $key => $configOriginal) {
            $configOriginal = (array) $configOriginal;
            $configBuilt = $this ->buildFieldConfig($key, (array) $configOriginal);
            // Validaciones
            if (array_key_exists('table', $configOriginal) && !is_string($configOriginal['table'])) {
                throw new SchemaFieldConfigBuildException("The 'table' configuration for field '{$key}' must be a string.");
            }
            if (array_key_exists('name', $configOriginal) && !is_string($configOriginal['name'])) {
                throw new SchemaFieldConfigBuildException("The 'name' configuration for field '{$key}' must be a string.");
            }

            $this ->fieldsConfig[$key] = $configBuilt;
        }

        return true;
    }

    protected function buildTableConfig(string $key, array $config) : array {
        $config['is_primary'] = ($key === $this ->getPrimaryTable());
        $config['is_extra'] = $config['is_primary'] ? false : ($config['is_extra'] ?? false);
        
        // "nombre", "alias" y "declaración sql" de la tabla
        if (array_key_exists('name', $config)) {
            $config['name'] = @(string) ($config['name']);
            $config['alias'] = $key;
            $config['sql'] = $config['name'] . ' AS ' . $key;
        }
        else {
            $config['name'] =  $key;
            $config['alias'] = null;
            $config['sql'] = $key;
        }
        $config['sql_concat'] = $config['is_extra'] ? $config['name'] : ($config['alias'] ?? $config['name']);
        $config['access_level'] ??= 0;
        $config['read_disabled'] ??= false;
        $config['filter_disabled'] ??= false;
        $config['order_disabled'] ??= false;

        // configuracion para tablas secundarias
        if (!$config['is_primary']) {
            if (!$config['is_extra']) {
                $config['join'] = @(array) ($config['join'] ?? []);
                $config['join']['on'] = @(string) ($config['join']['on']);
                $config['join']['type'] = @(string) ($config['join']['type'] ?? 'INNER');
                $config['join']['type'] = strtoupper($config['join']['type']);
            }
            else {
                $config['filter_disabled'] = true;
                $config['order_disabled'] = true;
            }
            $config['dependency'] = @(array) ($config['dependency'] ?? []);
            $config['dependency'] = is_string($config['dependency']) ? [$config['dependency']] : $config['dependency'];
        }

        $config['_built_'] = true;
        return $config;
    }

    protected function buildFieldConfig(string $key, array $config) : array {
        $config['table'] = @(string) ($config['table'] ?? $this ->getPrimaryTable());
        $tableConfig = $this ->getTableConfig($config['table']);
        if (empty($tableConfig)) {
            throw new SchemaFieldConfigBuildException("The 'table' configuration for field '{$key}' was not found registered as a table configuration.");
        }
        
        $config['is_extra'] = $tableConfig['is_extra'];
        $config['access_level'] = $config['access_level'] ?? $tableConfig['access_level'];
        $config['access_denied'] = ($config['access_level'] > $this ->accesslevel);
        
        // "nombre", "alias", "declaración sql" y "declaración sql select" del campo
        if (array_key_exists('name', $config)) {
            $config['name'] = @(string) ($config['name']);
            $config['alias'] = $key;
            $config['sql'] = $tableConfig['sql_concat'] . '.' . $config['name'];
            $config['sql_select'] =  $tableConfig['sql_concat'] . '.' . $config['name'] . ' AS ' . $config['alias'];

        }
        else {
            $config['name'] = $key;
            $config['alias'] = null;
            $config['sql'] = $tableConfig['sql_concat'] . '.' . $config['name'];
            $config['sql_select'] =  $tableConfig['sql_concat'] . '.' . $config['name'];
        }
        // campos necesarios
        $config['read_disabled'] = $config['is_extra'] ? $tableConfig['read_disabled'] : ($config['read_disabled'] ?? $tableConfig['read_disabled']);
        $config['filter_disabled'] = $config['is_extra'] ? true : ($config['filter_disabled'] ?? $tableConfig['filter_disabled']);
        $config['order_disabled'] = $config['is_extra'] ? true : ($config['order_disabled'] ?? $tableConfig['order_disabled']);

        $config['_built_'] = true;
        return $config;
    }

    // -- Metodos para los callbacks --
    function setExtraCallback(string $tableKey, callable $callback, array $setting = []) : Schema {
        $setting['type'] = @(string) ($setting['type'] ?? 'compound_keys');
        $this ->extraCallbacks[$tableKey] = array_merge($setting, ['callback' => $callback]);

        return $this;
    }

    function hasExtraCallback(string $tableKey) {
        return isset($this ->extraCallbacks[$tableKey]);
    }

    function getExtraCallback(string $tableKey) : array|null {
        return $this ->extraCallbacks[$tableKey] ?? null;
    }
    
    // -- Metodos auxiliares-- 
    public function getPrimaryTable() : string|null {
        return array_key_first($this ->tablesConfig);
    }
    
    private function addToConfigArray(array $configArray, array|string $field, null|array $value = null) : array {
        if (is_array($field)) {
            foreach ($field as $key => $config) {
                $config = !is_array($config) ? [$config] : $config;
                $config = array_merge($configArray[$key] ?? [], $config);
                $configArray[$key] = $config;
                $configArray[$key]['_built_'] = false;
            }
        }
        else {
            $value = array_merge($configArray[$field] ?? [], $value ?? []);
            $configArray[$field] = $value;
            $configArray[$field]['_built_'] = false;
        }

        return $configArray;
    }

    // -- Metodos de utilidades --
    /**
     * Función para detectar y gestionar las dependencias entre tablas. (crucial para la construcción de las consultas)
     * 
     * @param array $tables - Tablas a procesar
     * 
     * @throws SchemaException
     * 
     * @return array
     */
    public function getFillerTables(array $tables) : array {
        $response = [];
        $primaryTable = $this->getPrimaryTable();
        $pendingTables = $tables;
        while (!empty($pendingTables)) {
            $table = array_key_first($pendingTables);
            $value = $pendingTables[$table];
            unset($pendingTables[$table]);
            
            if (isset($response[$table])) {
                continue;
            }
            elseif ($table === $primaryTable) {
                $response[$table] = $value;
                continue;
            }
            
            $tableConfig = $this->getTableConfig($table);
            if (empty($tableConfig)) {
                throw new SchemaException("Table '{$table}' not found in the schema configuration.");
            }
            elseif ($tableConfig['is_extra']) {
                throw new SchemaException("Table '{$table}' is a is_extra table and cannot be used in the main query.");
            }
            
            $key = array_key_first($tableConfig['dependency']);
            $fieldConfig = $this->getFieldConfig($tableConfig['dependency'][$key]);
            if (empty($fieldConfig)) {
                throw new SchemaException("Field '{$tableConfig['dependency'][$key]}' not found in the schema configuration.");
            }

            $parentTable = $fieldConfig['table'];
            $response[$table] = $value;

            if (!isset($pendingTables[$parentTable])) {
                $pendingTables[$parentTable] = false;
            }
        }
        
        return $response;
    }

    /**
     * Funcion para quitar de un array los items (campos) a los que no se tengan acceso (por nivel de acceso)
     * 
     * @param array $fields - Campos a procesar
     * 
     * @return array
     */
    public function purgeFields(array $fields) : array {
        foreach ($fields as $field => $value) {
            $config = $this ->getFieldConfig($field);
            if (empty($config)) {
                continue;
            }
            elseif ($config['access_denied']) {
                unset($fields[$field]);
            }
        }
        return $fields;
    }
}