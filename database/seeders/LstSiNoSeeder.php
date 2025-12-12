<?php

namespace Database\Seeders;

use App\Models\LstSiNo;
use Illuminate\Database\Seeder;

class LstSiNoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $dataArray = [
            ['id' => '1', 'codigo' => 'NO', 'nombre' => 'NO', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => '2', 'extra_II' => 'N', 'extra_III' => '0', 'extra_IV' => '02', 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2021-07-21 12:13:52 PM', 'isPublicPrivate' => 'False'],
            ['id' => '2', 'codigo' => 'SI', 'nombre' => 'SI', 'descripcion' => null, 'habilitado' => 'SI', 'aplicacion' => null, 'isStandardGEL' => 'False', 'isStandardMSPS' => 'False', 'extra_I' => '1', 'extra_II' => 'S', 'extra_III' => '1', 'extra_IV' => '01', 'extra_V' => null, 'extra_VI' => null, 'extra_VII' => null, 'extra_VIII' => null, 'extra_IX' => null, 'extra_X' => null, 'valorRegistro' => null, 'usuarioResponsable' => null, 'fecha_actualizacion' => '2021-07-21 12:14:06 PM', 'isPublicPrivate' => 'False'],
        ];
        foreach ($dataArray as $value) {
            $data = new LstSiNo;
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
