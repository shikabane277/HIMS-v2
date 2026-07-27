<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('critical_positions', function (Blueprint $table) {
            $table->char('position_id', 36)->primary();
            $table->string('position_title', 200);
            $table->char('department_id', 36);
            $table->char('current_holder_id', 36)->nullable();
            $table->boolean('is_critical')->default(true);
            $table->string('vacancy_risk', 10)->default('medium');
            $table->json('risk_factors')->nullable();
            $table->text('impact_description')->nullable();
            $table->timestamps();
            $table->foreign('department_id')->references('department_id')->on('departments');
            $table->foreign('current_holder_id')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('succession_candidates', function (Blueprint $table) {
            $table->char('candidate_id', 36)->primary();
            $table->char('position_id', 36);
            $table->char('employee_id', 36);
            $table->integer('performance_score');
            $table->integer('potential_score');
            // nine_box_label computed in PHP (MySQL generated columns have limitations with Eloquent)
            $table->string('nine_box_label', 30)->nullable();
            $table->string('readiness_level', 20);
            $table->json('development_plan')->nullable();
            $table->char('mentor_id', 36)->nullable();
            $table->string('status', 20)->default('proposed');
            $table->char('nominated_by', 36)->nullable();
            $table->timestamp('nominated_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unique(['position_id', 'employee_id']);
            $table->foreign('position_id')->references('position_id')->on('critical_positions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('mentor_id')->references('employee_id')->on('employees')->nullOnDelete();
            $table->foreign('nominated_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('succession_reviews', function (Blueprint $table) {
            $table->char('review_id', 36)->primary();
            $table->char('position_id', 36);
            $table->string('review_period', 30);
            $table->char('reviewed_by', 36);
            $table->text('review_notes')->nullable();
            $table->string('risk_assessment', 10)->nullable();
            $table->json('action_items')->nullable();
            $table->timestamps();
            $table->foreign('position_id')->references('position_id')->on('critical_positions');
            $table->foreign('reviewed_by')->references('employee_id')->on('employees');
        });

        Schema::create('leadership_development_paths', function (Blueprint $table) {
            $table->char('path_id', 36)->primary();
            $table->char('candidate_id', 36);
            $table->string('milestone_title', 200);
            $table->string('milestone_type', 30)->nullable();
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('linked_course_id', 36)->nullable();
            $table->char('linked_competency', 36)->nullable();
            $table->timestamps();
            $table->foreign('candidate_id')->references('candidate_id')->on('succession_candidates')->cascadeOnDelete();
            $table->foreign('linked_course_id')->references('course_id')->on('courses')->nullOnDelete();
            $table->foreign('linked_competency')->references('competency_id')->on('competencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_development_paths');
        Schema::dropIfExists('succession_reviews');
        Schema::dropIfExists('succession_candidates');
        Schema::dropIfExists('critical_positions');
    }
};
