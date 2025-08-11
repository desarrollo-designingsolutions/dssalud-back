<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('process_batches_errors', function (Blueprint $table) {
            $table->uuid('id')->primary(); // ID único para cada error
            $table->uuid('batch_id')->index(); // ID del batch al que pertenece el error
            $table->integer('row_number')->nullable(); // Número de fila en Excel (si aplica)
            $table->string('column_name')->nullable(); // Nombre de la columna con error (si aplica)
            $table->text('error_message'); // Mensaje de error
            $table->string('error_type')->nullable(); // Tipo de error (ej. 'validation', 'structure', 'system')
            $table->string('error_value')->nullable(); // valor con el error
            $table->json('original_data')->nullable(); // Datos originales de la fila (JSON)
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_batches_errors');
    }
};
