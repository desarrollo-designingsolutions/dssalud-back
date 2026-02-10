<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    public $defaultTtl = 3600;

    /**
     * Genera una clave con un prefijo y tipo de dato.
     */
    public function generateKey(string $prefix, array $params = [], string $type = 'string'): string
    {
        $suffix = ! empty($params) ? '_'.md5(serialize($params)) : '';

        $project = env('KEY_REDIS_PROJECT');

        return "{$project}{$type}:{$prefix}{$suffix}";
    }

    /**
     * Recupera o genera datos en caché según el tipo de dato.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $redis_active = env('REDIS_ACTIVE', true);
        $data = null;
        if ($redis_active !== false) {

            $ttl = $ttl ?? $this->defaultTtl;
            $start = microtime(true);

            // Determinar el tipo de dato según el prefijo
            $type = $this->getTypeFromKey($key);

            $data = $this->getDataFromRedis($key, $type);

            if ($data !== null && $data !== false) {
                if ($type === 'string') {
                    $data = unserialize($data); // Deserializar solo para strings
                }
                $source = 'redis';
            } else {
                $data = $callback();
                $this->storeDataInRedis($key, $data, $type, $ttl);
                $source = 'database';
            }

            $end = microtime(true);
            $time = ($end - $start) * 1000;

            // Registrar métrica en Redis
            $metric = [
                'source' => $source,
                'response_time' => $time,
                'created_at' => now()->toDateTimeString(),
            ];
            Redis::rpush('list:cache_metrics', json_encode($metric)); // Cambiado a list:cache_metrics

            // Log::debug("Datos obtenidos de {$source}", ['key' => $key, 'time' => $time.'ms']);
        } else {
            // Si Redis no está activo, ejecutamos el callback directamente
            $data = $callback();
            Log::debug('Redis no está activo, ejecutando callback directamente', ['key' => $key]);
        }

        return $data;
    }

    /**
     * Elimina una clave del caché.
     */
    public function forget(string $key): void
    {
        Redis::del($key);
        // Log::debug('Caché eliminado', ['key' => $key]);
    }

    /**
     * Limpia claves por prefijo.
     */
    public function clearByPrefix(string $prefix): void
    {
        $cachePrefix = config('database.redis.options.prefix', 'laravel_database_');
        $fullPattern = "{$cachePrefix}{$prefix}*";

        $cursor = '0';
        $keysToDelete = [];

        do {
            [$cursor, $keys] = Redis::scan($cursor, 'MATCH', $fullPattern, 'COUNT', 100);
            if (! empty($keys)) {
                $array = [];
                foreach ($keys as $key => $value) {
                    $newK = str_replace($cachePrefix, '', $value);
                    $array[] = $newK;
                }
                $keysToDelete = array_merge($keysToDelete, $array);
                // Log::debug('Claves encontradas en esta iteración', ['keys' => $keys]);
            }

            if (count($keysToDelete) > 0) {
                Redis::del($keysToDelete);
                // Log::debug('Lote de cachés eliminado', ['count' => count($keysToDelete)]);
            }
            $keysToDelete = [];
        } while ($cursor !== '0');
    }

    /**
     * Determina el tipo de dato según el prefijo de la clave.
     */
    private function getTypeFromKey(string $key): string
    {
        if (strpos($key, 'set:') === 0) {
            return 'set';
        } elseif (strpos($key, 'list:') === 0) {
            return 'list';
        } elseif (strpos($key, 'hash:') === 0) {
            return 'hash';
        } elseif (strpos($key, 'zset:') === 0) {
            return 'zset';
        }

        return 'string'; // Por defecto
    }

    /**
     * Recupera datos de Redis según el tipo.
     */
    private function getDataFromRedis(string $key, string $type)
    {
        switch ($type) {
            case 'set':
                return Redis::smembers($key);
            case 'list':
                return Redis::lrange($key, 0, -1); // Obtener todos los elementos
            case 'hash':
                return Redis::hgetall($key);
            case 'zset':
                return Redis::zrange($key, 0, -1); // Obtener todos los elementos
            case 'string':
            default:
                return Redis::get($key);
        }
    }

    /**
     * Almacena datos en Redis según el tipo.
     */
    private function storeDataInRedis(string $key, $data, string $type, int $ttl): void
    {
        Redis::del($key); // Limpiar antes de almacenar

        switch ($type) {
            case 'set':
                if (is_array($data)) {
                    foreach ($data as $value) {
                        Redis::sadd($key, $value);
                    }
                    Redis::expire($key, $ttl);
                }
                break;
            case 'list':
                if (is_array($data)) {
                    foreach ($data as $value) {
                        Redis::rpush($key, $value);
                    }
                    Redis::expire($key, $ttl);
                }
                break;
            case 'hash':
                if (is_array($data)) {
                    Redis::hmset($key, $data);
                    Redis::expire($key, $ttl);
                }
                break;
            case 'zset':
                if (is_array($data)) {
                    foreach ($data as $score => $value) {
                        Redis::zadd($key, $score, $value); // Usar índices como puntajes
                    }
                    Redis::expire($key, $ttl);
                }
                break;
            case 'string':
            default:
                Redis::setex($key, $ttl, serialize($data));
                break;
        }
    }
}
