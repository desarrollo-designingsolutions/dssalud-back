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
        Schema::table('services', function (Blueprint $table) {
            $table->string('type')->nullable()->after('patient_id');
            $table->string('serviceable_type')->nullable()->after('type');
            $table->string('serviceable_id')->nullable()->after('serviceable_type');
            $table->integer('consecutivo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['type', 'serviceable_type', 'serviceable_id', 'consecutivo']);
        });
    }
};
