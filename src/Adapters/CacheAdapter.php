<?php

namespace DancasDev\DQB\Adapters;

use DancasDev\DQB\Adapters\CacheAdapterInterface;
use DancasDev\DQB\Exceptions\CacheAdapterException;

class CacheAdapter implements CacheAdapterInterface {
    private $cacheDir;

    public function __construct(string $cacheDir = null) {
        if (!empty($cacheDir)) {
            $this ->setDir($cacheDir);
        }
    }

    /**
     * Establece el directorio de almacenamiento del caché.
     * 
     * @param string $cacheDir Ruta al directorio de almacenamiento del caché.
     * 
     * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
     * 
     * @throws CacheAdapterException - Si no se puede establecer el directorio de almacenamiento del caché.
     */
    public function setDir(string $cacheDir) : bool {
        $response = true;
        
        $this->cacheDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cacheDir);

        if (!is_dir($this->cacheDir)) {
            $response = mkdir($this->cacheDir, 0775, true);
        }

        if (!$response) {
            throw new CacheAdapterException('Error setting cache directory');
        }

        return $response;
    }

    public function get(string $key): mixed {
        if (empty($this ->cacheDir)) {
            return null;
        }

        $file = $this->cacheDir . DIRECTORY_SEPARATOR . $key;
        if (!file_exists($file)) {
            return null;
        }

        try {
            $data = json_decode(file_get_contents($file), true);
        } catch (\Throwable $th) {
            $data = null;
        }
        
        if (empty($data) || (!empty($data['ttl']) && $data['ttl'] < time())) {
            $this->delete($key);
            return null;
        }

        return $data['value'] ?? true;
    }

    public function save(string $key, $data, int|null $ttl = 60): bool {
        if (empty($this ->cacheDir)) {
            return false;
        }

        $file = $this->cacheDir . DIRECTORY_SEPARATOR . $key;
        $dataToSave = [
            'ttl' => empty($ttl) ? null : time() + $ttl,
            'value' => $data
        ];

        try {
            $dataToSave = json_encode($dataToSave);
        } catch (\Throwable $th) {
            return false;
        }

        return file_put_contents($file, $dataToSave) !== false;
    }

    public function delete(string $key): bool {
        if (empty($this ->cacheDir)) {
            return false;
        }

        $file = $this->cacheDir . DIRECTORY_SEPARATOR . $key;
        return unlink($file);
    }
}