<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_venues', function (Blueprint $table) {
            $table->char('venue_id', 36)->primary();
            $table->string('venue_name', 150);
            $table->string('building', 100)->nullable();
            $table->string('floor', 20)->nullable();
            $table->integer('capacity');
            $table->json('equipment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->char('session_id', 36)->primary();
            $table->string('session_code', 30)->unique()->nullable();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->char('instructor_id', 36);
            $table->char('venue_id', 36)->nullable();
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('capacity');
            $table->date('registration_deadline')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->char('linked_course_id', 36)->nullable();
            $table->json('linked_competencies')->nullable();
            $table->decimal('cpd_hours', 4, 1)->default(0);
            $table->boolean('has_pre_test')->default(false);
            $table->boolean('has_post_test')->default(false);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('instructor_id')->references('employee_id')->on('employees');
            $table->foreign('venue_id')->references('venue_id')->on('training_venues')->nullOnDelete();
            $table->foreign('linked_course_id')->references('course_id')->on('courses')->nullOnDelete();
            $table->foreign('created_by')->references('employee_id')->on('employees')->nullOnDelete();
            // Unique index to prevent venue double-booking
            $table->unique(['venue_id', 'session_date', 'start_time'], 'idx_venue_schedule');
        });

        Schema::create('training_registrations', function (Blueprint $table) {
            $table->char('registration_id', 36)->primary();
            $table->char('session_id', 36);
            $table->char('employee_id', 36);
            $table->char('registered_by', 36)->nullable();
            $table->timestamp('registration_date')->useCurrent();
            $table->string('status', 20)->default('registered');
            $table->timestamp('check_in_time')->nullable();
            $table->string('check_in_method', 20)->nullable();
            $table->unique(['session_id', 'employee_id']);
            $table->foreign('session_id')->references('session_id')->on('training_sessions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('registered_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('training_tests', function (Blueprint $table) {
            $table->char('test_id', 36)->primary();
            $table->char('session_id', 36);
            $table->string('test_type', 10);
            $table->json('questions');
            $table->decimal('passing_score', 5, 2)->default(70.00);
            $table->timestamps();
            $table->foreign('session_id')->references('session_id')->on('training_sessions')->cascadeOnDelete();
        });

        Schema::create('training_test_results', function (Blueprint $table) {
            $table->char('result_id', 36)->primary();
            $table->char('test_id', 36);
            $table->char('employee_id', 36);
            $table->decimal('score_pct', 5, 2);
            $table->json('answers')->nullable();
            $table->timestamp('completed_at')->useCurrent();
            $table->unique(['test_id', 'employee_id']);
            $table->foreign('test_id')->references('test_id')->on('training_tests')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
        });

        Schema::create('training_feedback', function (Blueprint $table) {
            $table->char('feedback_id', 36)->primary();
            $table->char('session_id', 36);
            $table->char('employee_id', 36);
            $table->integer('overall_rating');
            $table->integer('content_rating')->nullable();
            $table->integer('instructor_rating')->nullable();
            $table->integer('venue_rating')->nullable();
            $table->text('comments')->nullable();
            $table->decimal('ai_sentiment_score', 3, 2)->nullable();
            $table->string('ai_sentiment_label', 20)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->unique(['session_id', 'employee_id']);
            $table->foreign('session_id')->references('session_id')->on('training_sessions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_feedback');
        Schema::dropIfExists('training_test_results');
        Schema::dropIfExists('training_tests');
        Schema::dropIfExists('training_registrations');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_venues');
    }
};
