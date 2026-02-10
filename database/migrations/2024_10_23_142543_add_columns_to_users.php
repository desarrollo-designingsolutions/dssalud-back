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
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->after('name');
            $table->foreignUuid('company_id')->nullable()->constrained('companies');
            $table->foreignUuid('role_id')->constrained('roles');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('surname');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('is_active');
            $table->dropSoftDeletes();
        });
    }
};
