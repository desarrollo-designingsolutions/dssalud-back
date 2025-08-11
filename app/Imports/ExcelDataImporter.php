<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class ExcelDataImporter implements ToArray
{
    public $data; // Contendrá un array de arrays, donde el primer elemento es la fila de encabezados

    public function array(array $array)
    {
        $this->data = $array;
    }
}
