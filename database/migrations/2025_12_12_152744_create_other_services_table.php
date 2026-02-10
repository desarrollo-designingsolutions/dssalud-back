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
        Schema::create('other_services', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('numAutorizacion')->nullable();
            $table->string('idMIPRES')->nullable();
            $table->string('fechaSuministroTecnologia')->nullable();
            $table->foreignUuid('tipoOS_id')->nullable()->constrained('tipo_otros_servicios');
            $table->string('codTecnologiaSalud')->nullable();
            $table->string('nomTecnologiaSalud')->nullable();
            $table->string('cantidadOS')->nullable();
            $table->string('vrUnitOS')->nullable();
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
        Schema::dropIfExists('other_services');
    }
};
