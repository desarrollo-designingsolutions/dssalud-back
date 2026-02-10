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
        Schema::create('ips_no_reps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo');
            $table->string('nombre');
            $table->string('descripcion');
            $table->string('habilitado');
            $table->string('aplicacion');
            $table->string('isStandardGEL');
            $table->string('isStandardMSPS');
            $table->string('telefono');
            $table->string('gerente');
            $table->string('regimen');
            $table->string('codDepartamento');
            $table->string('departamento');
            $table->string('codMunicipio');
            $table->string('municipio');
            $table->string('tipoIPS');
            $table->string('nivelAtencion');
            $table->string('nit');
            $table->string('valorRegistro');
            $table->string('usuarioResponsable');
            $table->string('fecha_actualizacion');
            $table->string('isPublicPrivate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ips_no_reps');
    }
};
