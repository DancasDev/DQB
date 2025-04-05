<?php

namespace DancasDev\DQB;

use PDO;
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
     * Conexión a la base de datos
     * 
     * @var PDO
     */
    private $connection;

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
     * Establecer la conexión a la base de datos
     * 
     * @param PDO|array $connection - Conexión a la base de datos. Puede ser un objeto PDO o un array con los datos de conexión.
     * 
     * Si se proporciona un array, debe tener la siguiente estructura:
     * [
     *  'host' => '', // Nombre del host de la base de datos.
     *  'username' => '', // Nombre de usuario para la conexión a la base de datos.
     *  'password' => '', // Contraseña para la conexión a la base de datos.
     *  'database' => '' // Nombre de la base de datos a la que conectarse.
     * ]
     * 
     * @throws DQBException - Si no se puede establecer la conexión a la base de datos.
     * 
     * @return DQB
     */
    public function setConnection(PDO|array $connection) : DQB {
        if ($connection instanceof PDO) {
            $this->connection = $connection;
        }
        else {
            foreach (['host', 'username', 'password', 'database'] as $key) {
                if (!array_key_exists($key, $connection) || !is_string($connection[$key])) {
                    throw new DQBException('Invalid connection parameters: you need to correctly provide the following parameters: host, username, password and database.');
                }
            }

            
            try {
                $this ->connection = new PDO('mysql:host=' . $connection['host'] . ';dbname=' . $connection['database'], $connection['username'], $connection['password']);
            } catch (\Throwable $th) {
                throw new DQBException('Error connecting to database "' . $connection['database'] . '"', 0, $th);
            }
        }

        return $this;
    }

    /**
     * Validar si la conexión a la base de datos ha sido establecida
     * 
     * @return bool
     */
    public function hasConnection() : bool {
        return $this ->connection instanceof PDO;
    }

    /**
     * Obtener la conexión a la base de datos
     * 
     * @return PDO
     */
    public function getConnection() : PDO {
        return $this ->connection;
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
     * @param array $filters - Filtros de la consulta
     * @param string $order - Orden de la consulta
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
    public function prepare(string $fields = '*', array $filters = null, string $order = null, int|null $page = null, int|null $itemsPerPage = null) : DQB {
        if (!$this ->schema ->isBuilt()) {
            $this ->schema ->buildConfig();
        }
        
        $this ->fieldsBuildData = FieldsProcessor::run($this->schema, $fields);
        $this ->filtersBuildData = ($filters !== null) ? FiltersProcessor::run($this->schema, $filters) : [];
        $this ->orderBuildData = ($order !== null) ? OrderProcessor::run($this->schema, $order) : [];
        $this ->paginationBuildData = PaginationProcessor::run($page, $itemsPerPage);

        $this ->isPrepared = true;

        return $this;
    }
    
    /**
     * Obtener la consulta SQL
     * 
     * @param array|null $segments - Segmentos de la consulta a obtener
     * @param bool $returnQueryArray - Indica si se devuelve el consulta como array
     * 
     * @return array
     */
    public function getSqlData(array|null $segments = null, bool $returnQueryArray = false) : array {
        $response = [
            'query' => ['SELECT' => null, 'FROM' => null, 'JOIN' => null, 'WHERE' => null, 'ORDER BY' => null, 'LIMIT' => null],
            'params' => []
        ];

        if ($this ->isPrepared) {
            $segments ??= ['SELECT', 'FROM', 'JOIN', 'WHERE', 'ORDER BY', 'LIMIT'];
            $joinTables = [];
            if (in_array('SELECT', $segments)) {
                $joinTables =  $this ->fieldsBuildData['tables']['main'];
                $response['query']['SELECT'] = 'SELECT ' . $this ->fieldsBuildData['sql'];
            }

            if (in_array('FROM', $segments)) {
                $tableConfig = $this ->schema ->getTableConfig($this ->schema ->getPrimaryTable());
                $response['query']['FROM'] = 'FROM ' . $tableConfig['sql'];
            }

            if (in_array('WHERE', $segments) && !empty($this ->filtersBuildData)) {
                $joinTables += $this ->filtersBuildData['tables'];
                $response['query']['WHERE'] = 'WHERE ' . $this ->filtersBuildData['sql'];
                $response['params'] = $this ->filtersBuildData['sql_params'];
            }

            if (in_array('ORDER BY', $segments) && !empty($this ->orderBuildData)) {
                $joinTables += $this ->orderBuildData['tables'];
                $response['query']['ORDER BY'] = 'ORDER BY ' . $this ->orderBuildData['sql'];
            }

            if (in_array('JOIN', $segments) && !empty($joinTables)) {
                $response['query']['JOIN'] = $this ->getJoinSQL($joinTables);
            }

            if (in_array('LIMIT', $segments) && !empty($this ->paginationBuildData)) {
                $response['query']['LIMIT'] = 'LIMIT ' . $this ->paginationBuildData['sql'];
            }
        }

        $response['query'] = $returnQueryArray ? $response['query'] : implode(' ', $response['query']);
        
        return $response;
    }

    /**
     * validar si todo esta bien para ejecutar una consulta
     * 
     * @throws DQBException
     * 
     * @return bool 
     */
    private function validate() : bool {
        if (!$this ->isPrepared) {
            throw new DQBException('You must prepare the data before executing the query.');
        }
        elseif (!$this ->hasConnection()) {
            throw new DQBException('You must set the database connection before executing the query.');
        }

        return true;
    }


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
     * Obtener registro
     * 
     * @throws DQBException
     * 
     * @return array
     */
    public function find() : array {
        $response = [];

        $this ->validate();

        // consulta principal
        $sqlData = $this ->getSqlData();
        
        $sqlData['query'] = $this ->connection ->prepare($sqlData['query']);

        try {
            $sqlData['query'] ->execute($sqlData['params']);
        } catch (\Throwable $th) {
            throw new DQBException('Error fetching data from database.', 0, $th);
        }
        $response = $sqlData['query'] ->fetchAll(PDO::FETCH_ASSOC);

        // consulta secundarias
        if (!empty($this ->fieldsBuildData['tables']['extra']) && !empty($response)) {
            $response = $this ->addExtraFields($response);
        }

        return $response;
    }

    /**
     * Contar todos los registros
     * 
     * @throws DQBException
     * 
     * @return int
     */
    public function countAll() : int {
        $this ->validate();

        $sqlData = $this ->getSqlData(['FROM'], true);
        $sqlData['query']['SELECT'] = 'SELECT COUNT(*) AS n';
        $sqlData['query'] = implode(' ', $sqlData['query']);
        
        $sqlData['query'] = $this ->connection ->prepare($sqlData['query']);

        try {
            $sqlData['query'] ->execute();
        } catch (\Throwable $th) {
            throw new DQBException('Error fetching data from database.', 0, $th);
        }

        return $sqlData['query'] ->fetch(PDO::FETCH_ASSOC)['n'] ?? 0;
    }

    /**
     * Contar resultados
     * 
     * @throws DQBException
     * 
     * @return int
     */
    public function count() : int {
        $this ->validate();

        $sqlData = $this ->getSqlData(['FROM','JOIN','WHERE'], true);
        $sqlData['query']['SELECT'] = 'SELECT COUNT(*) AS n';
        $sqlData['query'] = implode(' ', $sqlData['query']);
        
        $sqlData['query'] = $this ->connection ->prepare($sqlData['query']);

        try {
            $sqlData['query'] ->execute($sqlData['params']);
        } catch (\Throwable $th) {
            throw new DQBException('Error fetching data from database.', 0, $th);
        }

        return $sqlData['query'] ->fetch(PDO::FETCH_ASSOC)['n'] ?? 0;
    }

    /**
     * Correr callback de solicitud extra
     * 
     * @param array $records - Registros a procesar
     * 
     * @throws DQBException
     * @throws SchemaException
     * 
     * @return array
     */
    private function addExtraFields(array $records) : array {
        foreach ($this ->fieldsBuildData['tables']['extra'] as $tableKey => $info) {
            if (!$this ->schema ->hasExtraCallback($tableKey)) {
                throw new DQBException('Extra callback not found for table ' . $tableKey);
            }

            // obtener configuración
            $tableConfig = $this ->schema ->getTableConfig($tableKey);

            // obtener y ejecutar callback (puede lanzar la excepción SchemaException)
            $result = $this ->schema ->runExtraCallback($tableKey, [$records, $info['fields'], $tableConfig]);

            foreach ($result as $key => $values) {
                if (isset($records[$key])) {
                    $records[$key] += $values;
                }
            }
        }

        return $records;
    }
}