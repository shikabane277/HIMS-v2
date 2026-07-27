<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_pathways', function (Blueprint $table) {
            $table->char('pathway_id', 36)->primary();
            $table->string('pathway_name', 200);
            $table->text('description')->nullable();
            $table->json('target_roles')->nullable();
            $table->decimal('total_cpd_hours', 5, 1)->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->char('course_id', 36)->primary();
            $table->string('course_code', 30)->unique()->nullable();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->decimal('cpd_hours', 4, 1)->default(0);
            $table->string('difficulty_level', 20)->default('intermediate');
            $table->integer('estimated_duration')->nullable();
            $table->decimal('passing_score', 5, 2)->default(70.00);
            $table->integer('max_retakes')->default(3);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('pathway_courses', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('pathway_id', 36);
            $table->char('course_id', 36);
            $table->integer('sequence_order');
            $table->boolean('is_prerequisite')->default(false);
            $table->unique(['pathway_id', 'course_id']);
            $table->foreign('pathway_id')->references('pathway_id')->on('learning_pathways')->cascadeOnDelete();
            $table->foreign('course_id')->references('course_id')->on('courses');
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->char('module_id', 36)->primary();
            $table->char('course_id', 36);
            $table->string('module_title', 200);
            $table->string('module_type', 30);
            $table->text('content_url')->nullable();
            $table->text('content_body')->nullable();
            $table->integer('sequence_order');
            $table->integer('estimated_minutes')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('course_id')->on('courses')->cascadeOnDelete();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->char('certificate_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('course_id', 36);
            $table->string('certificate_code', 50)->unique();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->text('pdf_url')->nullable();
            $table->text('qr_verification_url')->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('course_id')->references('course_id')->on('courses');
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->char('enrollment_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('course_id', 36);
            $table->char('enrolled_by', 36)->nullable();
            $table->date('enrollment_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('enrolled');
            $table->integer('progress_pct')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->decimal('cpd_hours_earned', 4, 1)->default(0);
            $table->char('certificate_id', 36)->nullable();
            $table->unique(['employee_id', 'course_id']);
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('course_id')->references('course_id')->on('courses');
            $table->foreign('enrolled_by')->references('employee_id')->on('employees')->nullOnDelete();
            $table->foreign('certificate_id')->references('certificate_id')->on('certificates')->nullOnDelete();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->char('question_id', 36)->primary();
            $table->char('module_id', 36);
            $table->text('question_text');
            $table->string('question_type', 20)->default('multiple_choice');
            $table->json('options');
            $table->text('correct_answer');
            $table->text('explanation')->nullable();
            $table->integer('points')->default(1);
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
            $table->foreign('module_id')->references('module_id')->on('course_modules')->cascadeOnDelete();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->char('attempt_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('module_id', 36);
            $table->integer('attempt_number')->default(1);
            $table->json('answers');
            $table->decimal('score_pct', 5, 2);
            $table->boolean('passed');
            $table->timestamp('started_at');
            $table->timestamp('completed_at');
            $table->integer('time_spent_seconds')->nullable();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('module_id')->references('module_id')->on('course_modules');
        });

        Schema::create('cpd_records', function (Blueprint $table) {
            $table->char('cpd_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->string('source_type', 30);
            $table->char('source_id', 36)->nullable();
            $table->string('activity_name', 300);
            $table->decimal('cpd_hours', 4, 1);
            $table->date('date_earned');
            $table->string('renewal_period', 20)->nullable();
            $table->boolean('verified')->default(false);
            $table->char('verified_by', 36)->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('verified_by')->references('employee_id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpd_records');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('pathway_courses');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('learning_pathways');
    }
};
