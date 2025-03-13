<?php

namespace Database\Seeders;

use App\Helpers\Constants;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $permissionAll = Permission::all();

        $arrayData = [
            [
                'id' => Constants::ROLE_SUPERADMIN_UUID,
                'name' => 'Super Administrador',
                'description' => 'Super Administrador',
                'viewable' => 0,
            ],
        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $key => $value) {
            $model = new Role;
            $model->id = $value['id'];
            $model->name = $value['name'];
            $model->guard_name = 'api';
            $model->description = $value['description'];
            $model->viewable = $value['viewable'];

            $model->save();

            // Asignar todos los permisos al primer rol
            $model->givePermissionTo($permissionAll);
        }

        $bar->finish(); // Finalizar la barra

    }
}
