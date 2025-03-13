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
        Schema::create('cie10s', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('habilitado')->nullable();
            $table->string('aplicacion')->nullable();
            $table->string('isStandardGEL')->nullable();
            $table->string('isStandardMSPS')->nullable();
            $table->string('extra_I')->nullable();
            $table->string('extra_II')->nullable();
            $table->string('extra_III')->nullable();
            $table->string('extra_IV')->nullable();
            $table->string('extra_V')->nullable();
            $table->string('extra_VI')->nullable();
            $table->string('extra_VII')->nullable();
            $table->string('extra_VIII')->nullable();
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
        Schema::dropIfExists('cie10s');
    }
};
