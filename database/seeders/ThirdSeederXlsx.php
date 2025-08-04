<?php

namespace Database\Seeders;

use App\Imports\AssignmentBatchesImport;
use App\Imports\AssignmentsImport;
use App\Imports\FileImport;
use App\Imports\GlosasImport;
use App\Imports\InvoiceAuditsImport;
use App\Imports\PatientsImport;
use App\Imports\RoleImport;
use App\Imports\ServicesImport;
use App\Imports\SupportTypeImport;
use App\Imports\ThirdsImport;
use App\Imports\UserImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ThirdSeederXlsx extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // $filePath = public_path('seeders/thirds.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new ThirdsImport, $filePath);

        // $filePath = public_path('seeders\assignment_batches.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new AssignmentBatchesImport, $filePath);

        // $filePath = public_path('seeders\invoice_audits.csv');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new InvoiceAuditsImport, $filePath);

        // ROLES
        // $filePath = public_path('seeders\roles.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new RoleImport, $filePath);

        // USERS
        // $filePath = public_path('seeders\users.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new UserImport, $filePath);

        // $filePath = public_path('seeders\assignments.csv');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new AssignmentsImport, $filePath);

        // $filePath = public_path('seeders\patients.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new PatientsImport, $filePath);

        // $filePath = public_path('seeders\services.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new ServicesImport, $filePath);

        // $filePath = public_path('seeders\glosas.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new GlosasImport, $filePath);

        // //support_types
        // $filePath = public_path('seeders\support_types.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new SupportTypeImport, $filePath);

        // //FILES
        // $filePath = public_path('seeders\files.xlsx');

        // // Depuración: Verifica si el archivo existe
        // if (! file_exists($filePath)) {
        //     throw new \Exception('Archivo no encontrado en: '.$filePath);
        // }

        // Excel::import(new FileImport, $filePath);
    }
}
