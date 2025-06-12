<?php

namespace DancasDev\DQB;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Processors\FieldsProcessor;
use DancasDev\DQB\Processors\FiltersProcessor;
use DancasDev\DQB\Processors\OrderProcessor;
use DancasDev\DQB\Processors\PaginationProcessor;
use DancasDev\DQB\Exceptions\DQBException;
use DancasDev\DQB\Exceptions\FieldsProcessorException;
use DancasDev\DQB\Exceptions\FiltersProcessorException;
use DancasDev\DQB\Exceptions\OrderProcessorException;
use DancasDev\DQB\Exceptions\PaginationProcessorException;

class DQB {
    /**
     * Esquema de la consulta
     * 
     * @var Schema
     */
    private $schema;


    /**
     * Indica si los datos de la consulta han sido preparados
     * 
     * @var bool
     */
    protected $isPrepared = false;

    /**
     * Campos procesados, estructura:
     * 
     * [
     *      'sql' => '',
     *      'tables' => ['main' => [], 'extra' => []],
     *      'fields' => ['main' => [], 'extra' => []],
     *      'fields_list' => [],
     *      'processing_mode' => '', // 'all', 'shortener', 'specification'
     * ]
     * 
     * @var array
     */
    private $fieldsBuildData = [];

    /**
     * Filtros procesados, estructura:
     * 
     * [
     *      'sql' => '',
     *      'sql_params' => [],
     *      'tables' => [],
     *      'fields' => [],
     *      'filters_count' => 0,
     *      'filters_iteration_count' => 0
     * ]
     * 
     * @var array
     */
    private $filtersBuildData = [];

    /**
     * Orden procesado, estructura:
     * 
     * [
     *      'sql' => [],
     *      'tables' => [],
     *      'fields' => [],
     *      'order_count' => 0,
     *      'order_iteration_count' => 0,
     * ]
     * 
     * @var array
     */
    private $orderBuildData = [];

    /**
     * Paginación procesada, estructura:
     * 
     * [
     *      'sql' => '',
     *      'offset' => 25,
     *      'limit' => 1
     * ]
     * 
     * @var array
     */
    private $paginationBuildData = [];

    function __construct(Schema $schema = null) {
        if ($schema !== null) {
            $this ->setSchema($schema);
        }
    }

    /**
     * Establecer el esquema de la consulta
     * 
     * @param Schema $schema - Esquema de la consulta
     * 
     * @return DQB
     */
    public function setSchema(Schema $schema) {
        $this ->schema = $schema;

        $this ->fieldsBuildData = [];
        $this ->filtersBuildData = [];
        $this ->orderBuildData = [];
        $this ->paginationBuildData = [];
        $this ->isPrepared = false;

        return $this;
    }

    public function isPrepared() {
        return $this->isPrepared;
    }

    /**
     * Preparar datos para la construcción de la consulta
     * 
     * @param string $fields - Campos a seleccionar
     * @param array|null $filters - Filtros de la consulta
     * @param array|null $defaultFilters - Filtros por defecto de la consulta (esto no se limitaran si los campos estan habilitados)
     * @param array|null $order - Orden de la consulta
     * @param int|null $page - Página a consultar
     * @param int|null $itemsPerPage - Número de elementos por página
     * 
     * @throws FieldsProcessorException
     * @throws FiltersProcessorException
     * @throws OrderProcessorException
     * @throws PaginationProcessorException
     * 
     * @return DQB
     */
    public function prepare(string $fields = '*', array|null $filters = null, array|null $defaultFilters = null, array|null $order = null, int|null $page = null, int|null $itemsPerPage = null) : DQB {
        $this ->fieldsBuildData = FieldsProcessor::run($this->schema, $fields);
        $this ->filtersBuildData = ($filters !== null || $defaultFilters !== null) ? FiltersProcessor::run($this->schema, $filters, $defaultFilters) : [];
        $this ->orderBuildData = ($order !== null) ? OrderProcessor::run($this->schema, $order) : [];
        $this ->paginationBuildData = PaginationProcessor::run($page, $itemsPerPage);

        $this ->isPrepared = true;

        return $this;
    }
    
    /**
     * Obtener la consulta SQL
     * 
     * @param array|null $segments - Segmentos de la consulta a obtener
     * 
     * @return array
     */
    public function getSqlData(array|null $segments = null, array $s = []) : array {
        $response = [
            'query' => ['SELECT' => null, 'FROM' => null, 'JOIN' => null, 'WHERE' => null, 'ORDER BY' => null, 'LIMIT' => null],
            'params' => []
        ];

        if ($this ->isPrepared) {
            $segments ??= ['SELECT', 'FROM', 'JOIN', 'WHERE', 'ORDER BY', 'LIMIT'];
            $joinTables = [];
            if (in_array('SELECT', $segments)) {
                $joinTables =  $this ->fieldsBuildData['tables']['main'];
                $response['query']['SELECT'] = $this ->fieldsBuildData['sql'];
            }

            if (in_array('FROM', $segments)) {
                $tableConfig = $this ->schema ->getTableConfig($this ->schema ->getPrimaryTable());
                $response['query']['FROM'] = $tableConfig['sql'];
            }

            if (in_array('WHERE', $segments) && !empty($this ->filtersBuildData)) {
                $joinTables += $this ->filtersBuildData['tables'];
                $response['query']['WHERE'] = $this ->filtersBuildData['sql'];
                $response['params'] = $this ->filtersBuildData['sql_params'];
            }

            if (in_array('ORDER BY', $segments) && !empty($this ->orderBuildData)) {
                $joinTables += $this ->orderBuildData['tables'];
                $response['query']['ORDER BY'] = $this ->orderBuildData['sql'];
            }

            if (in_array('JOIN', $segments) && !empty($joinTables)) {
                $response['query']['JOIN'] = $this ->getJoinSQL($joinTables);
            }

            if (in_array('LIMIT', $segments) && !empty($this ->paginationBuildData)) {
                $response['query']['LIMIT'] = $this ->paginationBuildData['sql'];
            }
        }
        
        return $response;
    }

    /**
     * Obtener SQL
     * 
     * @param array|null $segments - Segmentos de la consulta a construir
     * @param array $segmentReplacements - Remplazos de los segmentos
     * 
     * @return array - ['query' => '', 'params' => []]
     */
    public function getSql(array|null $segments = null, array $segmentReplacements = []) : array|null {
        $response = ['query' => [], 'params' => []];
        if (!$this ->isPrepared) {
            throw new DQBException('You must prepare the data before executing the query.');
        }

        $sqlData = $this ->getSqlData($segments);
        foreach ($sqlData['query'] as $judgment => $value) {
            
            if (isset($segmentReplacements[$judgment])) {
                $value = is_callable($segmentReplacements[$judgment]) ? $segmentReplacements[$judgment]($value) : $segmentReplacements[$judgment];
            }
            
            if (empty($value)) continue;

            if ($judgment == 'JOIN') {
                $response['query'][] = ' ' . $value;
            }
            else {
                $response['query'][] = $judgment . ' ' . $value;
            }
        }

        $response['query'] = implode(' ', $response['query']);
        $response['params'] = $sqlData['params'];
        
        return $response;
    }

    // -- Metodos para agregar datos adicionales al restultado --
    public function  addExtraFields(array $records, bool $cleanFields = false) : array {
        if (empty($records)) return $records;
        
        foreach ($this ->fieldsBuildData['tables']['extra'] as $tableKey => $info) {
            if (!$this ->schema ->hasExtraCallback($tableKey)) {
                throw new DQBException('Extra callback not found for table ' . $tableKey);
            }

            $callbackSetting = $this ->schema ->getExtraCallback($tableKey);
            $callbackResult = $callbackSetting['callback']($records, $info, $this ->schema);

            # Tipo de aderencia
            // clave compuestas
            if ($callbackSetting['type'] == 'compound_keys') {
                $tableConfig = $this ->schema ->getTableConfig($tableKey);
                $records = $this ->adhesionByCompositeKey($records, $callbackResult, $info['fields'], $tableConfig);
            }
        }

        if ($cleanFields) {
            $records = $this ->clearFields($records);
        }

        return $records;
    }

    /**
     * Quita los campos que no fueron  solicitados
     * 
     * @param array $records - Registros a procesar
     * 
     * @return array
     */
    public function clearFields(array $records) : array {
        $fieldsToRemove = [];
        foreach ($this ->fieldsBuildData['fields']['all'] as $field => $inRequest) {
            if (!$inRequest) {
                $fieldsToRemove[] = $field;
            }
        }

        if (!empty($fieldsToRemove)) {
            foreach ($records as $key => $values) {
                foreach ($fieldsToRemove as $field) {
                    unset($records[$key][$field]);
                }
            }
        }

        return $records;
    }

    // -- Metodos Auxiliares --
    /**
     * Obtener uniones entre tablas
     * 
     * @param array $joinTables - Tablas unidas
     * 
     * @return string
     */
    private function getJoinSQL(array $joinTables) : string {
        $response = [];
        $tablePrimary = $this ->schema ->getPrimaryTable();
        $tableList = $this ->schema ->getTablesList();
        $joinTables = $this ->schema ->getFillerTables($joinTables);

        foreach ($tableList as $table) {
            if ($table == $tablePrimary || !array_key_exists($table, $joinTables)) {
                continue;
            }

            $config = $this ->schema ->getTableConfig($table);
            $response[] = $config['join']['type'] . ' JOIN ' . $config['sql'] . ' ON ' . $config['join']['on'];
        }

        return implode(' ', $response);
    }

    /**
     * Obtener key secundario de un registro
     * 
     * @param array $item - Datos del registro
     * @param array $fields - Campos que identifican el registro
     * 
     * @return string
     */
    private function buildRecordKey(array $item, array $fields) : string {
        $response = [];
        foreach ($fields as $field) {
            $response[] = $item[$field] ?? null;
        }

        return implode('_', $response);
    }

    /**
     * Agregar nuevos campos a los registros en base a una clave compuesta
     * 
     * @param array $records - Registros a procesar
     * @param array $results - Resultados de la consulta
     * @param array $fieldsToAdd - Campos a agregar
     * @param array $tableConfig - Configuración de la tabla
     * 
     * @return array - Registros procesados
     */
    private function adhesionByCompositeKey(array $records, array $results, array $fieldsToAdd, array $tableConfig) : array {
        // crear indice de los resultados
        $resultsIndex = [];
        foreach ($results as $index => $values) {
            $key = $this ->buildRecordKey($values, $tableConfig['dependency']);
            $resultsIndex[$key] = $index;
        }

        // Recorrer registros
        foreach ($records as $recordKey => &$recordValues) {
            $key = $this ->buildRecordKey($recordValues, $tableConfig['dependency']);
            $index = $resultsIndex[$key] ?? null;
            foreach ($fieldsToAdd as $field) {
                $recordValues[$field] = $results[$index][$field] ?? null;
            }
        }

        return $records;
    }
}