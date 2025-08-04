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
        Schema::create('process_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_id')->unique();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('user_id')->constrained();

            $table->integer('total_records');
            $table->integer('processed_records');
            $table->integer('error_count')->default(0);
            $table->string('status');
            $table->json('metadata');
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_batches');
    }
};
