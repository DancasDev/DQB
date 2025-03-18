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
        
        if(empty($response['sql'])) {
            throw new FieldsProcessorException('No fields to process.');
        }
        
        # Agregar todas las dependecias
        exit(json_encode($response));
        if (!empty($response['tables']['extra'])) {
            $response = $this ->addExtraDependencies($schema, $response);
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
        $response = [
            'sql' => [],
            'tables' => ['main' => [], 'extra' => []],
            'fields' => ['main' => [], 'extra' => []],
            'fields_list' => []
        ];

        $schemaConfig = $schema ->getConfig();
        foreach ($schemaConfig['fields'] as $field => $config) {
            self::validField($field, $config); // en caso de error, se lanzara una excepción

            $type = $config['is_extra'] ? 'extra' : 'main';
            $response['sql'][] = $config['sql_select'];
            $response['tables'][$type][$config['table']] = true;
            $response['fields'][$type][$field] = true;
            $response['fields_list'][$field] = true;
        }

        return $response;
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
        $response = [
            'sql' => [],
            'tables' => ['main' => [], 'extra' => []],
            'fields' => ['main' => [], 'extra' => []],
            'fields_list' => []
        ];

        $fieldsList = $schema ->getFieldsList();
        foreach ($fields as $field) {
            $fieldsToAdd = [];

            # Validar como se procesara el campo
            $shortenerPosition = strpos($field, '*');
            // sin acortador
            if ($shortenerPosition === false) {
                if (isset($response['fields_list'][$field])) {
                    continue;
                }
                elseif (isset($fieldsList[$field])) {
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
                $result = explode('*', $field);
                $prefix = $result[0];
                $suffix = $result[1] ?? '';
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
                self::validField($field, $config); // en caso de error, se lanzara una excepción

                $type = $config['is_extra'] ? 'extra' : 'main';
                $response['sql'][] = $config['sql_select'];
                $response['tables'][$type][$config['table']] = true;
                $response['fields'][$type][$field] = true;
                $response['fields_list'][$field] = true;
            }
        }

        return $response;
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
        $response = [
            'sql' => [],
            'tables' => ['main' => [], 'extra' => []],
            'fields' => ['main' => [], 'extra' => []],
            'fields_list' => []
        ];
        
        foreach ($fields as $field) {
            $config = $schema ->getFieldConfig($field);
            if (empty($config)) {
                throw new FieldsProcessorException("The field '{$field}' does not exist.");
            }
            elseif (isset($response['fields_list'][$field])) {
                continue;
            }
            
            self::validField($field, $config); // en caso de error, se lanzara una excepción

            $type = $config['is_extra'] ? 'extra' : 'main';
            $response['sql'][] = $config['sql_select'];
            $response['tables'][$type][$config['table']] = true;
            $response['fields'][$type][$field] = true;
            $response['fields_list'][$field] = true;
        }

        return $response;
    }
    
    /**
     * Validar si un campo esta acto para utilizar
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
     * Agregar campos foraneos de dependencias
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $result - resultado de los campos solicitados
     * 
     * @throws FieldsProcessorException
     * 
     * @return array|bool
     */
    protected static function addExtraDependencies(Schema $schema, array $result) : array|bool {
        $processedTables = [];
        $pendingTables = $result['tables']['extra'];
        while (!empty($pendingTables)) {
            $table = array_key_first($pendingTables);
            $value = $pendingTables[$table];
            unset($pendingTables[$table]);

            if (isset($processedTables[$table])) {
                continue;
            }

            $tableConfig = $schema ->getTableConfig($table);                
            foreach ($tableConfig['dependency'] as $field) {
                // Ignorar si el campo existe en los campos solicitados
                if (isset($result['fields_list'][$field])) {
                    continue;
                }

                $fieldConfig = $schema ->getFieldConfig($field);
                if ($fieldConfig['is_extra'] && !isset($processedTables[$fieldConfig['table']])) {
                    $pendingTables[$fieldConfig['table']] ??= false;
                }
                
                $type = $fieldConfig['is_extra'] ? 'extra' : 'main';
                $result['sql'][] = $fieldConfig['sql_select'];
                $result['tables'][$type][$fieldConfig['table']] = false;
                $result['fields'][$type][$field] = false;
                $result['fields_list'][$field] = false;
                
                $processedTables[$table] = $value;
            }
        }

        return $result;
    }
}