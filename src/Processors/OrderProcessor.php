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
     * @param string $order - Campos solicitados
     * 
     * @throws OrderProcessorException
     * 
     * @return array
     */
    public static function run(Schema $schema, string $order) : array {
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
        
        $order = explode(',', $order);
        foreach ($order as $value) {
            $response['order_iteration_count']++;
            if ($response['order_iteration_count'] > self::$iterationLimit) {
                throw new OrderProcessorException('The order iteration limit has been exceeded.');
            }

            // Separar campo y operador
            $value = explode(':', $value);
            $field = trim($value[0]);
            $operator = strtoupper(trim($value[1] ?? 'ASC'));

            // Validar campo
            $config = $schema ->getFieldConfig($field);
            if (empty($config)) {
                throw new OrderProcessorException('The field ' . $field . ' does not exist in the schema.');
            }
            elseif ($config['order_disabled']) {
                throw new OrderProcessorException('Field ' . $field . ' is disabled as a sort field.');
            }
            elseif ($config['access_denied']) {
                throw new OrderProcessorException('No access to the field ' . $field . '.');
            }

            if (isset($response['fields'][$field])) {
                continue;
            }

            // Validar operador
            if (!in_array($operator, self::$orderOperatorsAllowed)) {
                throw new OrderProcessorException('Field ' . $field . ' has an invalid sort type. It must be one of the following: ' . implode(', ', self::$orderOperatorsAllowed) . '.');
            }

            // Almacenar
            $response['sql'][] = "{$config['sql']} {$operator}";
            $response['tables'][$config['table']] = true;
            $response['fields'][$field] = true;
            $response['order_count']++;
        }

        $response['sql'] = implode(', ', $response['sql']);
        
        return $response;
    }
}