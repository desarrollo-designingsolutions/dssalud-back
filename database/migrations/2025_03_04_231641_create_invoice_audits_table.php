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
        Schema::create('invoice_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('third_id')->nullable()->constrained();
            $table->string('invoice_number')->nullable();
            $table->decimal('total_value', 15, 2);
            $table->string('origin')->nullable();
            $table->dateTime('expedition_date')->nullable();
            $table->date('date_entry')->nullable();
            $table->date('date_departure')->nullable();
            $table->string('modality')->nullable();
            $table->string('regimen')->nullable();
            $table->string('coverage')->nullable();
            $table->string('contract_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_audits');
    }
};
