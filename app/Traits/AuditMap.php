<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait AuditMap
{
    public function applyColumnMappingToAudits(&$audits)
    {
        foreach ($audits as $audit) {
            // Obtener el modelo asociado al audit
            $modelClass = $audit->auditable_type; // Esto devuelve el nombre de la clase del modelo
            $model = app($modelClass); // Resolver la instancia del modelo

            // Verificar si el modelo tiene la configuración de columnas
            if (method_exists($model, 'getColumnsConfig')) {
                $columns = $model->getColumnsConfig();

                // Aplicar el mapeo de columnas a los valores antiguos y nuevos
                $audit->old_values = $this->applyColumnMapping($audit->old_values, $columns);
                $audit->new_values = $this->applyColumnMapping($audit->new_values, $columns);
            }

            // Verificar si el modelo tiene la configuración de descripción para las accciones
            if (method_exists($model, 'getActionDescription')) {
                // Aplicar el mapeo de columnas a los valores antiguos y nuevos
                $audit->action = $model->getActionDescription($audit->event);
            }
        }
    }

    protected function applyColumnMapping($modelOld, $columns)
    {
        if ($modelOld && $columns) {
            // Reemplazar las claves en $modelOld usando los valores de $columns
            foreach ($modelOld as $key => $value) {
                // Si la clave de $modelOld existe en $columns, la reemplazamos por su valor
                if (array_key_exists($key, $columns)) {
                    // Obtenemos el 'label' como la nueva clave
                    $newKey = $columns[$key]['label'];
                    $modelOld[$newKey] = $value;

                    // Verificamos si existe un 'function' y usamos ese function, si no, mantenemos el valor original
                    if (isset($columns[$key]['function'])) {
                        // Aplicamos el 'function' al valor si existe
                        $modelOld[$newKey] = $columns[$key]['function']($value);
                    }
                    if (isset($columns[$key]['type'])) {
                        if ($columns[$key]['type'] == 'date') {
                            $modelOld[$newKey] = Carbon::parse($value)->format($columns[$key]['format']);
                        }
                    }
                    if (isset($columns[$key]['table']) && isset($columns[$key]['table_field'])) {

                        $registro = DB::table($columns[$key]['table'])->find($modelOld[$key]);

                        // Comprobar que se ha encontrado el registro
                        if ($registro) {
                            $field = $columns[$key]['table_field'];
                            $modelOld[$newKey] = $registro->$field;
                        }
                    }

                    if (isset($columns[$key]['model']) && isset($columns[$key]['model_field'])) {
                        // Instanciación del modelo
                        $modelClass = "App\Models\\".$columns[$key]['model'];
                        $registro = $modelClass::find($modelOld[$key]);

                        // Comprobar que se ha encontrado el registro
                        if ($registro) {
                            // Acceder al campo dinámicamente
                            $field = $columns[$key]['model_field'];

                            // Comprobar si el campo existe en el modelo
                            $modelOld[$newKey] = $registro->$field;
                        }
                    }

                    // Eliminamos la clave original
                    unset($modelOld[$key]);
                } else {
                    // Si la clave no está en $columns, la eliminamos de $modelOld
                    unset($modelOld[$key]);
                }
            }
        }

        return $modelOld;
    }
}
