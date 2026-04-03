<?php

namespace DancasDev\DQB;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Processors\FieldsProcessor;
use DancasDev\DQB\Processors\FiltersProcessor;
use DancasDev\DQB\Processors\GroupByProcessor;
use DancasDev\DQB\Processors\HavingProcessor;
use DancasDev\DQB\Processors\OrderProcessor;
use DancasDev\DQB\Processors\PaginationProcessor;
use DancasDev\DQB\Exceptions\DQBException;
use DancasDev\DQB\Exceptions\FieldsProcessorException;
use DancasDev\DQB\Exceptions\FiltersProcessorException;
use DancasDev\DQB\Exceptions\GroupByProcessorException;
use DancasDev\DQB\Exceptions\HavingProcessorException;
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
     *      'tables' => [],
     *      'fields' => [],
     *      'fields_by_aggregation' => [],
     *      'processing_mode' => 'all|shortener|specification',
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
     * Agrupación procesada, estructura:
     * 
     * [
     *      'sql' => [],
     *      'tables' => [],
     *      'fields' => [],
     *      'group_by_count' => 0,
     *      'group_by_iteration_count' => 0,
     * ]
     * 
     * @var array
     */
    private $groupByBuildData = [];

    /**
     * HAVING procesada, estructura:
     * 
     * @todo definir estructura
     * 
     * @var array
     */
    private $havingBuildData = [];

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
     * @param ?array $filters - Filtros de la consulta
     * @param ?array $filtersDefault - Filtros por defecto de la consulta (esto no se limitaran si los campos estan habilitados)
     * @param ?array $groupBy - Agrupación de la consulta
     * @param ?array $having - Filtros de los grupos
     * @param ?array $havingDefault - filtros por defectos de los grupos 
     * @param ?array $order - Orden de la consulta
     * @param ?int $page - Página a consultar
     * @param ?int $itemsPerPage - Número de elementos por página
     * 
     * @throws FieldsProcessorException
     * @throws FiltersProcessorException
     * @throws GroupByProcessorException
     * @throws HavingProcessorException
     * @throws OrderProcessorException
     * @throws PaginationProcessorException
     * 
     * @return DQB
     */
    public function prepare(
        string $fields = '*',
        ?array $filters = null,
        ?array $filtersDefault = null,
        ?array $groupBy = null,
        ?array $having = null,
        ?array $havingDefault = null,
        ?array $order = null,
        ?int $page = null,
        ?int $itemsPerPage = null) : DQB {

        $this ->fieldsBuildData = FieldsProcessor::run($this->schema, $fields);
        $this ->filtersBuildData = ($filters !== null || $filtersDefault !== null) ? FiltersProcessor::run($this->schema, $filters, $filtersDefault) : [];
        $this ->groupByBuildData = ($groupBy !== null) ? GroupByProcessor::run($this->schema, $groupBy) : [];
        $this ->havingBuildData = (!empty($this ->groupByBuildData) && ($having !== null || $havingDefault !== null)) ? HavingProcessor::run($this->schema, $having, $havingDefault, $this ->fieldsBuildData['fields_by_aggregation'], true) : [];
        $this ->orderBuildData = ($order !== null) ? OrderProcessor::run($this->schema, $order, $this ->fieldsBuildData['fields_by_aggregation'] ?? []) : [];
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
    public function getSqlData(array|null $segments = null) : array {
        $response = [
            'query' => ['SELECT' => null, 'FROM' => null, 'JOIN' => null, 'WHERE' => null, 'GROUP BY' => null, 'HAVING' => null, 'ORDER BY' => null, 'LIMIT' => null],
            'params' => []
        ];

        if ($this ->isPrepared) {
            $segments ??= ['SELECT', 'FROM', 'JOIN', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'];
            $joinTables = [];

            $key = 'SELECT';
            if (in_array($key, $segments)) {
                $joinTables =  $this ->fieldsBuildData['tables'];
                $response['query'][$key] = $this ->fieldsBuildData['sql'];
            }

            $key = 'FROM';
            if (in_array($key, $segments)) {
                $tableConfig = $this ->schema ->getTableConfig($this ->schema ->getPrimaryTable());
                $response['query'][$key] = $tableConfig['sql'];
            }

            $key = 'WHERE';
            if (in_array($key, $segments) && !empty($this ->filtersBuildData)) {
                $joinTables += $this ->filtersBuildData['tables'];
                $response['query'][$key] = $this ->filtersBuildData['sql'];
                $response['params'] = $this ->filtersBuildData['sql_params'];
            }

            $key = 'GROUP BY';
            if (in_array($key, $segments) && !empty($this ->groupByBuildData)) {
                $joinTables += $this ->groupByBuildData['tables'];
                $response['query'][$key] = $this ->groupByBuildData['sql'];
            }


            $key = 'HAVING';
            if (in_array($key, $segments) && !empty($this ->havingBuildData)) {
                $joinTables += $this ->havingBuildData['tables'];
                $response['query'][$key] = $this ->havingBuildData['sql'];
                $response['params'] = array_merge($response['params'], $this ->havingBuildData['sql_params']);
            }

            $key = 'ORDER BY';
            if (in_array($key, $segments) && !empty($this ->orderBuildData)) {
                $joinTables += $this ->orderBuildData['tables'];
                $response['query'][$key] = $this ->orderBuildData['sql'];
            }

            $key = 'LIMIT';
            if (in_array($key, $segments) && !empty($this ->paginationBuildData)) {
                $response['query'][$key] = $this ->paginationBuildData['sql'];
            }

            $key = 'JOIN';
            if (in_array($key, $segments) && !empty($joinTables)) {
                $response['query'][$key] = $this ->getJoinSQL($joinTables);
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
}