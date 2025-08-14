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
        Schema::table('conciliation_invoices', function (Blueprint $table) {
            $table->decimal("sum_accepted_value_ips",15,2)->nullable();
            $table->decimal("sum_accepted_value_eps",15,2)->nullable();
            $table->decimal("sum_eps_ratified_value",15,2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conciliation_invoices', function (Blueprint $table) {
            $table->dropColumn("sum_accepted_value_ips");
            $table->dropColumn("sum_accepted_value_eps");
            $table->dropColumn("sum_eps_ratified_value");
        });
    }
};
