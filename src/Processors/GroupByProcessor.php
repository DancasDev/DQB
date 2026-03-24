<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\GroupByProcessorException;

class GroupByProcessor {
    /**
     * Límite de iteraciones entre agrupación por consulta (Ajustar según convenga)
     * 
     * @var int
     */
    protected static int $iterationLimit = 6;

    /**
     * Procesar orden de la consulta
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $groupBy - agrupaciones solicitadas
     * 
     * @throws GroupByProcessorException
     * 
     * @return array
     */
    public static function run(Schema $schema, array $groupBy) : array {
        $response = [
            'sql' => [],
            'tables' => [],
            'fields' => [],
            'group_by_count' => 0,
            'group_by_iteration_count' => 0,
        ];

        if (empty($groupBy)) {
            throw new GroupByProcessorException('No grouping has been specified for the query.');
        }
        
        foreach ($groupBy as $field) {
            $response['group_by_iteration_count']++;
            if ($response['group_by_iteration_count'] > self::$iterationLimit) {
                throw new GroupByProcessorException('The group by iteration limit has been exceeded.');
            }

            // Validar campo
            $config = $schema ->getFieldConfig($field);
            if (empty($config)) {
                throw new GroupByProcessorException('The field ' . $field . ' does not exist in the schema.');
            }
            elseif ($config['group_by_disabled']) {
                throw new GroupByProcessorException('Field ' . $field . ' is disabled for grouping.');
            }
            elseif ($config['access_denied']) {
                throw new GroupByProcessorException('No access to the field ' . $field . '.');
            }

            // Almacenar
            $response['sql'][] = $config['sql'];
            $response['tables'][$config['table']] = true;
            $response['fields'][$field] = true;
            $response['group_by_count']++;
        }

        $response['sql'] = implode(', ', $response['sql']);
        
        return $response;
    }
}