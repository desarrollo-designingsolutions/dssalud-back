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
        Schema::create('filings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('user_id')->constrained()->comment('Usuario que subio el zip');

            $table->foreignUuid('contract_id')->nullable()->constrained();

            $table->string('path_zip')->nullable()->comment('ruta del archivo zip');
            $table->string('path_json')->nullable()->comment('ruta del archivo json');

            $table->json('validationZip')->nullable()->comment('errores de validacion Zip');
            $table->json('validationTxt')->nullable()->comment('errores de validacion TXT');

            $table->string('type');
            $table->string('status');
            $table->string('sumVr')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filings');
    }
};
