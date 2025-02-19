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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid('company_id')->constrained('companies');
            $table->foreignUuid('rip_id')->constrained('rips');
            $table->string('num_invoice');
            $table->string('path_json')->nullable();
            $table->string('path_excel')->nullable();
            $table->string('path_xml')->nullable();
            $table->json('validationXml')->nullable();
            $table->string('xml_status');
            $table->string('status');
            $table->integer('sumVr')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
