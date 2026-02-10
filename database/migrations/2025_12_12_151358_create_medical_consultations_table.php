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
        Schema::create('medical_consultations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('fechaInicioAtencion')->nullable();
            $table->string('numAutorizacion')->nullable();
            $table->foreignUuid('codConsulta_id')->nullable()->constrained('cups_rips');
            $table->foreignUuid('modalidadGrupoServicioTecSal_id')->nullable()->constrained('modalidad_atencions');
            $table->foreignUuid('grupoServicios_id')->nullable()->constrained('grupo_servicios');
            $table->foreignUuid('codServicio_id')->nullable()->constrained('servicios');
            $table->foreignUuid('finalidadTecnologiaSalud_id')->nullable()->constrained('rips_finalidad_consulta_version2s');
            $table->foreignUuid('causaMotivoAtencion_id')->nullable()->constrained('rips_causa_externa_version2s');
            $table->foreignUuid('codDiagnosticoPrincipal_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionado1_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionado2_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionado3_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('tipoDiagnosticoPrincipal_id')->nullable()->constrained('rips_tipo_diagnostico_principal_version2s');
            $table->string('valorPagoModerador')->nullable();
            $table->string('vrServicio')->nullable();
            $table->foreignUuid('conceptoRecaudo_id')->nullable()->constrained('concepto_recaudos');

            $table->foreignUuid('tipoDocumentoIdentificacion_id')->nullable()->constrained('tipo_id_pisis');
            $table->string('numDocumentoIdentificacion')->nullable();
            $table->string('codPrestador')->nullable();
            $table->string('numFEVPagoModerador')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_consultations');
    }
};
