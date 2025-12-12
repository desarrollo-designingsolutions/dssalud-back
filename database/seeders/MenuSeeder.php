<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $arrayData = [
            [
                'id' => 1,
                'order' => 10,
                'title' => 'Inicio',
                'to' => 'Home',
                'icon' => 'tabler-home',
                'requiredPermission' => 'menu.home',
            ],
            [
                'id' => 2,
                'order' => 20,
                'title' => 'Compañias',
                'to' => 'Company-List',
                'icon' => ' tabler-building',
                'requiredPermission' => 'company.list',
            ],
            [
                'id' => 3,
                'order' => 30,
                'title' => 'Usuarios',
                'icon' => 'tabler-user-shield',
                'requiredPermission' => 'menu.user.father',
            ],
            [
                'id' => 4,
                'order' => 40,
                'title' => 'Usuarios',
                'to' => 'User-List',
                'icon' => '',
                'father' => 3,
                'requiredPermission' => 'menu.user',
            ],
            [
                'id' => 5,
                'order' => 50,
                'title' => 'Roles',
                'to' => 'Role-List',
                'icon' => '',
                'father' => 3,
                'requiredPermission' => 'menu.role',
            ],

            [
                'id' => 6,
                'order' => 60,
                'title' => 'Radicación',
                'to' => 'Filing-New-Index',
                'icon' => 'tabler-file-zip',
                'requiredPermission' => 'filing.new.index',
            ],
            // [//NOTA:GERMAN mando a quitarlo (21-04-2025 JCMG)
            //     'id' => 7,
            //     'order' => 70,
            //     'title' => 'Nueva Radicación',
            //     'to' => 'Filing-New-Index',
            //     'icon' => 'tabler-file-zip',
            //     'requiredPermission' => 'filing.new.index',
            //     'father' => 6,
            // ],
            [
                'id' => 8,
                'order' => 80,
                'title' => 'Cuentas médicas',
                'icon' => 'tabler-report-medical',
                'requiredPermission' => 'menu.medical.bills',
            ],
            [
                'id' => 9,
                'order' => 90,
                'title' => 'Asignación',
                'to' => 'AssignmentBatche-List',
                'icon' => 'tabler-report-medical',
                'requiredPermission' => 'assignmentBatche.list',
                'father' => 8,
            ],
            [
                'id' => 10,
                'order' => 100,
                'title' => 'Auditoria',
                'to' => 'InvoiceAuditAssignmentBatche-List',
                'icon' => 'tabler-report-medical',
                'requiredPermission' => 'invoiceAuditAssignmentBatche.list',
                'father' => 8,
            ],
            [
                'id' => 11,
                'order' => 110,
                'title' => 'Calendario Eventos',
                'to' => 'Schedule-Index',
                'icon' => 'tabler-calendar-event',
                'father' => null,
                'requiredPermission' => 'schedule.menu',
            ],
            [
                'id' => 12,
                'order' => 120,
                'title' => 'Grupo de conciliación',
                'to' => 'ReconciliationGroup-List',
                'icon' => 'tabler-device-desktop-dollar',
                'requiredPermission' => 'reconciliationGroup.list',
            ],
            [
                'id' => 13,
                'order' => 130,
                'title' => 'Conciliaciones',
                'to' => 'Conciliation-List',
                'icon' => 'tabler-free-rights',
                'requiredPermission' => 'conciliation.list',
            ],
            [
                'id' => 14,
                'order' => 140,
                'title' => 'Contratos',
                'to' => 'Contract-List',
                'icon' => 'tabler-contract',
                'requiredPermission' => 'contract.list',
            ],

            [
                'id' => 15,
                'order' => 150,
                'title' => 'Salud IA',
                'to' => 'SaludIA-List',
                'icon' => 'tabler-message-chatbot',
                'requiredPermission' => 'saludIA.list',
            ],

            [
                'id' => 16,
                'order' => 160,
                'title' => 'Facturas del prestador',
                'to' => 'ProviderInvoices-List',
                'icon' => 'tabler-file-dollar',
                'requiredPermission' => 'providerInvoices.list',
            ],
        ];

        // Inicializar la barra de progreso
        $this->command->info('Starting Seed Data ...');
        $bar = $this->command->getOutput()->createProgressBar(count($arrayData));

        foreach ($arrayData as $key => $value) {
            $data = Menu::find($value['id']);
            if (!$data) {
                $data = new Menu;
            }
            $data->id = $value['id'];
            $data->order = $value['order'];
            $data->title = $value['title'];
            $data->to = $value['to'] ?? null;
            $data->icon = $value['icon'];
            $data->father = $value['father'] ?? null;
            $data->requiredPermission = $value['requiredPermission'];
            $data->save();
        }

        $bar->finish(); // Finalizar la barra
    }
}
