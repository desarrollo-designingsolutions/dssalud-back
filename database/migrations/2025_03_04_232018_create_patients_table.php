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
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid('invoice_audit_id')->constrained();
            $table->string('type_identification')->nullable();
            $table->string('idetification_number');
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->string('first_suname')->nullable();
            $table->string('second_suname')->nullable();
            $table->string('gender')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
