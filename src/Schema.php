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
     * Nombre de la cache
     * 
     * @var string
     */
    protected $cacheName;

    /**
     * Tiempo de vida del cache
     * 
     * @var int|null
     */
    protected $cacheTtl;

    /**
     * Indica si la configuración del esquema ha sido construida
     * 
     * @var bool
     */
    protected $isBuilt = false;

    /**
     * Nivel acceso con el que se estara descartando lops campos restringido
     * 
     * @var int
     */
    protected $accesslevel = 0;

    /**
     * Listado de tablas con la configuración sin procesar
     * 
     * @var array
     */
    protected array $tablesConfigRaw = [];

    /**
     * Listado de tablas con la configuración procesada
     * 
     * @var array
     */
    protected array $tablesConfigProcessed = [];

    /**
     * Listado de campos con la configuración  sin procesar
     * 
     * @var array
     */
    protected array $fieldsConfigRaw = [];

    /**
     * Listado de campos con la configuración  sin procesar
     * 
     * @var array
     */
    protected array $fieldsConfigProcessed = [];

    /**
     * Listado de callback de solicitudes extra
     * 
     * @var array
     */
    protected array $extraCallbacks = [];
    
    /**
     * Lista de tipos de joins permitidos
     * 
     * @var array
     */
    protected array $typeJoinsAllowed = ['INNER','LEFT','RIGHT','OUTER','LEFT OUTER','RIGHT OUTER'];

    function __construct(array $tablesConfigRaw = [], array $fieldsConfigRaw = []) {
        $this ->resetConfig($tablesConfigRaw, $fieldsConfigRaw);
    }

    public function isBuilt() : bool {
        return $this ->isBuilt;
    }

    public function setAccessLevel(int $value = 0) : Schema {
        $this ->accesslevel = $value;

        return $this;
    }

    public function getConfig() : array {
        return [
            'tables' => $this ->tablesConfigProcessed,
            'fields' => $this ->fieldsConfigProcessed
        ];
    }

    public function getTableConfig(string $key) : array|null {
        return $this ->tablesConfigProcessed[$key] ?? null;
    }

    public function getTablesList() : array {
        return array_keys($this ->tablesConfigProcessed);
    }

    public function getFieldConfig(string $key) : array|null {
        return $this ->fieldsConfigProcessed[$key] ?? null;
    }

    public function getFieldsList() : array {
        return array_keys($this ->fieldsConfigProcessed);
    }

    public function getAccessLevel() : int {
        return $this ->accesslevel;
    }

    public function resetConfig(array $tablesConfigRaw = [], array $fieldsConfigRaw = []) {
        $this ->tablesConfigRaw = $tablesConfigRaw;
        $this ->tablesConfigProcessed = [];
        $this ->fieldsConfigRaw = $fieldsConfigRaw;
        $this ->fieldsConfigProcessed = [];
        $this ->isBuilt = false;
    }
    
    /**
     * Establecer cache
     * 
     * @param string $name - Nombre de la cache
     * @param int|null $ttl - (opcional) Tiempo de vida de la cache (en segundos)
     * @param string $dir - (opcional) Directorio donde se almacenará la cache o adaptador de cache
     * 
     * @throws CacheAdapterException
     * 
     * @return Schema
     */
    public function setCache(string $name, int $ttl = null, string|object $dir = null) : Schema {
        $this ->cacheName = $name;
        $this ->cacheTtl = $ttl;

        if (empty($this ->cacheAdapter)) {
            $dir ??= __DIR__ . '/writable';
            if(is_string($dir)) {
                $this ->cacheAdapter = new CacheAdapter($dir);
            }
            else {
                $classImplementList = class_implements($dir);
                if (!in_array('DancasDev\\DQB\\Adapters\\CacheAdapterInterface', $classImplementList)) {
                    throw new CacheAdapterException('Invalid implementation: The cache adapter must implement CacheAdapterInterface.', 1);
                }
                
                $this ->cacheAdapter = $dir;
            }
        }

        return $this;
    }

    /**
     * Establecer callback de solicitud extra
     * 
     * @param string $tableKey - Key de la tabla donde se ejecutará el callback
     * @param callable $callback - Callback a ejecutar
     * 
     * @return Schema
     */
    function setExtraCallback(string $tableKey, callable $callback) {
        $this ->extraCallbacks[$tableKey] = $callback;

        return $this;
    }

    /**
     * Validar si existe un callback de solicitud extra para una tabla
     * 
     * @param string $tableKey - Key de la tabla a validar
     * 
     * @return bool
     */
    function hasExtraCallback(string $tableKey) {
        return isset($this ->extraCallbacks[$tableKey]);
    }

    /**
     * Ejecutar callback de solicitud extra
     * 
     * @param string $tableKey - Key de la tabla a obtener el callback
     * @param array $callbackParams - Parámetros del callback
     * 
     * @throws SchemaException
     * 
     * @return array listado nuevos campos por cada registro
     */
    function runExtraCallback(string $tableKey, array $callbackParams) : array {
        $result = call_user_func_array($this ->extraCallbacks[$tableKey], $callbackParams);
        if (!is_array($result)) {
            throw new SchemaException('Extra callback "' . $tableKey . '"  must return an array.');
        }

        return $result;
    }

    /**
     * Obtener tabla primaria del esquema
     * 
     * @return string|null
     */
    public function getPrimaryTable() : string|null {
        return array_key_first($this ->tablesConfigRaw);
    }

    /**
     * Agregar tabla(s) a la configuración del esquema
     * 
     * @param array|string $table - tabla(s) o lista de tablas con sus respectivas configuraciones a agregar
     * @param array|null $value - configuración de la tabla (solo aplica cuando se agrega una sola tabla)
     * 
     * @return Schema
     */
    public function addTable(array|string $table, null|array $value = null) : Schema {
        if (is_array($table)) {
            foreach ($table as $key => $config) {
                $config = !is_array($config) ? [$config] : $config;
                $config = array_merge($this ->tablesConfigRaw[$key] ?? [], $config);
                $this ->tablesConfigRaw[$key] = $config;
            }
        }
        else {
            if (empty($value)) {
                $this ->tablesConfigRaw[$table] = [];
            }
            else {
                $value = array_merge($this ->tablesConfigRaw[$table] ?? [], $value);
                $this ->tablesConfigRaw[$table] = $value;
            }
        }

        $this ->isBuilt = false;

        return $this;
    }

    /**
     * Agregar campo(s) a la configuración del esquema
     * 
     * @param array|string $field - campo(s) o lista de campos con sus respectivas configuraciones a agregar
     * @param array|null $value - configuración del campo (solo aplica cuando se agrega una sola campo)
     * 
     * @return Schema
     */
    public function addField(array|string $field, null|array $value = null) : Schema {
        if (is_array($field)) {
            foreach ($field as $key => $config) {
                // Almacenar
                $config = !is_array($config) ? [$config] : $config;
                $config = array_merge($this ->fieldsConfigRaw[$key] ?? [], $config);
                $this ->fieldsConfigRaw[$key] = $config;
            }
        }
        else {
            if (empty($value)) {
                $this ->fieldsConfigRaw[$field] = [];
            }
            else {
                $value = array_merge($this ->fieldsConfigRaw[$field] ?? [], $value);
                $this ->fieldsConfigRaw[$field] = $value;
            }
        }

        $this->isBuilt = false;

        return $this;
    }

    /**
     * Carga la configuración del esquema desde un archivo.
     *
     * @param string $filePath Ruta al archivo de configuración.
     * @param string $format   Formato del archivo de configuración (json). Por defecto 'array'.
     * 
     * @throws SchemaException Si hay un error al cargar o parsear el archivo.
     * @throws SchemaTableConfigBuildException
     * @throws SchemaFieldConfigBuildException
     *
     * @return Schema
     */
    public function loadConfig(string $filePath, string $format = 'json'): Schema {
        if (!file_exists($filePath)) {
            throw new SchemaException("Configuration file not found: {$filePath}");
        }

        $configData = null;

        switch (strtolower($format)) {
            case 'json':
                $fileContent = file_get_contents($filePath);
                $configData = json_decode($fileContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new SchemaException("Error parsing JSON configuration: " . json_last_error_msg());
                }
                break;
            default:
                throw new SchemaException("Unsupported configuration format: {$format}");
        }

        if (!isset($configData['tables']) || !is_array($configData['tables'])) {
            throw new SchemaException("Invalid configuration: Missing or invalid 'tables' key.");
        }

        if (!isset($configData['fields']) || !is_array($configData['fields'])) {
            throw new SchemaException("Invalid configuration: Missing or invalid 'fields' key.");
        }

        $this->resetConfig($configData['tables'], $configData['fields']);
        
        return $this;
    }

    /**
     * Construir la configuración del esquema
     * 
     * @param bool $fromCache - (opcional) Indica si se debe intentar obtener la configuración desde la cache
     * 
     * @throws SchemaTableConfigBuildException
     * 
     * @return Schema
     */
    public function buildConfig(bool $fromCache = true) : Schema {
        $this ->tablesConfigProcessed = [];
        $this ->fieldsConfigProcessed = [];

        // Recuperar de cache
        $useCache = $fromCache && !empty($this ->cacheAdapter);
        if ($useCache) {
            $cacheData = $this ->cacheAdapter ->get($this ->cacheName);
            if (!empty($cacheData)) {
                $this ->tablesConfigProcessed = $cacheData['tables'];
                $this ->fieldsConfigProcessed = $cacheData['fields'];
                $this ->isBuilt = true;
            }
        }

        // procesar configuración
        if (!$this ->isBuilt) {
            foreach ($this ->tablesConfigRaw as $tableName => $tableConfig) {
                $this ->tablesConfigProcessed[$tableName] = $this ->buildTableConfig($tableName, $tableConfig);
            }
            foreach ($this ->fieldsConfigRaw as $fieldName => $fieldConfig) {
                $this ->fieldsConfigProcessed[$fieldName] = $this ->buildFieldConfig($fieldName, $fieldConfig);
            }
    
            $this ->isBuilt = true;
            
            // Almacenar de cache
            if ($useCache) {
                $this ->cacheAdapter ->save($this ->cacheName, [
                    'tables' => $this ->tablesConfigProcessed,
                    'fields' => $this ->fieldsConfigProcessed
                ], $this ->cacheTtl);
            }
        }

        # Post-proceso
        // Campos
        foreach ($this ->fieldsConfigProcessed as $fieldName => $fieldConfig) {
            //  Marcar acceso denegado
            $this ->fieldsConfigProcessed[$fieldName]['access_denied'] = ($fieldConfig['access_level'] > $this ->accesslevel);
        }
        
        return $this;
    }

    /**
     * Procesar configuración de una tabla
     * 
     * @param string $key - Nombre de la tabla
     * @param array $config - Configuración de la tabla
     * 
     * @throws SchemaTableConfigBuildException
     * 
     * @return array
     */
    protected function buildTableConfig(string $key, array $config) : array {
        # Validar/depurar campos de configuración
        $config['is_primary'] = ($key === $this ->getPrimaryTable());
        $config['is_extra'] = $config['is_primary'] ? false : ($config['is_extra'] ?? false);
        
        // "nombre", "alias" y "declaración sql" de la tabla
        if (array_key_exists('name', $config)) {
            if (!is_string($config['name'])) {
                throw new SchemaTableConfigBuildException("The 'name' configuration for table '{$key}' must be a string.");
            }
            $config['alias'] = $key;
            $config['sql'] = $config['name'] . ' AS ' . $key;
        }
        else {
            $config['name'] =  $key;
            $config['alias'] = null;
            $config['sql'] = $key;
        }

        // concatenación SQL
        $config['sql_concat'] = $config['is_extra'] ? $config['name'] : ($config['alias'] ?? $config['name']);

        // campos necesarios
        $config['access_level'] ??= 0;
        $config['read_disabled'] ??= false;
        $config['filter_disabled'] ??= false;
        $config['order_disabled'] ??= false;

        // tablas secundarias
        if (!$config['is_primary']) {
            ## Extracción por defecto
            if (!$config['is_extra']) {
                // Union
                if (!array_key_exists('join', $config)) {
                    throw new SchemaTableConfigBuildException("Missing 'join' configuration for table '{$key}'.");
                    return false;
                }
                elseif (!is_array($config['join'])) {
                    throw new SchemaTableConfigBuildException("The 'join' configuration for table '{$key}' must be an array.");
                }
                elseif (!array_key_exists('on', $config['join'])) {
                    throw new SchemaTableConfigBuildException("Missing join condition for table '{$key}'. (key = 'on')");
                }
                elseif (!is_string($config['join']['on'])) {
                    throw new SchemaTableConfigBuildException("The join condition for table '{$key}' must be a string.");
                }

                if (!array_key_exists('type', $config['join'])) {
                    $config['join']['type'] = 'INNER';
                }
                elseif (!is_string($config['join']['type'])) {
                    throw new SchemaTableConfigBuildException("Invalid join type for table '{$key}' (must be a string).");
                }
                else {
                    $config['join']['type'] = strtoupper($config['join']['type']);
                    if (!in_array($config['join']['type'], $this ->typeJoinsAllowed)) {
                        throw new SchemaTableConfigBuildException("Invalid join type '{$config['join']['type']}' for table '{$key}' Allowed types:" . implode(', ', $this->typeJoinsAllowed));
                    }
                }
            }
            ## Extración foranea
            else {
                // inhabilitar los filtros y ordenamiento
                $config['filter_disabled'] = true;
                $config['order_disabled'] = true;
            }

            ## ...
            // campos de dependencia
            if (!array_key_exists('dependency', $config)) {
                throw new SchemaTableConfigBuildException("Missing 'dependency' configuration for table '{$key}'.");
            }

            $config['dependency'] = is_string($config['dependency']) ? [$config['dependency']] : $config['dependency'];
            if (!is_array($config['dependency'])) {
                throw new SchemaTableConfigBuildException("The 'dependency' configuration for table '{$key}' must be an string or array.");
            }

            foreach ($config['dependency'] as $index => $field) {
                if (!is_string($field)) {
                    throw new SchemaTableConfigBuildException("Dependency field at index '{$index}' for table '{$key}' must be a string.");
                }
                elseif (!array_key_exists($field, $this ->fieldsConfigRaw)) {
                    throw new SchemaTableConfigBuildException("Dependency field '{$field}' for table '{$key}' not found in exposed fields.");
                }
            }
        }

        return $config;
    }

    /**
     * Procesar configuración de un campo
     * 
     * @param string $key - Nombre del campo
     * @param array $config - Configuración del campo
     * 
     * @throws SchemaFieldConfigBuildException
     * 
     * @return array
     */
    protected function buildFieldConfig(string $key, array $config) : array {
        # Validar/depurar campos de configuración
        // tabla
        if (!isset($config['table'])) {
            $config['table'] = $this ->getPrimaryTable(); // no definido, se asume la tabla primaria
        } elseif (!is_string($config['table'])) {
            throw new SchemaFieldConfigBuildException("The 'table' configuration for field '{$key}' must be a string.");
        }

        $tableConfig = $this ->getTableConfig($config['table']);
        if (empty($tableConfig)) {
            throw new SchemaFieldConfigBuildException("The 'table' configuration for field '{$key}' was not found registered as a table configuration.");
        }
        
        $config['is_extra'] = $tableConfig['is_extra'];

        // nivel de acceso
        $config['access_level'] = $config['access_level'] ?? $tableConfig['access_level'];
        $config['access_denied'] = null; // no calcular aqui, para evitar errores con relación a la cache
        
        // "nombre", "alias", "declaración sql" y "declaración sql select" del campo
        if (array_key_exists('name', $config)) {
            if (!is_string($config['name'])) {
                throw new SchemaFieldConfigBuildException("The 'name' configuration for field '{$key}' must be a string.");
            }
            
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

        return $config;
    }

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
}