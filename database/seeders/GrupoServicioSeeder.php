<?php

namespace Database\Seeders;

use App\Models\GrupoServicio;
use Illuminate\Database\Seeder;

class GrupoServicioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $dataArray = [
            ['id' => '1', 'codigo' => '01', 'nombre' => 'Consulta externa', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => null, 'extra_II' => null, 'extra_III' => null, 'extra_IV' => null, 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2022-06-16 04:04:43 PM', 'isPublicPrivate' => null],
            ['id' => '2', 'codigo' => '02', 'nombre' => 'Apoyo diagnóstico y complementación  terapéutica', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => null, 'extra_II' => null, 'extra_III' => null, 'extra_IV' => null, 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2022-06-16 04:04:43 PM', 'isPublicPrivate' => null],
            ['id' => '3', 'codigo' => '03', 'nombre' => 'Internación', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => null, 'extra_II' => null, 'extra_III' => null, 'extra_IV' => null, 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2022-06-16 04:04:43 PM', 'isPublicPrivate' => null],
            ['id' => '4', 'codigo' => '04', 'nombre' => 'Quirúrgico', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => null, 'extra_II' => null, 'extra_III' => null, 'extra_IV' => null, 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2022-06-16 04:04:43 PM', 'isPublicPrivate' => null],
            ['id' => '5', 'codigo' => '05', 'nombre' => 'Atención inmediata', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => null, 'extra_II' => null, 'extra_III' => null, 'extra_IV' => null, 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2022-06-16 04:04:43 PM', 'isPublicPrivate' => null],
        ];

        foreach ($dataArray as $key => $value) {
            $data = new GrupoServicio;
            $data->codigo = $value['codigo'];
            $data->nombre = $value['nombre'];
            $data->descripcion = $value['descripcion'];
            $data->habilitado = $value['habilitado'];
            $data->aplicacion = $value['aplicacion'];
            $data->isStandardGEL = $value['isStandardGEL'];
            $data->isStandardMSPS = $value['isStandardMSPS'];
            $data->extra_I = $value['extra_I'];
            $data->extra_II = $value['extra_II'];
            $data->extra_III = $value['extra_III'];
            $data->extra_IV = $value['extra_IV'];
            $data->extra_V = $value['extra_V'];
            $data->extra_VI = $value['extra_VI'];
            $data->extra_VII = $value['extra_VII'];
            $data->extra_VIII = $value['extra_VIII'];
            $data->extra_IX = $value['extra_IX'];
            $data->extra_X = $value['extra_X'];
            $data->valorRegistro = $value['valorRegistro'];
            $data->usuarioResponsable = $value['usuarioResponsable'];
            $data->fecha_actualizacion = $value['fecha_actualizacion'];
            $data->isPublicPrivate = $value['isPublicPrivate'];

            $data->save();
        }
    }
}
