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
        Schema::create('hospitalizations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('viaIngresoServicioSalud_id')->nullable()->constrained('via_ingreso_usuarios');
            $table->string('fechaInicioAtencion')->nullable();
            $table->string('numAutorizacion')->nullable();
            $table->foreignUuid('causaMotivoAtencion_id')->nullable()->constrained('rips_causa_externa_version2s');
            $table->foreignUuid('codDiagnosticoPrincipal_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoPrincipalE_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionadoE1_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionadoE2_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionadoE3_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codComplicacion_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('condicionDestinoUsuarioEgreso_id')->nullable()->constrained('condiciony_destino_usuario_egresos');
            $table->foreignUuid('codDiagnosticoCausaMuerte_id')->nullable()->constrained('cie10s');
            $table->string('fechaEgreso')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospitalizations');
    }
};
