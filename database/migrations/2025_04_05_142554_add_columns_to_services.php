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
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('value_glosa', 15, 2)->nullable()->comment('Valor Glosa es la sumatoria de los valores de las glosas del servicio');
            $table->decimal('value_approved', 15, 2)->nullable()->comment('Valor aprobado resta entre el valor de la glosa y el total del servicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('value_glosa');
            $table->dropColumn('value_approved');

        });
    }
};
