<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The compliance layer: mandatory training assignment, renewal-cycle rules, and
 * the cycle instances those rules produce.
 *
 * WHY RULES AND CYCLES ARE TWO TABLES
 *
 * A rule says what a cycle requires ("PRC nurse licence: 45 hours every 36
 * months"). A cycle is one employee's current window against that rule. Keeping
 * them apart means a rule change cannot silently rewrite history: each cycle
 * freezes `hours_required_snapshot` when it opens, so raising the requirement
 * from 45 to 60 applies to the next cycle rather than retroactively failing
 * everyone who already met the old bar.
 *
 * Hours *attained* are deliberately NOT stored. They are summed from
 * `cpd_records` at read time (date_earned inside the window, verified = 1), so a
 * CPD entry verified late needs no recount job and cannot leave a stale total
 * behind.
 *
 * WHY renewal_rules IS NOT AN EXTENSION OF credential_types
 *
 * `credential_types` is dead schema — zero references anywhere — and is keyed on
 * `type_name` while `employee_credentials.credential_type` is free text with no
 * FK to it. Binding rules to it would need a data-cleanup migration over live
 * credential rows first. It also cannot host CPD cycles, which need the same
 * rule shape but hang off a profession rather than a credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        // What a renewal cycle requires. subject_key is a credential_type string
        // or a profession label — free text on purpose, matching the free-text
        // column it has to join against.
        Schema::create('renewal_rules', function (Blueprint $table) {
            $table->char('rule_id', 36)->primary();
            $table->string('subject_type', 20);              // credential | cpd
            $table->string('subject_key', 100);
            $table->string('label', 150);
            $table->decimal('required_hours', 5, 1);
            $table->integer('cycle_months');
            $table->integer('grace_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_key']);
            $table->foreign('created_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        // One employee's window against one rule.
        Schema::create('employee_renewal_cycles', function (Blueprint $table) {
            $table->char('cycle_id', 36)->primary();
            $table->char('employee_id', 36);
            $table->char('rule_id', 36);
            $table->date('cycle_start');
            $table->date('cycle_end');
            // Frozen at open: a later rule change must not rewrite this window.
            $table->decimal('hours_required_snapshot', 5, 1);
            $table->char('credential_id', 36)->nullable();
            $table->string('status', 20)->default('open');   // open | met | shortfall | closed
            $table->timestamps();

            $table->unique(['employee_id', 'rule_id', 'cycle_start']);
            $table->index(['employee_id', 'status']);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            $table->foreign('rule_id')->references('rule_id')->on('renewal_rules')->cascadeOnDelete();
            $table->foreign('credential_id')->references('credential_id')->on('employee_credentials')->nullOnDelete();
        });

        // The assignment *intent*. A department-wide assignment is one row here
        // plus N enrollment rows, so "who assigned this and to whom" survives
        // even after people join or leave the department.
        Schema::create('training_assignments', function (Blueprint $table) {
            $table->char('assignment_id', 36)->primary();
            $table->string('subject_type', 20);              // course | session
            $table->char('subject_id', 36);
            $table->string('target_type', 20);               // employee | department | role | all
            $table->char('target_id', 36)->nullable();       // null when target_type = all
            $table->date('required_by')->nullable();
            $table->text('reason')->nullable();
            $table->char('assigned_by', 36)->nullable();
            $table->integer('expanded_count')->default(0);
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->foreign('assigned_by')->references('employee_id')->on('employees')->nullOnDelete();
        });

        // Which assignment produced a given enrollment, so an assigned course can
        // be told apart from one somebody chose. Nullable: self-enrollment stays
        // exactly as it was.
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->char('assignment_id', 36)->nullable()->after('enrolled_by');
        });

        Schema::table('training_registrations', function (Blueprint $table) {
            $table->char('assignment_id', 36)->nullable()->after('registered_by');
            $table->date('required_by')->nullable()->after('assignment_id');
        });

        // credential_alert_log was built for credentials only: credential_id is
        // NOT NULL with an FK. A cycle-shortfall alert has no credential to point
        // at, so the column becomes nullable and a discriminator says what the
        // row is about. One ledger keeps one dedupe path — two would drift, which
        // is the failure v2.7.0 collapsed nine inline copies to avoid.
        Schema::table('credential_alert_log', function (Blueprint $table) {
            $table->string('subject_type', 30)->nullable()->after('alert_id');
            $table->char('subject_id', 36)->nullable()->after('subject_type');
        });

        $this->makeCredentialIdNullable();
    }

    /**
     * Drop the FK, widen the column, put the FK back.
     *
     * sqlite cannot ALTER a column in place, and doctrine/dbal is not installed,
     * so the sqlite path rebuilds the table by hand. phpunit runs on sqlite
     * `:memory:`, so this path is the one the test suite exercises.
     */
    private function makeCredentialIdNullable(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE credential_alert_log DROP FOREIGN KEY credential_alert_log_credential_id_foreign');
            DB::statement('ALTER TABLE credential_alert_log MODIFY credential_id CHAR(36) NULL');
            DB::statement('ALTER TABLE credential_alert_log
                ADD CONSTRAINT credential_alert_log_credential_id_foreign
                FOREIGN KEY (credential_id) REFERENCES employee_credentials(credential_id)');

            return;
        }

        $existing = DB::table('credential_alert_log')->get();

        Schema::rename('credential_alert_log', 'credential_alert_log_old');

        Schema::create('credential_alert_log', function (Blueprint $table) {
            $table->char('alert_id', 36)->primary();
            $table->string('subject_type', 30)->nullable();
            $table->char('subject_id', 36)->nullable();
            $table->char('credential_id', 36)->nullable();
            $table->char('employee_id', 36);
            $table->string('alert_type', 30);
            $table->json('sent_to');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreign('credential_id')->references('credential_id')->on('employee_credentials');
            $table->foreign('employee_id')->references('employee_id')->on('employees');
        });

        foreach ($existing->chunk(200) as $chunk) {
            DB::table('credential_alert_log')->insert(
                $chunk->map(fn ($row) => (array) $row)->all()
            );
        }

        Schema::drop('credential_alert_log_old');
    }

    public function down(): void
    {
        Schema::table('training_registrations', function (Blueprint $table) {
            $table->dropColumn(['assignment_id', 'required_by']);
        });

        Schema::table('course_enrollments', fn (Blueprint $t) => $t->dropColumn('assignment_id'));

        Schema::table('credential_alert_log', function (Blueprint $table) {
            $table->dropColumn(['subject_type', 'subject_id']);
        });

        Schema::dropIfExists('training_assignments');
        Schema::dropIfExists('employee_renewal_cycles');
        Schema::dropIfExists('renewal_rules');
    }
};
