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
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('third_id')->constrained();
            $table->json('emails')->nullable();
            $table->string('type_event')->nullable();
            $table->string('title')->nullable();
            $table->date('start_date')->nullable();
            $table->string('start_hour')->nullable();
            $table->date('end_date')->nullable();
            $table->string('end_hour')->nullable();
            $table->string('link')->nullable();
            $table->boolean('all_day')->default(false);
            $table->text('description')->nullable();
            $table->string('response_status')->nullable(); 
            $table->dateTime('response_date')->nullable(); 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
