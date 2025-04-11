<?php

namespace Database\Seeders;

use App\Imports\AssignmentBatchesImport;
use App\Imports\AssignmentsImport;
use App\Imports\GlosasImport;
use App\Imports\InvoiceAuditsImport;
use App\Imports\PatientsImport;
use App\Imports\ServicesImport;
use App\Imports\ThirdsImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ThirdSeederXlsx extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $filePath = public_path('seeders/thirds.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new ThirdsImport, $filePath);

        $filePath = public_path('seeders\assignmentBatche.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new AssignmentBatchesImport, $filePath);

        $filePath = public_path('seeders\invoiceAudit.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new InvoiceAuditsImport, $filePath);

        $filePath = public_path('seeders\assignments.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new AssignmentsImport, $filePath);
        
        $filePath = public_path('seeders\patients.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new PatientsImport, $filePath);
        
        $filePath = public_path('seeders\services.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new ServicesImport, $filePath);
        
        $filePath = public_path('seeders\glosas.xlsx');

        // Depuración: Verifica si el archivo existe
        if (! file_exists($filePath)) {
            throw new \Exception('Archivo no encontrado en: '.$filePath);
        }

        Excel::import(new GlosasImport, $filePath);
    }
}
