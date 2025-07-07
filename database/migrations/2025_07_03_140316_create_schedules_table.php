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
            $table->string('title')->nullable();
            $table->string('type_event')->nullable();
            $table->date('start_date')->nullable();
            $table->string('start_hour')->nullable();
            $table->date('end_date')->nullable();
            $table->string('end_hour')->nullable();
            $table->boolean('all_day')->default(false);
            $table->json('emails')->nullable();
            $table->text('description')->nullable();
            $table->string('scheduleable_type')->nullable();
            $table->string('scheduleable_id')->nullable();
            $table->foreignUuid('rescheduled_from_id')->nullable()->constrained('schedules');

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
