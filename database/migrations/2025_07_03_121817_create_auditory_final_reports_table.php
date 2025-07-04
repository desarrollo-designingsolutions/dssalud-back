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
        Schema::create('auditory_final_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('factura_id')->constrained('invoice_audits');
            $table->foreignUuid('servicio_id')->constrained('services');
            $table->string('origin')->nullable();
            $table->char('nit', 36)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('numero_factura')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('modalidad')->nullable();
            $table->string('regimen')->nullable();
            $table->string('cobertura')->nullable();
            $table->string('contrato')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('numero_documento')->nullable();
            $table->string('primer_nombre')->nullable();
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido')->nullable();
            $table->string('segundo_apellido')->nullable();
            $table->string('genero', 50)->nullable();
            $table->string('codigo_servicio')->nullable();
            $table->string('descripcion_servicio')->nullable();
            $table->integer('cantidad_servicio')->nullable();
            $table->decimal('valor_unitario_servicio', 15, 2)->nullable();
            $table->decimal('valor_total_servicio', 15, 2)->nullable();
            $table->text('codigos_glosa')->nullable();
            $table->longText('observaciones_glosas')->nullable();
            $table->decimal('valor_glosa', 15, 2)->nullable();
            $table->decimal('valor_aprobado', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditory_final_reports');
    }
};
