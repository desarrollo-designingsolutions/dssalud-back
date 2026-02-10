<?php

namespace Database\Seeders;

use App\Enums\SupportType\SupportTypeModuleEnum;
use App\Helpers\Constants;
use App\Models\SupportType;
use Illuminate\Database\Seeder;

class SupportTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $arrayData = [
            [
                'code' => 'f',
                'company_id' => Constants::COMPANY_UUID,
                'name' => 'Facturas',
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_001->value,
            ],
            [
                'code' => 's',
                'company_id' => Constants::COMPANY_UUID,
                'name' => 'Historia clinica',
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_001->value,
            ],
            [
                'code' => '003',
                'company_id' => Constants::COMPANY_UUID,
                'name' => 'GF-F-16 Acta de conciliación PDF',
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_002->value,

            ],
            [
                'code' => '004',
                'company_id' => Constants::COMPANY_UUID,
                'name' => 'GF-F-16 Acta de conciliación EXCEL',
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_002->value,

            ],
            [
                'code' => '005',
                'company_id' => Constants::COMPANY_UUID,
                'name' => 'Acta de reunión',
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_002->value,

            ],
            [
                'code' => '006',
                'company_id' => Constants::COMPANY_UUID,
                'name' => 'Sabana',
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_002->value,

            ],
            [
                'code' => '007',
                'company_id' => Constants::COMPANY_UUID,
                'module' => SupportTypeModuleEnum::SUPPORT_TYPE_MODULE_002->value,
                'name' => 'Otros',
            ],

        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $key => $value) {
            $data = SupportType::where('code', $value['code'])->first();
            if (! $data) {
                $data = new SupportType;
            }

            $data->code = $value['code'];
            $data->company_id = $value['company_id'];
            $data->name = $value['name'];
            $data->save();
        }

        $bar->finish(); // Finalizar la barra
    }
}
