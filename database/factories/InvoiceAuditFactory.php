<?php

namespace Database\Factories;

use App\Models\Third;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceAudit>
 */
class InvoiceAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expeditionDate = fake()->dateTimeBetween('-1 year', 'now');
        $dateEntry = Carbon::parse($expeditionDate)->addDays(rand(1, 5));
        $dateDeparture = Carbon::parse($dateEntry)->addDays(rand(1, 10));

        $thirds = Third::inRandomOrder()->first();

        return [
            'third_id' => $thirds->id,
            'invoice_number' => fake()->optional(90)->numerify('INV-#######'), // 10% de nulo
            'total_value' => fake()->randomFloat(2, 100, 10000),
            'origin' => fake()->optional()->company,
            'expedition_date' => fake()->optional(80)->passthrough($expeditionDate), // 20% de nulo
            'date_entry' => $dateEntry,
            'date_departure' => fake()->optional(30)->passthrough($dateDeparture), // 30% de nulo
            'modality' => fake()->optional()->randomElement(['Aérea', 'Marítima', 'Terrestre']),
            'regimen' => fake()->optional()->randomElement(['Importación', 'Exportación', 'Tránsito']),
            'coverage' => fake()->optional()->randomElement(['Nacional', 'Internacional', 'Regional']),
            'contract_number' => fake()->optional(50)->numerify('CONTRACT-####'), // 50% de nulo
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
