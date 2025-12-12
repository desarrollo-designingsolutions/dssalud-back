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
        Schema::create('third_departments', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("third_id")->constrained();
            $table->string("municipio")->nullable();
            $table->string("departamento")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('third_departments');
    }
};
