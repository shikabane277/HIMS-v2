<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competency_domains', function (Blueprint $table) {
            $table->char('domain_id', 36)->primary();
            $table->string('domain_name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('competency_categories', function (Blueprint $table) {
            $table->char('category_id', 36)->primary();
            $table->char('domain_id', 36);
            $table->string('category_name', 150);
            $table->string('jci_standard_code', 30)->nullable();
            $table->timestamps();
            $table->foreign('domain_id')->references('domain_id')->on('competency_domains');
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->char('competency_id', 36)->primary();
            $table->char('category_id', 36);
            $table->string('competency_name', 200);
            $table->string('competency_code', 30)->unique()->nullable();
            $table->text('description')->nullable();
            $table->integer('required_proficiency');
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();
            $table->foreign('category_id')->references('category_id')->on('competency_categories');
        });

        Schema::create('role_competency_requirements', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('role_id', 36);
            $table->char('competency_id', 36);
            $table->integer('minimum_proficiency');
            $table->boolean('is_critical')->default(false);
            $table->unique(['role_id', 'competency_id']);
            $table->foreign('role_id')->references('role_id')->on('roles');
            $table->foreign('competency_id')->references('competency_id')->on('competencies');
        });

        Schema::create('competency_assessments', function (Blueprint $table) {
            $table->char('assessment_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('competency_id', 36);
            $table->char('assessed_by', 36);
            $table->string('assessment_method', 30)->default('observation');
            $table->integer('current_proficiency');
            $table->integer('gap')->nullable();
            $table->text('evidence_url')->nullable();
            $table->text('notes')->nullable();
            $table->date('assessed_date');
            $table->date('next_assessment_due')->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('competency_id')->references('competency_id')->on('competencies');
            $table->foreign('assessed_by')->references('employee_id')->on('employees');
        });

        // MySQL trigger to auto-compute gap
        DB::unprepared('
            CREATE TRIGGER trg_compute_gap_insert
            BEFORE INSERT ON competency_assessments
            FOR EACH ROW
            BEGIN
                DECLARE req_prof INT;
                SELECT required_proficiency INTO req_prof FROM competencies WHERE competency_id = NEW.competency_id;
                SET NEW.gap = NEW.current_proficiency - req_prof;
            END
        ');
        DB::unprepared('
            CREATE TRIGGER trg_compute_gap_update
            BEFORE UPDATE ON competency_assessments
            FOR EACH ROW
            BEGIN
                DECLARE req_prof INT;
                SELECT required_proficiency INTO req_prof FROM competencies WHERE competency_id = NEW.competency_id;
                SET NEW.gap = NEW.current_proficiency - req_prof;
            END
        ');

        Schema::create('employee_credentials', function (Blueprint $table) {
            $table->char('credential_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->string('credential_type', 50);
            $table->string('credential_number', 100)->nullable();
            $table->string('issuing_body', 150)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('document_url')->nullable();
            $table->char('verified_by', 36)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('verified_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        Schema::create('credential_alert_log', function (Blueprint $table) {
            $table->char('alert_id', 36)->primary();
            $table->char('credential_id', 36);
            $table->char('employee_id', 36);
            $table->string('alert_type', 30);
            $table->json('sent_to');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreign('credential_id')->references('credential_id')->on('employee_credentials');
            $table->foreign('employee_id')->references('employee_id')->on('employees');
        });
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_compute_gap_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_compute_gap_insert');
        Schema::dropIfExists('credential_alert_log');
        Schema::dropIfExists('employee_credentials');
        Schema::dropIfExists('competency_assessments');
        Schema::dropIfExists('role_competency_requirements');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('competency_categories');
        Schema::dropIfExists('competency_domains');
    }
};
