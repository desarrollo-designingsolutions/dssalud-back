<?php

namespace Database\Seeders;

use App\Helpers\Constants;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Datos para insertar o actualizar
        $arrayData = [
            [
                'id' => 1,
                'name' => 'menu.home',
                'description' => 'Visualizar Menú Inicio',
                'menu_id' => 1,
            ],
            [
                'id' => 2,
                'name' => 'company.list',
                'description' => 'Visualizar Módulo de Compañia',
                'menu_id' => 2,
            ],
            [
                'id' => 3,
                'name' => 'menu.user.father',
                'description' => 'Visualizar Menú Acceso de usuarios',
                'menu_id' => 3,
            ],
            [
                'id' => 4,
                'name' => 'menu.user',
                'description' => 'Visualizar Menú Usuarios',
                'menu_id' => 4,
            ],
            [
                'id' => 5,
                'name' => 'menu.role',
                'description' => 'Visualizar Menú Roles',
                'menu_id' => 5,
            ],
            [
                'id' => 6,
                'name' => 'filing.new.index',
                'description' => 'Visualizar Módulo Radicación',
                'menu_id' => 6,
            ],
            // [
            //     'id' => 7,
            //     'name' => 'filing.new.index',
            //     'description' => 'Visualizar Módulo Radicación',
            //     'menu_id' => 7,
            // ],
            [
                'id' => 8,
                'name' => 'menu.medical.bills',
                'description' => 'Visualizar Cuentas médicas',
                'menu_id' => 8,
            ],
            [
                'id' => 9,
                'name' => 'assignmentBatche.list',
                'description' => 'Visualizar Menu de asignación',
                'menu_id' => 9,
            ],
            [
                'id' => 10,
                'name' => 'invoiceAuditAssignmentBatche.list',
                'description' => 'Visualizar Menu de auditoria',
                'menu_id' => 10,
            ],
            [
                'id' => 11,
                'name' => 'schedule.menu',
                'description' => 'Visualizar Menú Eventos',
                'menu_id' => 11,
            ],
            [
                'id' => 12,
                'name' => 'reconciliationGroup.list',
                'description' => 'Visualizar Menu Grupo de conciliación',
                'menu_id' => 12,
            ],
            [
                'id' => 13,
                'name' => 'conciliation.list',
                'description' => 'Visualizar Menu Conciliación',
                'menu_id' => 13,
            ],
            [
                'id' => 14,
                'name' => 'contract.list',
                'description' => 'Visualizar Menu Contratos',
                'menu_id' => 14,
            ],
            [
                'id' => 15,
                'name' => 'saludIA.list',
                'description' => 'Visualizar Menu Salud IA',
                'menu_id' => 15,
            ],
            [
                'id' => 16,
                'name' => 'providerInvoices.list',
                'description' => 'Visualizar Menu Facturas del prestador',
                'menu_id' => 16,
            ],
        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $key => $value) {
            $data = Permission::find($value['id']);
            if (!$data) {
                $data = new Permission;
            }
            $data->id = $value['id'];
            $data->name = $value['name'];
            $data->description = $value['description'];
            $data->menu_id = $value['menu_id'];
            $data->guard_name = 'api';
            $data->save();
        }

        // Obtener permisos
        $permissions = Permission::whereIn('id', collect($arrayData)->pluck('id'))->get();

        // Asignar permisos al rol
        $role = Role::find(Constants::ROLE_SUPERADMIN_UUID);
        if ($role) {
            $role->syncPermissions($permissions);
        }

        // Sincronizar roles con usuarios
        $users = User::get();
        foreach ($users as $user) {
            $role = Role::find($user->role_id);
            if ($role) {
                $user->syncRoles($role);
            }
        }

        $bar->finish(); // Finalizar la barra

    }
}
