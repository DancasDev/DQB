<?php

namespace DancasDev\DQB\Exceptions;

use Exception;

class SchemaFieldConfigBuildException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}