<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\FieldsProcessorException;

class FieldsProcessor {
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
        
        # Agregar todas las dependecias
        if (!empty($response['tables']['extra'])) {
            self::processDependencyFields($schema, $response);
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
                self::validField($field, $config); // en caso de error, se lanzara una excepción
                self::addItemToResult($field, $config, $result);
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
                if (isset($result['fields']['all'][$field])) {
                    continue;
                }

                $config = $schema ->getFieldConfig($field);
                self::validField($field, $config); // en caso de error, se lanzara una excepción
                self::addItemToResult($field, $config, $result);
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
            if (empty($config)) {
                throw new FieldsProcessorException("The field '{$field}' does not exist.");
            }
            elseif (isset($result['fields']['all'][$field])) {
                continue;
            }
            
            self::validField($field, $config); // en caso de error, se lanzara una excepción
            self::addItemToResult($field, $config, $result);
        }

        return $result;
    }
    
    /**
     * Procesar campos de dependencias requeridos por tablas extra, estos campos se incluiran en el resultado
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $result - resultado de los campos solicitados
     * 
     * @throws FieldsProcessorException
     * 
     * @return array|bool
     */
    private static function processDependencyFields(Schema $schema, array &$result) : array|bool {
        $processedTables = [];
        $pendingTables = array_keys($result['tables']['extra']);
        while (!empty($pendingTables)) {
            $tableKey = array_shift($pendingTables); // Obtener y eliminar el primer elemento
            if (isset($processedTables[$tableKey])) {
                continue;
            }

            $tableConfig = $schema ->getTableConfig($tableKey);                
            foreach ($tableConfig['dependency'] as $fieldKey) {
                if (isset($result['fields']['all'][$fieldKey])) {
                    continue;
                }

                $fieldConfig = $schema ->getFieldConfig($fieldKey);
                if ($fieldConfig['is_extra'] && !isset($processedTables[$fieldConfig['table']])) {
                    $pendingTables[] = $fieldConfig['table'];
                }
                
                self::addItemToResult($fieldKey, $fieldConfig, $result, false);
                
                $processedTables[$tableKey] = true;
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
            'tables' => ['main' => [], 'extra' => []],
            'fields' => ['main' => [], 'extra' => [], 'all' => []]
        ];
    }

    /**
     * Agregar item al resultado
     * 
     * @param string $fieldKey - key del campo
     * @param array $fieldConfig - configuración del campo
     * @param array $result - array de resultado
     * @param bool $inRequest - Indica si el campo esta en la solicitud
     * 
     * @return void
     */
    private static function addItemToResult(string $fieldKey, array $fieldConfig, array &$result, bool $inRequest = true) {
        $type = $fieldConfig['is_extra'] ? 'extra' : 'main';

        // tabla
        $result['tables'][$type][$fieldConfig['table']] ??= ['in_request' => $inRequest, 'fields' => []];
        $result['tables'][$type][$fieldConfig['table']]['fields'][] = $fieldKey;
        
        // campo
        $result['fields'][$type][$fieldKey] ??= ['in_request' => $inRequest, 'table' => $fieldConfig['table']];
        $result['fields']['all'][$fieldKey] = $inRequest;

        // sql
        if (!$fieldConfig['is_extra']) {
            $result['sql'][] = $fieldConfig['sql_select'];
        }
    }
}