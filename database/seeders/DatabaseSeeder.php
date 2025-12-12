<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\InvoiceAudit;
use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // paquete para el seeder de paises,estados y ciudades
        // 1. composer require altwaireb/laravel-world
        // 2. php artisan world:install
        // 3. php artisan world:seeder

        $this->call([
            WorldTableSeeder::class,
            MenuSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,

                // ThirdSeederXlsx::class,
            TypeCodeGlosaSeeder::class,
            GeneralCodeGlosaSeeder::class,
            CodeGlosaSeeder::class,

            // TO VALIDATIONS

            Cie10Seeder::class,
            CupsRipsSeeder::class,
            GrupoServicioSeeder::class,

            IpsCodHabilitacionSeeder::class,
            IpsCodHabilitacionSeeder2::class,
            IpsCodHabilitacionSeeder3::class,
            IpsCodHabilitacionSeeder4::class,

            IpsNoRepsSeeder::class,
            LstSiNoSeeder::class,

            MunicipioSeeder::class,
            PaisSeeder::class,
            ServicioSeeder::class,
            TipoMedicamentoPosVersion2Seeder::class,

            ZonaVersion2Seeder::class,

        ]);

        $client = new ClientRepository;

        $client->createPasswordGrantClient(null, 'Laravel Personal Grant Client', 'https://localhost');
        $client->createPersonalAccessClient(null, 'Laravel Password Access Client', 'https://localhost');
    }
}
