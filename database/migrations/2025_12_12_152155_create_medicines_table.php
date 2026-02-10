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
        Schema::create('medicines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('numAutorizacion')->nullable();
            $table->string('idMIPRES')->nullable();
            $table->string('fechaDispensAdmon')->nullable();
            $table->foreignUuid('codDiagnosticoPrincipal_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('codDiagnosticoRelacionado_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('tipoMedicamento_id')->nullable()->constrained('tipo_medicamento_pos_version2s');
            $table->string('codTecnologiaSalud')->nullable();
            $table->string('nomTecnologiaSalud')->nullable();
            $table->string('concentracionMedicamento')->nullable();
            $table->foreignUuid('unidadMedida_id')->nullable()->constrained('umms');
            $table->string('formaFarmaceutica')->nullable();
            $table->string('unidadMinDispensa')->nullable();
            $table->string('cantidadMedicamento')->nullable();
            $table->string('diasTratamiento')->nullable();
            $table->string('vrUnitMedicamento')->nullable();
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
        Schema::dropIfExists('medicines');
    }
};
