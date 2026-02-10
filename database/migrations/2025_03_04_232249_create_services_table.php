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
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('invoice_audit_id')->constrained();
            $table->foreignUuid('patient_id')->constrained();
            $table->string('detail_code')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('unit_value', 15, 2)->nullable();
            $table->decimal('total_value', 15, 2)->nullable();
            $table->decimal('value_glosa', 15, 2)->nullable()->comment('Valor Glosa es la sumatoria de los valores de las glosas del servicio');
            $table->decimal('value_approved', 15, 2)->nullable()->comment('Valor aprobado resta entre el valor de la glosa y el total del servicio');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
