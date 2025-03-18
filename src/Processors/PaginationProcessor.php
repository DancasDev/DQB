<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Exceptions\PaginationProcessorException;

class PaginationProcessor {

    /**
     * Límite de items por página (Ajustar según convenga)
     * 
     * @var int
     */
    protected static int $itemsPerPageLimit = 100;

    /**
     * Número de items por página por defecto (Ajustar según convenga)
     * 
     * @var int
     */
    protected static int $itemsPerPageDefault = 25;

    /**
     * Procesar paginación solicitada
     * 
     * @param int $page - Página solicitada
     * @param int $itemsPerPage - Items por página
     * 
     * @throws FieldsProcessorException
     * 
     * @return array
     */
    public static function run(int|null $page = null, int|null $itemsPerPage = null) : array {
        $response = [
            'sql' => '',
            'offset' => null,
            'limit' => null
        ];

        
        $page = ($page == null || $page <= 0) ? 1 : $page;
        $itemsPerPage = ($itemsPerPage == null || $itemsPerPage <= 0) ? self::$itemsPerPageDefault : $itemsPerPage;
        if ($itemsPerPage > self::$itemsPerPageLimit) {
            throw new PaginationProcessorException("The number of items per page in pagination cannot be greater than '".self::$itemsPerPageLimit."'.");
        }
        
        $response['offset'] = ($page - 1) * $itemsPerPage;
        $response['limit'] = $itemsPerPage;

        $response['sql'] = ($response['offset'] == 0) ? "{$response['limit']}" : "{$response['offset']}, {$response['limit']}";

        return $response;
    }
}