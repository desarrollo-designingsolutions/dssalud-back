<?php

namespace Database\Seeders;

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
                'code' => '001',
                'company_id' =>Constants::COMPANY_UUID,
                'name' => 'Facturas',
            ],
            [
                'code' => '002',
                'company_id' =>Constants::COMPANY_UUID,
                'name' => 'Historia clinica',
            ],

        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $key => $value) {
            $data = new SupportType();
            $data->code = $value['code'];
            $data->company_id = $value['company_id'];
            $data->name = $value['name'];
            $data->save();
        }

        $bar->finish(); // Finalizar la barra
    }
}
