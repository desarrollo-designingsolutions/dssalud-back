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
        Schema::table('conciliation_results', function (Blueprint $table) {
            $table->foreignUuid("reconciliation_group_id")->after("invoice_audit_id")->constrained("reconciliation_groups");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conciliation_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId("reconciliation_group_id");
        });
    }
};
