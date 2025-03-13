<?php

namespace Database\Seeders;

use App\Helpers\Constants;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $arrayData = [
            [
                'id' => Constants::COMPANY_UUID,
                'name' => 'Company Central',
                'nit' => '123',
                'email' => 'companyCenter@gmail.com',
                'phone' => '0000',
                'address' => 'xxx',
                'country_id' => Constants::COUNTRY_ID,
                // 'logo' => 1,
                'final_date' => '2080-01-01',
            ],
        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $value) {
            $data = new Company;
            $data->id = $value['id'];
            $data->name = $value['name'];
            $data->nit = $value['nit'];
            $data->email = $value['email'];
            $data->phone = $value['phone'];
            $data->address = $value['address'];
            $data->country_id = $value['country_id'];
            // $data->logo = $value['logo'];
            $data->final_date = $value['final_date'];
            $data->save();
        }

        $bar->finish(); // Finalizar la barra

    }
}
