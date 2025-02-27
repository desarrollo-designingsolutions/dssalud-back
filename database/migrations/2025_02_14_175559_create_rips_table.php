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
        Schema::create('rips', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('user_id')->constrained()->comment("Usuario que subio el zip");
            $table->bigInteger('numInvoices');
            $table->integer('successfulInvoices')->default(0);
            $table->integer('failedInvoices')->default(0);
            $table->string('sumVr')->default(0);

            $table->boolean('send')->default(0);
            $table->string('status');

            $table->text('path_zip')->nullable()->comment("ruta del archivo zip");
            $table->string('path_json')->nullable()->comment("ruta del archivo json");
            $table->string('path_xls')->nullable()->comment("ruta del archivo excel");

            $table->json('validationZip')->nullable()->comment("errores de validacion Zip");
            $table->json('validationTxt')->nullable()->comment("errores de validacion TXT");
            $table->json('validationExcel')->nullable()->comment("errores de validacion EXCEL");

            $table->bigInteger('numeration');
            $table->string("type");
            $table->string("nit")->nullable();




            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rips');
    }
};
