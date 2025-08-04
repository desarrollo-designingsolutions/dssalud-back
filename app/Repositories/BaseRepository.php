<?php

namespace App\Repositories;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BaseRepository
{
    protected $model;

    protected $cacheService;

    /**
     * Constructor del repositorio.
     *
     * @param  Model  $model  Modelo Eloquent asociado
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        // Laravel inyectará automáticamente CacheService
        $this->cacheService = app(CacheService::class);
    }

    // ---- Constructores e Instanciación ----

    /**
     * Crea una nueva instancia del modelo con datos iniciales.
     *
     * @param  array  $data  Datos iniciales para el modelo
     * @return Model
     */
    public function new($data = [])
    {
        return new $this->model($data);
    }

    /**
     * Crea una nueva instancia del modelo usando el método estático de Eloquent.
     *
     * @return Model
     */
    public function newModelInstance()
    {
        return $this->model::newModelInstance();
    }

    // ---- Consultas con Caché ----

    /**
     * Obtiene todos los registros con relaciones opcionales usando caché.
     *
     * @param  array  $with  Relaciones a cargar
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($with = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_get", ['with' => $with], 'string');

        return $this->cacheService->remember($cacheKey, function () use ($with) {
            return $this->model->with($with)->get();
        }); // Usa el defaultTtl de CacheService
    }

    /**
     * Obtiene un registro por ID con caché.
     *
     * @param  int  $id  ID del registro
     * @param  array  $with  Relaciones a cargar
     * @param  string|array  $select  Columnas a seleccionar
     * @param  array  $withCount  Conteos de relaciones a incluir
     * @return Model|null
     */
    public function find($id, $with = [], $select = '*', $withCount = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_find_{$id}", [
            'with' => $with,
            'select' => $select,
            'withCount' => $withCount,
        ], 'string');

        return $this->cacheService->remember($cacheKey, function () use ($id, $with, $select, $withCount) {
            return $this->model->select($select)
                ->withCount($withCount)
                ->with($with)
                ->find($id);
        }); // Usa el defaultTtl de CacheService
    }

    /**
     * Obtiene todos los registros sin filtros ni relaciones usando caché.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_all", [], 'string');

        return $this->cacheService->remember($cacheKey, function () {
            return $this->model->all();
        }); // Usa el defaultTtl de CacheService
    }

    /**
     * Obtiene registros activos según un criterio usando caché.
     *
     * @param  string  $key  Columna para filtrar
     * @param  mixed  $value  Valor del filtro
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function datos_activos($key = 'estado', $value = '1')
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_active", [$key => $value], 'string');

        return $this->cacheService->remember($cacheKey, function () use ($key, $value) {
            return $this->model->where($key, $value)->get();
        }); // Usa el defaultTtl de CacheService
    }

    // ---- Consultas sin Caché ----

    /**
     * Cuenta el número total de registros sin caché.
     *
     * @return int
     */
    public function count()
    {
        return $this->model->count();
    }

    /**
     * Obtiene el primer registro sin caché.
     *
     * @return Model|null
     */
    public function first()
    {
        return $this->model->first();
    }

    // ---- Operaciones de Escritura ----

    /**
     * Guarda un modelo (la invalidación se maneja vía Cacheable en el modelo).
     *
     * @param  Model  $model  Modelo a guardar
     * @return Model
     */
    public function save(Model $model)
    {
        $model->save();

        return $model;
    }

    /**
     * Elimina un registro por ID (la invalidación se maneja vía Cacheable en el modelo).
     *
     * @param  int  $id  ID del registro a eliminar
     * @return Model|null Modelo eliminado o null si no se encuentra
     */
    public function delete($id)
    {
        $model = $this->find($id);
        if (! $model) {
            return null;
        }
        $model->delete();

        return $model;
    }

    /**
     * Replica un modelo con datos opcionales y lo guarda.
     *
     * @param  Model  $model  Modelo a replicar
     * @param  array  $data  Datos adicionales para el clon
     * @return Model Modelo clonado y guardado
     */
    public function replicate(Model $model, $data = [])
    {
        $clone = $model->replicate();
        if (! empty($data)) {
            foreach ($data as $key => $value) {
                $clone->$key = $value;
            }
        }
        $clone->save();

        return $clone;
    }

    /**
     * Cambia el estado de un registro por ID.
     *
     * @param  int  $id  ID del registro
     * @param  mixed  $estado  Nuevo valor del estado
     * @param  string  $column  Columna a actualizar
     * @param  array  $with  Relaciones a cargar (no usado actualmente)
     * @return Model|null Modelo actualizado o null si no se encuentra
     */
    public function changeState($id, $estado, $column = 'estado', $with = [])
    {
        $model = $this->find($id);
        if (! $model) {
            return null;
        }
        $model->$column = $estado;
        $model->save();

        return $model;
    }

    /**
     * Cambia el estado de múltiples registros por IDs.
     *
     * @param  array  $ids  IDs de los registros
     * @param  mixed  $estado  Nuevo valor del estado
     * @param  string  $column  Columna a actualizar
     * @param  array  $with  Relaciones a cargar (no usado actualmente)
     * @return int Número de filas afectadas
     */
    public function changeStateArray($ids, $estado, $column = 'estado', $with = [])
    {
        return $this->model->whereIn('id', $ids)->update([$column => $estado]);
    }

    /**
     * Trunca la tabla del modelo y limpia el caché.
     *
     * @return void
     */
    public function truncate()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->model->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->cacheService->clearByPrefix("{$this->model->getTable()}_*");
    }

    // ---- Utilidades ----

    /**
     * Genera una clave de caché personalizada.
     *
     * @param  string  $prefix  Prefijo de la clave
     * @param  array  $params  Parámetros adicionales
     * @return string Clave generada
     */
    public function generateCacheKey(string $prefix, array $params = []): string
    {
        return $this->cacheService->generateKey($prefix, $params);
    }

    /**
     * Elimina una clave específica del caché.
     *
     * @param  string  $key  Clave a eliminar
     */
    public function forgetCache(string $key): void
    {
        $this->cacheService->forget($key);
    }

    /**
     * Genera un PDF a partir de una vista y lo entrega como stream o descarga.
     *
     * @param  string  $vista  Vista Blade para el PDF
     * @param  array  $data  Datos para la vista
     * @param  string  $nombre  Nombre del archivo PDF
     * @param  bool  $is_stream  Si es true, devuelve stream; si false, descarga
     * @param  bool  $landscape  Si es true, usa orientación horizontal
     * @return \Illuminate\Http\Response
     */
    public function pdf($vista, $data = [], $nombre = 'archivo', $is_stream = true, $landscape = false)
    {
        $cacheKey = $this->cacheService->generateKey("pdf_{$vista}", $data, 'string');
        $pdfContent = $this->cacheService->remember($cacheKey, function () use ($vista, $data, $landscape) {
            $pdf = \PDF::loadView($vista, compact('data'));
            if ($landscape) {
                $pdf->setPaper('legal', 'landscape');
            }

            return $pdf->output();
        }); // Usa el defaultTtl de CacheService

        $nombre .= '.pdf';
        $pdf = \PDF::loadHTML($pdfContent);

        return $is_stream ? $pdf->stream($nombre) : $pdf->download($nombre);
    }

    /**
     * Convierte valores 'null' o 'undefined' en null en un array.
     *
     * @param  array  $array  Array a procesar
     * @return array Array limpio
     */
    public function clearNull($array)
    {
        return array_map(function ($value) {
            // Solo reemplazar 'null' y 'undefined' como strings por null, no los valores booleanos o otros tipos
            if ($value === 'null' || $value === 'undefined') {
                return null;
            }

            return $value;
        }, $array);
    }

    // ---- Configuración ----

    /**
     * Establece la conexión de base de datos del modelo.
     *
     * @param  string|null  $connectionName  Nombre de la conexión (opcional)
     * @return array Nombre de la conexión y base de datos actual
     */
    public function setDatabaseConnection($connectionName = null)
    {
        if ($connectionName === null) {
            $connectionName = $this->model->getConnection()->getName();
        }
        $this->model->setConnection($connectionName);

        return [
            $this->model->getConnection()->getName(),
            $this->model->getConnection()->getDatabaseName(),
        ];
    }

    public function getModelClass()
    {
        return get_class($this->model);
    }

    /**
     * Inserta múltiples registros en lotes
     *
     * @param  array  $data  Array de arrays con los datos a insertar
     * @param  int  $batchSize  Número de registros por lote (por defecto 1000)
     */
    public function insertBatch(array $data, int $batchSize = 1000): bool
    {
        if (empty($data)) {
            return false;
        }

        // Dividir los datos en lotes
        $batches = array_chunk($data, $batchSize);

        foreach ($batches as $batch) {
            // Insertar registros en el lote actual
            if (! $this->model->newQuery()->insert($batch)) {
                throw new \Exception('Failed to insert batch of records.');
            }
        }

        return true;
    }

    /**
     * Actualiza múltiples registros con los mismos campos en lotes
     *
     * @param  array  $data  Array de arrays con los datos a actualizar, cada uno debe incluir el 'id'
     * @param  int  $batchSize  Número de registros por lote (por defecto 1000)
     */
    public function updateBatch(array $data, int $batchSize = 1000): bool
    {
        if (empty($data)) {
            return false;
        }

        // Validar que todos los registros tengan 'id'
        $ids = array_column($data, 'id');
        if (empty($ids)) {
            throw new \Exception('Each item in the batch must contain an "id" field.');
        }

        // Obtener los valores a actualizar (tomar el primer registro como referencia)
        $firstItem = reset($data);
        $updates = Arr::except($firstItem, ['id']);

        // Dividir los ids en lotes
        $idBatches = array_chunk($ids, $batchSize);

        foreach ($idBatches as $batchIds) {
            // Actualizar registros en el lote actual
            $affected = $this->model->whereIn('id', $batchIds)->update($updates);

            // Verificar que se actualizaron los registros esperados
            if ($affected !== count($batchIds)) {
                throw new \Exception('Not all records in the batch were updated.');
            }
        }

        return true;
    }
}
