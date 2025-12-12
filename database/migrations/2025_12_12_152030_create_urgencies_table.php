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
        Schema::create('urgencies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('fechaInicioAtencion')->nullable();
            $table->foreignUuid('causaMotivoAtencion_id')->nullable()->constrained('rips_causa_externa_version2s');
            $table->foreignUuid('codDiagnosticoPrincipal_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoPrincipalE_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionadoE1_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionadoE2_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionadoE3_id')->nullable()->constrained('cie10s');
            $table->string('condicionDestinoUsuarioEgreso')->nullable();
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
        Schema::dropIfExists('urgencies');
    }
};
