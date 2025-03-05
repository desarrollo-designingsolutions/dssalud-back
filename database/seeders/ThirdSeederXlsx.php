<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ThirdsImport;

class ThirdSeederXlsx extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $filePath = public_path('seeders/thirds.xlsx');

        // Depuración: Verifica si el archivo existe
        if (!file_exists($filePath)) {
            throw new \Exception("Archivo no encontrado en: " . $filePath);
        }

        Excel::import(new ThirdsImport, $filePath);
    }
}
