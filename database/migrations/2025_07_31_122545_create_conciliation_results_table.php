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
        Schema::create('conciliation_results', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('auditory_final_report_id')->constrained();
            $table->string('response_status');
            $table->string('autorization_number')->nullable();
            $table->decimal('accepted_value_ips', 15, 2);
            $table->decimal('accepted_value_eps', 15, 2);
            $table->decimal('eps_ratified_value', 15, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliation_results');
    }
};
