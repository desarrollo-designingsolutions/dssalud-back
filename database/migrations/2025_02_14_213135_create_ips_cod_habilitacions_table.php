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
        Schema::create('ips_cod_habilitacions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('habilitado')->nullable();
            $table->string('aplicacion')->nullable();
            $table->string('isStandardGEL')->nullable();
            $table->string('isStandardMSPS')->nullable();
            $table->string('tipoIDPrestador')->nullable();
            $table->string('nroIDPrestador')->nullable();
            $table->string('codigoPrestador')->nullable();
            $table->string('codMpioSede')->nullable();
            $table->string('nombreMpioSede')->nullable();
            $table->string('nombreDptoSede')->nullable();
            $table->string('clasePrestador')->nullable();
            $table->string('nomClasePrestador')->nullable();
            $table->string('extra_IX')->nullable();
            $table->string('extra_X')->nullable();
            $table->string('valorRegistro')->nullable();
            $table->string('usuarioResponsable')->nullable();
            $table->string('fecha_actualizacion')->nullable();
            $table->string('isPublicPrivate')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ips_cod_habilitacions');
    }
};
