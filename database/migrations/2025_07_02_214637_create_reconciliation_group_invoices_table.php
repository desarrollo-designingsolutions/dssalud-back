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
        Schema::create('reconciliation_group_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reconciliation_group_id')->constrained();
            $table->foreignUuid('invoice_audit_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_group_invoices');
    }
};
