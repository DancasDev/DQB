<?php

namespace DancasDev\DQB\Processors;

use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\HavingProcessorException;
use DancasDev\DQB\Exceptions\FiltersProcessorException;
use DancasDev\DQB\Processors\FiltersProcessor;

class HavingProcessor {

    /**
     * Procesar la cláusula HAVING
     * 
     * @param Schema $schema - Esquema de la consulta
     * @param array $having - Filtros del having
     * @param array $selectFields - Campos ya procesados por FieldsProcessor
     * @param bool $validateAccess - Validar si se tiene acceso a los campos que se intentan acceder
     * 
     * @return array
     */
    public static function run(Schema $schema, array $having, bool $validateAccess = true, array $selectFields) : array {
        // Remplazar callbacks
        $callbacks = ['config_key_override' => null, 'field_override' => null];
        foreach ($callbacks as $key => $callback) {
            $callbacks[$key] = FiltersProcessor::getCallback($key);
        }

        try {
            FiltersProcessor::addCallback('config_key_override', function ($fieldKey) use($selectFields) {
                if (!array_key_exists($fieldKey, $selectFields)) {
                    throw new HavingProcessorException("The field '{$fieldKey}' used in HAVING must be an aggregated field defined in the SELECT clause.");
                }

                return $selectFields[$fieldKey]['field'];
            });

            FiltersProcessor::addCallback('field_override', function ($fieldKey, $config) use($selectFields) {
                return $selectFields[$fieldKey]['sql'];
            });
            
            $response = FiltersProcessor::run($schema, $having, $validateAccess);
        } catch (FiltersProcessorException $th) {
            throw new HavingProcessorException($th->getMessage(), $th->getCode(), $th);
        }
        
        // restblacer callbacks
        foreach ($callbacks as $key => $callback) {
            if (is_callable($callback)) {
                $callbacks[$key] = FiltersProcessor::addCallback($key, $callback);
            }
        }

        return $response;
    }
    
}