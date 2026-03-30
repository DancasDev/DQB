<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\OrderProcessorException;

class OrderProcessor {
    /**
     * Límite de iteraciones entre ordenamientos por consulta (Ajustar según convenga)
     * 
     * @var int
     */
    protected static int $iterationLimit = 10;

    /**
	 * Lista de operadores para orden permitidos.
	 *
	 * @var array
	 */
    protected static array $orderOperatorsAllowed = ['ASC','DESC']; // todo: agregar funcionalidad 'random'

    /**
     * Procesar filtros de la consulta
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $order - Campos solicitados
     * @param array $aggregationFields - Campos de agregación presentes
     * 
     * @throws OrderProcessorException
     * 
     * @return array
     */
    public static function run(Schema $schema, array $order, array $aggregationFields = []) : array {
        $response = [
            'sql' => [],
            'tables' => [],
            'fields' => [],
            'order_count' => 0,
            'order_iteration_count' => 0,
        ];

        if (empty($order)) {
            throw new OrderProcessorException('No order has been specified for the query.');
        }
        
        foreach ($order as $fieldKey => $operator) {
            $response['order_iteration_count']++;
            if ($response['order_iteration_count'] > self::$iterationLimit) {
                throw new OrderProcessorException('The order iteration limit has been exceeded.');
            }

            $isAggregationField = array_key_exists($fieldKey, $aggregationFields);


            // Validar campo
            $config = $schema ->getFieldConfig($isAggregationField ? $aggregationFields[$fieldKey]['field'] : $fieldKey);
            if (empty($config)) {
                throw new OrderProcessorException('The field ' . $fieldKey . ' does not exist in the schema.');
            }
            elseif ($config['order_disabled']) {
                throw new OrderProcessorException('Field ' . $fieldKey . ' is disabled as a sort field.');
            }
            elseif ($config['access_denied']) {
                throw new OrderProcessorException('No access to the field ' . $fieldKey . '.');
            }

            $field = $isAggregationField ? $aggregationFields[$fieldKey]['sql'] : $config['sql'];

            // Validar operador
            $operator = @(string) $operator;
            $operator = strtoupper($operator);
            if (!in_array($operator, self::$orderOperatorsAllowed)) {
                throw new OrderProcessorException('Field ' . $fieldKey . ' has an invalid sort type. It must be one of the following: ' . implode(', ', self::$orderOperatorsAllowed) . '.');
            }

            // Almacenar
            $response['sql'][] = "{$field} {$operator}";
            $response['tables'][$config['table']] = true;
            $response['fields'][$fieldKey] = true;
            $response['order_count']++;
        }

        $response['sql'] = implode(', ', $response['sql']);
        
        return $response;
    }
}