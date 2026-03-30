<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\FiltersProcessorException;

class FiltersProcessor {
    /**
     * Límite de filtros por consulta (Ajustar según convenga)
     * 
     * @var int
     */
    protected static int $limit = 20;

    /**
     * Límite de iteraciones entre filtros por consulta (Ajustar según convenga)
     * 
     * @var int
     */
    protected static int $iterationLimit = 64;

    /**
     * Lista de operadores relacionales permitidos
     * 
     * @var array
     */
    private static array $relationalOperatorsAllowed = ['=','!=','>','>=','<','<=','LIKE'];

    /**
	 * Lista de operadores logicos permitidos.
	 *
	 * @var array
	 */
    private static array $logicalOperatorsAllowed = ['AND','OR'];

    /**
     * Lista de formato like permitidos
     * 
     * @var array
     */
    private static array $likeFormatsAllowed = ['BOTH','BEFORE','AFTER'];

    /**
     * Listado de callbacks validos
     * 
     * @var array
     */
    private static array $validCallbacks = [
        'config_key_override',
        'field_override'
    ];

    /**
     * Callbacks para determinados partes del proceso
     * 
     * @var array
     */
    private static array $callbacks = [];


    /**
     * Agregar un callback a la ejecución
     * 
     * @param string $key - Key del callback
     * @param callable $callback - Callback a ejecutar
     * 
     * @throws FiltersProcessorException
     * 
     * @return bool
     */
    public static function addCallback(string $key, callable $callback) : bool {
        if (!in_array($key, self::$validCallbacks)) {
            throw new FiltersProcessorException("The callback '{$key}' is not valid.");
        }

        self::$callbacks[$key] = $callback;

        return true;
    }

    /**
     * Obtener un callback
     * 
     * @param string $key - Key del callback
     * 
     * @return callable|null
     */
    public static function getCallback(string $key) : callable|null {
        return self::$callbacks[$key] ?? null;
    }

    /**
     * Remover un callback
     * 
     * @param string $key - Key del callback
     * 
     * @return callable|null
     */
    public static function removeCallback(string $key) : bool {
        if (!in_array($key, self::$validCallbacks)) {
            return false;
        }

        unset(self::$callbacks[$key]);

        return true;
    }

    public static function setLimit(int $limit) {
        self::$limit = $limit;
    }

    public static function setIterationLimit(int $iterationLimit) {
        self::$iterationLimit = $iterationLimit;
    }

    /**
     * Procesar filtros de la consulta
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $filters - Campos solicitados
     * @param bool $validateAccess - Validar si se tiene acceso a los campos que se intentan acceder
     * 
     * @throws FiltersProcessorException
     * 
     * @return array
     */
    public static function run(Schema $schema, array|null $filters = [], bool $validateAccess = true) : array {
        $response = [
            'sql' => [],
            'sql_params' => [],
            'tables' => [],
            'fields' => [],
            'filters_count' => 0,
            'filters_iteration_count' => 0,
            'add_logical_operator' => false
        ];

        self::recursiveFilterSearch($schema, $filters, $response, $validateAccess);
        $response['sql'] = implode('', $response['sql']);
        unset($response['add_logical_operator']);

        return $response;
    }

    /**
     * Buscar filtros de forma recursiva
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $reel - Campos solicitados
     * @param array $data - Datos de la consulta
     * @param string $breadcrumb - Ruta de navegación
     * @param bool $validateAccess -Validar acceso al campo
     * 
     * @throws FiltersProcessorException
     * 
     * @return null
     */
    private static function recursiveFilterSearch(Schema $schema, array $reel, array &$data, string $breadcrumb = 'root', bool $validateAccess = true) {
        $data['filters_iteration_count']++;
        if ($data['filters_iteration_count'] > self::$iterationLimit) {
            throw new FiltersProcessorException('The iteration limit of ' . self::$iterationLimit . ' items per request has been exceeded.');
        }
        
        # Carril (listado de filtros o filtro).
        // validar
        $firstKey = array_key_first($reel);
        if (!isset($firstKey)) {
            throw new FiltersProcessorException("The filter '{$breadcrumb}' is not defined correctly.");
        }
        
        // Listado de filtros
        if (is_array($reel[$firstKey])) {
            foreach ($reel as $key => $value) {
                $isGroup = true;
                // Apertura de grupo
                if (self::stringStartsWith($key, 'group')) {
                    $data['sql'][] = $data['add_logical_operator'] ? ' AND (' : '(';
                    $data['add_logical_operator'] = false;
                }
                elseif (self::stringStartsWith($key, 'orGroup')) {
                    $data['sql'][] = $data['add_logical_operator'] ? ' OR (' : '(';
                    $data['add_logical_operator'] = false;
                }
                else {
                    $isGroup = false;
                }
                
                // aplicar recursividad
                if (!is_array($value)) {
                    $breadcrumb .= '/' . $key;
                    throw new FiltersProcessorException("The filter '{$breadcrumb}' must be an array.");
                }

                self::recursiveFilterSearch($schema, $value, $data, $breadcrumb . '/' . $key, $validateAccess);

                // Cierre de grupo
                if ($isGroup) {
                    $data['sql'][] = ')';
                    $data['add_logical_operator'] = true;
                }
            }
        }
        // Filtro solo
        else {
            if ($data['filters_count'] >= self::$limit) {
                throw new FiltersProcessorException('The limit of ' . self::$limit . ' filters per request was exceeded.');
            }
            $result = self::buildFilter($schema, $reel, $breadcrumb, $validateAccess);

            $sql = ($data['add_logical_operator'] ? " {$result['logical_operator']} " : '') . "{$result['field']}";
            if ($result['relational_operator'] == 'LIKE') {
                $data['sql'][] = $sql . ' LIKE ?';
                if ($result['like_format'] == 'BEFORE') {
                    $data['sql_params'][] = '%' . $result['value'];
                }
                elseif ($result['like_format'] == 'AFTER') {
                    $data['sql_params'][] = $result['value'] . '%';
                }
                else {
                    $data['sql_params'][] = '%' . $result['value'] . '%';
                }
            }
            else {
                if ($result['value'] === null) {
                    $data['sql'][] = $sql . ($result['relational_operator'] == '!=' ? ' IS NOT NULL' : ' IS NULL');
                }
                else {
                    $data['sql'][] = $sql . " {$result['relational_operator']} ?";
                    $data['sql_params'][] = $result['value'];
                }
            }
            
            $data['fields'][$result['field_key']] = ($data['fields'][$result['field_key']] ?? 0) + 1;
            $data['tables'][$result['table_key']] = true;
            $data['add_logical_operator'] = true;
            $data['filters_count']++;
        }
    }

    /**
     * Validar si un string comienza con un prefijo
     * 
     * @param string $string - String validar
     * @param string $prefix - Prefijo a buscar
     * 
     * @return bool
     */
    public static function stringStartsWith(string $string, string $prefix) : bool {
        return substr($string, 0, strlen($prefix)) === $prefix;
    }

    /**
     * Construir filtro
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $filter - Filtro a construir
     * @param string $breadcrumb - Ruta de navegación
     * @param bool $validateAccess - Validar acceso al campo
     * 
     * @throws FiltersProcessorException
     * 
     * @return null
     */
    private static function buildFilter(Schema $schema, array $filter, string $breadcrumb, bool $validateAccess) {
        # Validar integridad del filtro
        // Campo
        $fieldKey = $filter[0] ?? $filter['field'] ?? null;
        if (!isset($fieldKey) || !is_string($fieldKey)) {
            throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' does not have a valid field defined (index: 0).");
        }


        $configKey = $fieldKey;
        if (array_key_exists('config_key_override', self::$callbacks)) {
            $configKey = self::getCallback('config_key_override')($configKey, $filter);
            if (!is_string($configKey)) {
                throw new FiltersProcessorException("The callback 'config_key_override' must return a string.");
            }
        }

        $config = $schema ->getFieldConfig($configKey);        
        if (empty($config)) {
            throw new FiltersProcessorException("The field '{$fieldKey}' does not exist in the schema (breadcrumb: {$breadcrumb}; index: 0).");
        }
        elseif ($validateAccess) {
            if ($config['filter_disabled']) {
                throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' has a field '{$fieldKey}' that cannot be used as a filter (index: 0).");}
            elseif ($config['access_denied']) {
                throw new FiltersProcessorException("No access to the field '{$fieldKey}' (breadcrumb: {$breadcrumb}; index: 0).");
            }
        }

        $field = $config['sql'];
        if (array_key_exists('field_override', self::$callbacks)) {
            $field = self::getCallback('field_override')($fieldKey, $config, $filter);
            if (!is_string($field)) {
                throw new FiltersProcessorException("The callback 'field_override' must return a string.");
            }
        }        
        
        // Valor
        $value = $filter[1] ?? $filter['value'] ?? null;
        if (is_array($value)) {
            throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' does not have a valid value defined (index: 1; valid: string, integer, float, bool, null).");
        }
            
        // Operador (relacional)
        $relationalOperator = $filter[2] ?? $filter['relational_operator'] ?? null;
        if (isset($relationalOperator)) {
            $isString = false;
            if (is_string($relationalOperator)) {
                $isString = true;
                $relationalOperator = strtoupper($relationalOperator);
            }
            
            if (!$isString || !in_array($relationalOperator, self::$relationalOperatorsAllowed)) {
                throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' does not have a valid relational operator defined (index: 2; valid: " . implode(', ', self::$relationalOperatorsAllowed) . ').');
            }
        }
        else {
            $relationalOperator = '=';
        }
        
        // Operador lógico
        $logicalOperator = $filter[3] ?? $filter['logical_operator'] ?? null;
        if (isset($logicalOperator)) {
            $isString = false;
            if (is_string($logicalOperator)) {
                $isString = true;
                $logicalOperator = strtoupper($logicalOperator);
            }

            if (!$isString || !in_array($logicalOperator, self::$logicalOperatorsAllowed)) {
                throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' does not have a valid logical operator defined (index: 3; valid: " . implode(', ', self::$logicalOperatorsAllowed) . ').');
            }
        }
        else {
            $logicalOperator = 'AND';
        }

        // Formato like
        $likeFormat = null;
        if ($relationalOperator == 'LIKE') {
            $likeFormat = $filter[4] ?? $filter['like_format'] ?? null;
            $likeFormat = empty($likeFormat) ? 'BOTH' : strtoupper((string) $likeFormat);
            if(!in_array($likeFormat, self::$likeFormatsAllowed)) {
                throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' does not have a valid 'LIKE PATTERN' defined (index: 4; valid: " . implode(', ', self::$likeFormatsAllowed) . ').');
            }
            
            // Valor
            if (!is_string($value) || !strlen($value)) {
                throw new FiltersProcessorException("The filter configuration '{$breadcrumb}' does not have a valid value defined (index: 1; valid: string).");
            }
        }

        return [
            'field_key' => $fieldKey,
            'table_key' => $config['table'],
            'field' => $field,
            'value' => $value,
            'relational_operator' => $relationalOperator,
            'logical_operator' => $logicalOperator,
            'like_format' => $likeFormat
        ];
    }
}
