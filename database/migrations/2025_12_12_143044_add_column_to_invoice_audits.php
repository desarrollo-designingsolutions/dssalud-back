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
        Schema::table('invoice_audits', function (Blueprint $table) {
            $table->foreignUuid('filing_invoice_id')->after('third_id')->nullable()->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('filing_invoice_id');
        });
    }
};
