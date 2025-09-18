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
        Schema::create('conciliation_invoices', function (Blueprint $table) {
            $table->foreignUuid('invoice_audit_id')->nullable()->constrained();
            $table->foreignUuid('third_id')->nullable()->constrained();
            $table->string('invoice_number', 255)->nullable();
            $table->string('origin', 255)->nullable();
            $table->integer('total_value_service')->nullable();
            $table->double('glosa_value')->nullable();
            $table->double('approved_value')->nullable();
            $table->string('status', 255)->nullable();
            $table->decimal('sum_accepted_value_ips', 15, 2)->nullable();
            $table->decimal('sum_accepted_value_eps', 15, 2)->nullable();
            $table->decimal('sum_eps_ratified_value', 15, 2)->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliation_invoices');
    }
};
