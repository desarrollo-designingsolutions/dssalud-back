<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BaseRepository
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function new($data = [])
    {
        return new $this->model($data);
    }

    public function newModelInstance()
    {
        return $this->model::newModelInstance();
    }

    public function all()
    {
        return $this->model->all();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function first()
    {
        return $this->model->first();
    }

    public function get($with = [])
    {
        return $this->model->with($with)->get();
    }

    public function find($id, $with = [], $select = '*', $withCount = [])
    {
        return $this->model->select($select)->withCount($withCount)->with($with)->find($id);
    }

    public function save(Model $model)
    {
        $model->save();

        return $model;
    }

    public function make(Model $model) //esto es para simular un registro
    {
        $model->make();

        return $model;
    }

    public function replicate(Model $model, $data = []) //esto es para clonar un registro
    {
        $clone = $model->replicate();

        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $clone['key'] = $value;
            }
        }

        return $clone->save();
    }

    public function delete($id)
    {
        try {
            $model = $this->model->find($id);
            $model->delete();

            return $model;
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage(), $th->getCode(), $th);
        }
    }

    public function truncate()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->model->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function changeState($id, $estado, $column = 'estado', $with = [])
    {
        $model = $this->model->find($id);
        $model[$column] = $estado;
        $model->save();

        return $model;
    }

    public function changeStateArray($ids, $estado, $column = 'estado', $with = [])
    {
        $model = $this->model->whereIn('id', $ids)->update([
            $column => $estado,
        ]);

        return $model;
    }

    public function pdf($vista, $data = [], $nombre = 'archivo', $is_stream = true, $landspace = false)
    {
        $pdf = \PDF::loadview($vista, compact('data'));
        if ($landspace == true) {
            $pdf->setPaper('legal', 'landscape');
        }
        // dd('pasí');

        $nombre = $nombre . '.pdf';
        if ($is_stream) {
            return $pdf->stream($nombre);
        } else {
            return $pdf->download($nombre);
        }
    }

    public function datos_activos($key = 'estado', $value = '1')
    {
        return $this->model->where($key, $value)->get();
    }

    public function clearNull($array)
    {
        foreach ($array as $clave => $valor) {
            // if ($valor === 'null' || $valor === "undefined" || $valor === 0 || $valor === "0") {
            if ($valor === 'null' || $valor === "undefined") {
                $array[$clave] = null; // Asignar null en lugar de 'null'
            }
        }

        return $array;
    }

    public function setDatabaseConnection($connectionName = null)
    {
        if ($connectionName === null) {
            // Si no se proporciona ningún nombre de conexión, se usa la conexión actual
            $connectionName = $this->model->getConnection()->getName();
        }

        $this->model->setConnection($connectionName);

        // Retornar $this permite encadenar métodos si es necesario
        return [
            $this->model->getConnection()->getName(),
            $this->model->getConnection()->getDatabaseName(),
        ];
    }

}
