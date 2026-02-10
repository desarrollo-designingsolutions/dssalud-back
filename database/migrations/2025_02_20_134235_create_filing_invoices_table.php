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
        Schema::create('filing_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('filing_id')->constrained();
            $table->string('invoice_number')->comment('Columna del txt (numFactura), archivo AF');
            $table->string('case_number')->unique()->comment('Número de radicado unico en todo el sistema');

            $table->string('status');
            $table->string('status_xml');

            $table->dateTime('date')->comment('fecha de preradicado y fecha de radicado');

            $table->string('sumVr')->default(0);

            $table->string('users_count')->default(0)->comment('cantidad de usaurios en la factura');

            $table->string('path_json')->nullable()->comment('ruta del archivo json');
            $table->string('path_xml')->nullable()->comment('ruta del archivo xml');

            $table->json('validationXml')->nullable()->comment('errores de validacion XML');
            $table->json('validationTxt')->nullable()->comment('errores de validacion TXT de la factura');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filing_invoices');
    }
};
