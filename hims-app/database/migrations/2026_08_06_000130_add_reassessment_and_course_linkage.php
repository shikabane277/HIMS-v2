<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-type reassessment rules (e.g. "BLS every 24 months").
        Schema::create('credential_types', function (Blueprint $table) {
            $table->char('type_id', 36)->primary();
            $table->string('type_name', 100)->unique();
            $table->text('description')->nullable();
            $table->integer('reassessment_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Competency-level reassessment interval (overrides the category default).
        Schema::table('competencies', function (Blueprint $table) {
            $table->integer('reassessment_months')->nullable()->after('is_mandatory');
        });

        // Which courses remediate which competencies.
        Schema::create('course_competencies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('course_id', 36);
            $table->char('competency_id', 36);
            $table->unique(['course_id', 'competency_id']);
            $table->foreign('course_id')->references('course_id')->on('courses')->cascadeOnDelete();
            $table->foreign('competency_id')->references('competency_id')->on('competencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_competencies');
        Schema::table('competencies', function (Blueprint $table) {
            $table->dropColumn('reassessment_months');
        });
        Schema::dropIfExists('credential_types');
    }
};
