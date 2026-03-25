<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\FieldsProcessorException;

class FieldsProcessor {
    /**
     * Lista de funciones de agregaciones validas
     * 
     * @var array
     */
    protected static array $aggregationFunctionsAllowed = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

    /**
     * Procesar campos solicitados
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param string $fields - Campos solicitados
     * 
     * @throws FieldsProcessorException
     * 
     * @return array
     */
    public static function run(Schema $schema, string $fields) : array {
        $response = [];

        $fields = str_replace(' ', '', $fields);

        # Construcción
        $processingMode = null;
        // todos lo campos
        if (empty($fields) || $fields === '*') {
            $processingMode = 'all';
            $response = self::processAllFields($schema);
        }
        // acortadores
        elseif (strpos($fields, '*') !== false) {
            $processingMode = 'shortener';
            $fields = explode(',', $fields);
            $response = self::processFieldsByShortener($schema, $fields);
        }
        // campos específicos
        else {
            $processingMode = 'specification';
            $fields = explode(',', $fields);
            $response = self::processFieldsBySpecification($schema, $fields);
        }
        
        if(empty($response['sql'])) {
            throw new FieldsProcessorException('No fields to process.');
        }
        
        $response['sql'] = implode(', ', $response['sql']);
        $response['processing_mode'] = $processingMode;

        return $response;
    }
    
    /**
     * Construir todos los campos
     * 
     * @param Schema $schema - Esquema de la consulta
     * 
     * @throws FieldsProcessorException
     * 
     * @return array|bool
     */
    protected static function processAllFields(Schema $schema) : array|bool {
        $result = self::initResult();

        $fieldsList = $schema ->getFieldsList();
        foreach ($fieldsList as $field) {
            try {
                $config = $schema ->getFieldConfig($field);
                if (empty($config)) continue;
                self::addItemToResult($field, null, $config, $result);
            } catch (FieldsProcessorException $e) {
                continue; // se ignora la excepción porque es que no se tiene acceso al compo
            }
        }

        return $result;
    }

    /**
     * Construir campos en base a acortadores (comodin "*")
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $fields - Campos a procesar
     * 
     * @throws FieldsProcessorException
     * 
     * @return array|bool
     */
    protected static function processFieldsByShortener(Schema $schema, array $fields) : array|bool {
        $result = self::initResult();

        $fieldsList = $schema ->getFieldsList();
        foreach ($fields as $field) {
            $fieldsToAdd = [];

            # Validar como se procesara el campo
            $shortenerPosition = strpos($field, '*');
            // sin acortador
            if ($shortenerPosition === false) {
                if (isset($fieldsList[$field])) {
                    $fieldsToAdd[] = $field;
                }
            }
            // Acortador por sufijo
            elseif ($shortenerPosition === 0) {
                $suffix = str_replace('*', '', $field);
                foreach ($fieldsList as $fieldName) {
                    if ($suffix !== substr($fieldName, -strlen($suffix))) {
                        continue;
                    }
                    
                    $fieldsToAdd[] = $fieldName;
                }
            }
            // Acortador por prefijo
            elseif ($shortenerPosition === strlen($field) - 1) {
                $prefix = str_replace('*', '', $field);
                foreach ($fieldsList as $fieldName) {
                    if ($prefix !== substr($fieldName, 0, $shortenerPosition)) {
                        continue;
                    }
                    
                    $fieldsToAdd[] = $fieldName;
                }
            }
            // Acortador en medio
            else {
                $x = explode('*', $field);
                $prefix = $x[0];
                $suffix = $x[1] ?? '';
                foreach ($fieldsList as $fieldName) {
                    if ($prefix !== substr($fieldName, 0, $shortenerPosition) || $suffix !== substr($fieldName, -strlen($suffix))) {
                        continue;
                    }
                    
                    $fieldsToAdd[] = $fieldName;
                }
            }
            
            if (empty($fieldsToAdd)) {
                if ($shortenerPosition === false) {
                    throw new FieldsProcessorException("The field '{$field}' does not exist.");
                }
                else {
                    throw new FieldsProcessorException("The field shortener '{$field}' has no matches.");
                }
            }

            foreach ($fieldsToAdd as $field) {
                $config = $schema ->getFieldConfig($field);
                self::addItemToResult($field, null, $config, $result);
            }
        }

        return $result;
    }

    /**
     * Construir campos en especificos
     * 
     *  @param Schema $schema - Esquema de la consulta
     *  @param array $fields - campos a procesar
     * 
     *  @throws FieldsProcessorException
     * 
     *  @return array|bool
     */
    protected static function processFieldsBySpecification(Schema $schema, array $fields) : array|bool {
        $result = self::initResult();
        
        foreach ($fields as $field) {
            $config = $schema ->getFieldConfig($field);
            if (!empty($config)) {
                self::addItemToResult($field, null, $config, $result);
            }
            else {
                // Añadir posible funcion de agregación
                $aggregationData = self::scanAggregationFunctions($field);
                if (!is_array($aggregationData) || !($config = $schema ->getFieldConfig($aggregationData['field']))) {
                    throw new FieldsProcessorException("The field '{$field}' does not exist.");
                }


                self::addItemToResult($aggregationData['field'], $aggregationData['function'], $config, $result);
            }
            
        }

        return $result;
    }
    
    ### Utilidades
    /**
     * Validar si un campo esta apto para utilizar
     * 
     * @param string $field - key del campo
     * @param array $config - configuración del campo
     * 
     * @throws FieldsProcessorException
     * 
     * @return bool
     */
    protected static function validField(string $field, array $config) : bool {
        if ($config['read_disabled']) {
            throw new FieldsProcessorException("The field '{$field}' is disabled for reading.");
        }
        elseif ($config['access_denied']) {
            throw new FieldsProcessorException("No access to the field '{$field}'.");
        }

        return true;
    }

    /**
     * Iniciar array de resultado
     * 
     * @return array
     */
    private static function initResult() : array {
        return [
            'sql' => [],
            'tables' => [],
            'fields' => []
        ];
    }

    /**
     * Agregar item al resultado
     * 
     * @param string $fieldKey - key del campo
     * @param string|null $aggregationfunctionName - Nombre de la función de agregación
     * @param array $fieldConfig - configuración del campo
     * @param array $result - array de resultado
     * 
     * @return void
     */
    private static function addItemToResult(string $fieldKey, string|null $aggregationfunctionName, array $fieldConfig, array &$result) {
        $byAggregation = !empty($aggregationfunctionName);
        $key = $byAggregation ? $fieldKey . '_' . strtolower($aggregationfunctionName) : $fieldKey;

        if (isset($result['fields'][$key])) {
            return; // ignorar si ya se proceso
        }
        
        self::validField($fieldKey, $fieldConfig); // en caso de error, se lanzara una excepción

        // tabla
        $result['tables'][$fieldConfig['table']] = true;
    
        // campo
        $result['fields'][$key] = $fieldKey;

        // sql
        if (!$byAggregation) {
            $result['sql'][] =  $fieldConfig['sql_select'];
        }
        else {
            $result['sql'][] = $aggregationfunctionName . '(' . $fieldConfig['sql'] . ') AS ' . $key;
        }
    }

    /**
     * Scanear si un string es un campo con una funcion de agregación
     * 
     * @param string $value - Valor a analizar
     * 
     * @return array|bool
     */
    public static function scanAggregationFunctions(string $value) : array|bool {
        $pattern = '/^([a-zA-Z_]+)\((.+)\)$/';
        if (!preg_match($pattern, $value, $matches)) {
            return false;
        }

        $matches[1] = strtoupper($matches[1]);
        if (!in_array($matches[1], self::$aggregationFunctionsAllowed)) {
            return false;
        }
        
        return [
            'function' => $matches[1],
            'field' => $matches[2]
        ];
    }
}