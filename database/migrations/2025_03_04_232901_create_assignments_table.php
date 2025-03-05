<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid("id")->primary();

            $table->foreignUuid('assignment_batch_id')->constrained();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('invoice_audit_id')->constrained();


            $table->string('phase', 15)->nullable();
            $table->string('status', 15)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assignments');
    }
};
