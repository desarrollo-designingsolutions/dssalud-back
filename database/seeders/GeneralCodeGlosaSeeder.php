<?php

namespace Database\Seeders;

use App\Models\GeneralCodeGlosa;
use Illuminate\Database\Seeder;

class GeneralCodeGlosaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        GeneralCodeGlosa::insert([
            ['id' => 1, 'type_code_glosa_id' => 1, 'general_code' => '1', 'description' => 'facturación', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'type_code_glosa_id' => 1, 'general_code' => '2', 'description' => 'tarifas', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'type_code_glosa_id' => 1, 'general_code' => '3', 'description' => 'soportes', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'type_code_glosa_id' => 1, 'general_code' => '4', 'description' => 'autorizaciones', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'type_code_glosa_id' => 1, 'general_code' => '5', 'description' => 'cobertura', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'type_code_glosa_id' => 1, 'general_code' => '6', 'description' => 'pertinencia', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'type_code_glosa_id' => 1, 'general_code' => '8', 'description' => 'devoluciones', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'type_code_glosa_id' => 1, 'general_code' => '9', 'description' => 'respuestas a glosas o devoluciones', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
