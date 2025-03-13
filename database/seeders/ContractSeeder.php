<?php

namespace Database\Seeders;

use App\Helpers\Constants;
use App\Models\Contract;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $arrayData = [
            [
                'name' => 'Contrato 01',
            ],
            [
                'name' => 'Contrato 02',
            ],
        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $key => $value) {
            $data = new Contract;
            $data->name = $value['name'];
            $data->company_id = Constants::COMPANY_UUID;
            $data->save();
        }

        $bar->finish(); // Finalizar la barra
    }
}
