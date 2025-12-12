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
        Schema::create('conciliation_change_statuses', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("company_id")->constrained();
            $table->foreignUuid("user_id")->constrained();
            $table->foreignUuid("reconciliation_group_id")->constrained("reconciliation_groups");
            $table->string("status");
            $table->string("reason");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliation_change_statuses');
    }
};
