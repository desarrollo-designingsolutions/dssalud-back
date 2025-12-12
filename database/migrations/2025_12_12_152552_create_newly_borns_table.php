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
        Schema::create('newly_borns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('fechaNacimiento')->nullable();
            $table->string('edadGestacional')->nullable();
            $table->string('numConsultasCPrenatal')->nullable();
            $table->foreignUuid('codSexoBiologico_id')->nullable()->constrained('sexos');
            $table->string('peso')->nullable();
            $table->foreignUuid('codDiagnosticoPrincipal_id')->nullable()->constrained('cie10s');
            $table->foreignUuid('condicionDestinoUsuarioEgreso_id')->nullable()->constrained('condiciony_destino_usuario_egresos');
            $table->foreignUuid('codDiagnosticoCausaMuerte_id')->nullable()->constrained('cie10s');
            $table->string('fechaEgreso')->nullable();

            $table->foreignUuid('tipoDocumentoIdentificacion_id')->nullable()->constrained('tipo_id_pisis');
            $table->string('numDocumentoIdentificacion')->nullable();
            $table->string('codPrestador')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newly_borns');
    }
};
