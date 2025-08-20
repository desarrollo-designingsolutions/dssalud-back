<?php

namespace Database\Seeders;

use App\Imports\ThirdDepartmentImportSeeder;
use App\Models\ThirdDepartment;
use App\Services\ExcelService;
use Exception;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Symfony\Component\Console\Helper\ProgressBar;

class ThirdDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('db/thirds_departments.xlsx');

        $excelService = new ExcelService;
        $sheet = null;

        try {
            $sheet = $excelService
                ->getSpreadsheetFromExcel($filePath)
                ->getSheetByName('Table')
                ->toArray();
        } catch (Exception $e) {
            // $this->error('Error al leer el excel');
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            // $this->error('Error al obtener la hoja');
        }

        if ($sheet) {
            // Inicializar la barra de progreso
            $this->command->info('Starting Seed Data ...');
            $bar = $this->command->getOutput()->createProgressBar(count($sheet));

            foreach ($sheet as $dataSheet) {
                  ThirdDepartment::updateOrCreate(
                    ['third_id' => $dataSheet[0]],
                    [
                        'municipio' => $dataSheet[1],
                        'departamento' => $dataSheet[2],
                    ]
                );

                $bar->advance();
            }
            $bar->finish(); // Finalizar la barra
        }
    }
}
