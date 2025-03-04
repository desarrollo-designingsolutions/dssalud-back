<?php

namespace Database\Seeders;

use App\Models\TypeCodeGlosa;
use Illuminate\Database\Seeder;

class TypeCodeGlosaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TypeCodeGlosa::create(['id' => 1, 'type_code' => '3047', 'name' => 'RESOLUCION 3047']);
    }
}
