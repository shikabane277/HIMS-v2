<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->char('cycle_id', 36)->primary();
            $table->string('cycle_name', 100);
            $table->string('cycle_type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('planned');
            $table->char('created_by', 36);
            $table->timestamps();

            $table->foreign('created_by')->references('employee_id')->on('employees');
        });

        Schema::create('kpi_library', function (Blueprint $table) {
            $table->char('kpi_id', 36)->primary();
            $table->string('kpi_name', 200);
            $table->string('kpi_category', 30);
            $table->text('description')->nullable();
            $table->decimal('target_value', 5, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->json('applicable_roles')->nullable();
            $table->decimal('weight', 3, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->char('review_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('cycle_id', 36);
            $table->char('reviewer_id', 36)->nullable();
            $table->string('review_type', 20)->default('standard');
            $table->string('status', 30)->default('draft');
            $table->decimal('self_rating', 3, 2)->nullable();
            $table->decimal('supervisor_rating', 3, 2)->nullable();
            $table->decimal('peer_rating', 3, 2)->nullable();
            $table->decimal('overall_score', 3, 2)->nullable();
            $table->text('strengths_text')->nullable();
            $table->text('improvements_text')->nullable();
            $table->json('ai_bias_flags')->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('digital_signature')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('cycle_id')->references('cycle_id')->on('review_cycles');
            $table->foreign('reviewer_id')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('review_kpi_scores', function (Blueprint $table) {
            $table->char('score_id', 36)->primary();
            $table->char('review_id', 36);
            $table->char('kpi_id', 36);
            $table->decimal('self_score', 3, 2)->nullable();
            $table->decimal('supervisor_score', 3, 2)->nullable();
            $table->decimal('peer_score', 3, 2)->nullable();
            $table->decimal('weighted_score', 3, 2)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'kpi_id']);
            $table->foreign('review_id')->references('review_id')->on('performance_reviews')->cascadeOnDelete();
            $table->foreign('kpi_id')->references('kpi_id')->on('kpi_library');
        });

        Schema::create('peer_reviews', function (Blueprint $table) {
            $table->char('peer_review_id', 36)->primary();
            $table->char('review_id', 36);
            $table->char('peer_employee_id', 36);
            $table->decimal('rating', 3, 2);
            $table->text('comments')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->timestamps();

            $table->foreign('review_id')->references('review_id')->on('performance_reviews')->cascadeOnDelete();
            $table->foreign('peer_employee_id')->references('employee_id')->on('employees');
        });

        Schema::create('performance_improvement_plans', function (Blueprint $table) {
            $table->char('pip_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('triggered_by_review', 36);
            $table->string('status', 20)->default('initiated');
            $table->json('action_steps');
            $table->date('start_date');
            $table->date('target_end_date');
            $table->date('actual_end_date')->nullable();
            $table->char('supervisor_id', 36)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('triggered_by_review')->references('review_id')->on('performance_reviews');
            $table->foreign('supervisor_id')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('review_goals', function (Blueprint $table) {
            $table->char('goal_id', 36)->primary();
            $table->char('review_id', 36);
            $table->char('employee_id', 36);
            $table->string('goal_title', 300);
            $table->text('goal_description')->nullable();
            $table->date('target_date')->nullable();
            $table->integer('progress_pct')->default(0);
            $table->string('status', 20)->default('not_started');
            $table->timestamps();

            $table->foreign('review_id')->references('review_id')->on('performance_reviews')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_goals');
        Schema::dropIfExists('performance_improvement_plans');
        Schema::dropIfExists('peer_reviews');
        Schema::dropIfExists('review_kpi_scores');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('kpi_library');
        Schema::dropIfExists('review_cycles');
    }
};
