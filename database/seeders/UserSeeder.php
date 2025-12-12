<?php

namespace Database\Seeders;

use App\Helpers\Constants;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ESTE USUARIO SUPER ADMINISTRADOR NO VA A TENER COMPAÑIA
        $arrayData = [
            [
                'name' => 'SuperAdmin',
                'surname' => 'SuperAdmin',
                'email' => 'superadmin@admin.com',
                'role_id' => Constants::ROLE_SUPERADMIN_UUID,
            ],
        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $value) {
            $data = new User;
            $data->name = $value['name'];
            $data->surname = $value['surname'];
            $data->email = $value['email'];
            $data->password = 123456789;
            $data->role_id = $value['role_id'];
            $data->first_time = 0;
            $data->save();

            $role = Role::find($value['role_id']);
            if ($role) {
                $data->syncRoles($role);
            }
        }

        $bar->finish(); // Finalizar la barra
    }
}
