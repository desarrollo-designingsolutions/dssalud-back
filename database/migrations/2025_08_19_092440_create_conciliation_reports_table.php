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
        Schema::create('conciliation_reports', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("company_id")->constrained();
            $table->foreignUuid("reconciliation_group_id")->constrained("reconciliation_groups");
            $table->date("dateConciliation");
            $table->string("nameIPSrepresentative")->nullable();
            $table->string("positionIPSrepresentative")->nullable();


            // Nuevas columnas relacionadas con users y cargos
            $table->foreignUuid("elaborator_id")->nullable()->constrained("users"); // Elaboró (Conciliador)
            $table->string("elaborator_position")->nullable(); // Cargo del Conciliador

            $table->foreignUuid("reviewer_id")->nullable()->constrained("users"); // Revisó (Líder de glosas y conciliaciones)
            $table->string("reviewer_position")->nullable(); // Cargo del Líder

            $table->foreignUuid("approver_id")->nullable()->constrained("users"); // Aprobó (Coordinador de glosas y Conciliaciones)
            $table->string("approver_position")->nullable(); // Cargo del Coordinador

            $table->foreignUuid("legal_representative_id")->nullable()->constrained("users"); // Aprobó (Representante Legal / Director Nacional de Cuentas Médicas)
            $table->string("legal_representative_position")->nullable(); // Cargo del Representante Legal

            $table->foreignUuid("health_audit_director_id")->nullable()->constrained("users"); // Revisión por Director de auditoría en Salud
            $table->string("health_audit_director_position")->nullable(); // Cargo del Director de auditoría

            $table->foreignUuid("vp_planning_control_id")->nullable()->constrained("users"); // Revisión por Vicepresidencia de Planeación y Control Financiero
            $table->string("vp_planning_control_position")->nullable(); // Cargo de la Vicepresidencia

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliation_reports');
    }
};
