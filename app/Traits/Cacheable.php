<?php

namespace App\Traits;

use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

trait Cacheable
{
    /**
     * Prefijos de caché por defecto que siempre se incluirán
     * El tipo de dato (string:, set:, list:, etc.) debe incluirse explícitamente
     */
    protected $defaultCachePrefixes = [
        'string:{table}_paginate*',
        'string:{table}_find_{id}*',
        'string:{table}_list*',
        'string:{table}_paginateAgenda*',
        'string:{table}_paginateConciliation*',
        'string:{table}_paginateConciliationInvoices*',
    ];

    /**
     * Boot del trait para registrar eventos
     */
    public static function bootCacheable()
    {
        $cacheService = app(CacheService::class);

        static::created(function ($model) use ($cacheService) {
            $model->invalidateCache($cacheService);
        });

        static::updated(function ($model) use ($cacheService) {
            $model->invalidateCache($cacheService);
        });

        static::deleted(function ($model) use ($cacheService) {
            $model->invalidateCache($cacheService);
        });

        static::saved(function ($model) use ($cacheService) {
            $model->invalidateCache($cacheService);
        });
    }

    /**
     * Invalidar las claves de caché asociadas al modelo
     */
    protected function invalidateCache(CacheService $cacheService)
    {
        $table = $this->getTable();

        foreach ($this->getCachePrefixes() as $prefix) {

            $project = env('KEY_REDIS_PROJECT');

            $prefix = $project.$prefix;

            $usePattern = str_ends_with($prefix, '*');
            $cleanPrefix = $usePattern ? rtrim($prefix, '*') : $prefix;

            $cacheKeyPrefix = $this->buildCacheKeyPrefix($cleanPrefix, $table);

            if ($usePattern) {
                $cacheService->clearByPrefix($cacheKeyPrefix);
            } else {
                $cacheService->forget($cacheKeyPrefix);
            }

            $legacyPrefix = preg_replace('/^(string|set|list|hash|zset):/', '', $cacheKeyPrefix);
            if ($legacyPrefix !== $cacheKeyPrefix) {
                if ($usePattern) {
                    $cacheService->clearByPrefix($legacyPrefix);
                } else {
                    $cacheService->forget($legacyPrefix);
                }
            }

            // Log::debug('Caché invalidado', [
            //     'prefix' => $cacheKeyPrefix,
            //     'legacy_prefix' => $legacyPrefix,
            //     'pattern' => $usePattern,
            // ]);
        }
    }

    /**
     * Obtener todos los prefijos de caché, combinando los por defecto con los personalizados
     */
    protected function getCachePrefixes(): array
    {
        return array_merge(
            $this->defaultCachePrefixes,
            $this->customCachePrefixes ?? [] // Solo usa $defaultCachePrefixes y $customCachePrefixes
        );
    }

    /**
     * Construir el prefijo de la clave de caché para coincidir con CacheService
     */
    protected function buildCacheKeyPrefix(string $prefix, string $table): string
    {
        $prefix = str_replace('{table}', $table, $prefix);

        if (strpos($prefix, '{id}') !== false && $this->id) {
            return str_replace('{id}', $this->id, $prefix);
        }

        return $prefix;
    }

    /**
     * Método público para invalidar manualmente
     */
    public function clearCache()
    {
        $this->invalidateCache(app(CacheService::class));
    }
}
