<?php

namespace DancasDev\DQB\Adapters;

interface CacheAdapterInterface {
  
  /**
   * Obtiene un valor almacenado en el caché.
   * 
   * @param string $key Clave del valor a obtener.
   * 
   * @return mixed Valor almacenado en el caché, o NULL si no se encuentra o ha expirado.
   */
  public function get(string $key): mixed;

  /**
   * Almacena un valor en el caché.
   * 
   * @param string $key Clave del valor a almacenar.
   * @param mixed $data Valor a almacenar.
   * @param int|null $ttl Tiempo de vida en segundos (opcional).
   * 
   * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
   */
  public function save(string $key, mixed $data, ?int $ttl = 60): bool;

  /**
   * Elimina un valor del caché.
   * 
   * @param string $key Clave del valor a eliminar.
   * 
   * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
   */
  public function delete(string $key): bool;
}