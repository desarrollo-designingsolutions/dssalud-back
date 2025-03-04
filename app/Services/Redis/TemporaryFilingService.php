<?php
namespace App\Services\Redis;

use Illuminate\Support\Facades\Redis;

class TemporaryFilingService
{
    private string $prefix = 'temp_filing:';
    private int $expiration = 3600; // 1 hora en segundos

    public function saveTemporaryData($key, array $data = [])
{
    // Generamos una clave única para este registro
    $redisKey = $this->prefix . $key;

    // Agregamos solo el timestamp por defecto, pero permitimos cualquier estructura en $data
    $tempData = array_merge(
        ['created_at' => now()->toDateTimeString()],
        $data
    );

    // Guardamos en Redis como JSON
    Redis::set($redisKey, json_encode($tempData));
    // Establecemos tiempo de expiración
    Redis::expire($redisKey, $this->expiration);

    return $redisKey;
}
    public function getTemporaryData($filingId)
    {
        $key = $this->prefix . $filingId;
        $data = Redis::get($key);

        return $data ? json_decode($data, true) : null;
    }

    public function addToTemporaryData($filingId, string $type, array $data)
    {
        $key = $this->prefix . $filingId;
        $currentData = $this->getTemporaryData($filingId) ?? [];

        // Si el type no existe, se inicializa como array vacío
        // Esto ya está cubierto por el ?? pero lo hacemos más explícito
        $currentData[$type] = $currentData[$type] ?? [];

        // Combinamos los datos nuevos con los existentes (si los hay)
        $currentData[$type] = array_merge($currentData[$type], $data);

        // Guardamos en Redis
        Redis::set($key, json_encode($currentData));
        Redis::expire($key, $this->expiration);

        return $currentData;
    }

    public function deleteTemporaryData($filingId)
    {
        $key = $this->prefix . $filingId;
        return Redis::del($key);
    }
}
