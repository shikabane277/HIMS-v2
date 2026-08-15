<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapses the performance review to a single reviewer.
 *
 * A review used to gather three voices — the subject's `self_score`, an invited
 * peer's `peer_score`, and the reviewer's `supervisor_score` — blended 30/20/50
 * into `weighted_score`. That is gone. One review is now one reviewer's
 * assessment of one employee, and `weighted_score` carries the reviewer's own
 * number straight through so the consumers that read it
 * (CompetencyGapAnalysisService, the gap-analysis employee view) keep working
 * against a column that still means "the score for this KPI".
 *
 * WHY THE EXISTING ROWS ARE DELETED
 *
 * Every review in the table was written under the old model, and the shapes are
 * not translatable: a row sitting in `self_assessment` has a self score and no
 * reviewer score, so migrating it forward would either invent a reviewer
 * assessment that nobody made or leave a review that reads as complete with
 * nothing in it. The old statuses (`self_assessment`, `supervisor_review`,
 * `calibration`) have no successor either — the new column holds only 'draft' or
 * 'finished'. Purging is the honest option, and it is what was asked for.
 *
 * PIPs GO WITH THEM, AND THAT IS NOT OPTIONAL
 *
 * `performance_improvement_plans.triggered_by_review` is NOT NULL with a plain
 * foreign key — no cascade, no null-on-delete. So a PIP pins its review in place
 * and the purge cannot start until the PIPs are gone. Their loss is real
 * collateral, recorded in the patch notes rather than glossed over here.
 *
 * `review_kpi_scores`, `peer_reviews` and `review_goals` all cascade off
 * `performance_reviews`, so deleting the reviews empties them without help.
 *
 * THE UNIQUE INDEX LOSES `review_type`
 *
 * It was (employee, cycle, type, reviewer), which let one reviewer file a
 * standard review and a promotion review on the same employee in the same cycle.
 * The rule is now one review per reviewer per employee per cycle regardless of
 * type, so the index is rebuilt without it. The replacement must be created
 * before the old one is dropped: MySQL has been using the old unique index as
 * the backing index for the `employee_id` foreign key (its leftmost column is
 * employee_id), and refuses to drop an index a constraint depends on. Still a
 * backstop and not the rule: `reviewer_id` is nullable, MySQL counts NULLs as
 * distinct, and the PHP check in PerformanceController::storeReview() is what
 * actually redirects a repeat.
 *
 * The '360' review type is dropped from the validation rule in the controller
 * rather than here — a 360 *is* the self+peer+supervisor loop, so it has nothing
 * left to mean, but `review_type` is a plain varchar with no CHECK constraint and
 * the purge leaves no rows carrying the old value.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Order matters: PIPs block the reviews, reviews cascade to everything else.
        DB::table('performance_improvement_plans')->delete();
        DB::table('performance_reviews')->delete();

        Schema::dropIfExists('peer_reviews');

        Schema::table('performance_reviews', function (Blueprint $table) {
            // Add first, then drop: MySQL is using the old unique index as the
            // backing index for the employee_id foreign key (its leftmost column
            // is employee_id), and it refuses to drop an index a constraint
            // depends on. The new index steps into that role, so the FK is never
            // left with nothing to stand on. Named explicitly for the same reason
            // as the index it replaces: the generated name would run past MySQL's
            // 64-character limit.
            $table->unique(
                ['employee_id', 'cycle_id', 'reviewer_id'],
                'pr_employee_cycle_reviewer_unique'
            );
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropUnique('pr_employee_cycle_type_reviewer_unique');
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn(['self_rating', 'peer_rating']);
        });

        Schema::table('review_kpi_scores', function (Blueprint $table) {
            $table->dropColumn(['self_score', 'peer_score']);
        });
    }

    /**
     * Restores the shape, not the data.
     *
     * The purged reviews and PIPs are unrecoverable — rolling back gives you the
     * old columns and an empty `peer_reviews` table, and any review written under
     * the single-reviewer model keeps its supervisor score with the self and peer
     * columns sitting null.
     */
    public function down(): void
    {
        Schema::table('review_kpi_scores', function (Blueprint $table) {
            $table->decimal('self_score', 3, 2)->nullable()->after('kpi_id');
            $table->decimal('peer_score', 3, 2)->nullable()->after('supervisor_score');
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->decimal('self_rating', 3, 2)->nullable()->after('exception_reason');
            $table->decimal('peer_rating', 3, 2)->nullable()->after('self_rating');
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            // Same add-then-drop order as up(), for the same reason: whichever of
            // these two indexes exists is the one holding up the employee_id
            // foreign key, so the replacement has to be in place first.
            $table->unique(
                ['employee_id', 'cycle_id', 'review_type', 'reviewer_id'],
                'pr_employee_cycle_type_reviewer_unique'
            );
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropUnique('pr_employee_cycle_reviewer_unique');
        });

        Schema::create('peer_reviews', function (Blueprint $table) {
            $table->char('peer_review_id', 36)->primary();
            $table->char('review_id', 36);
            $table->char('peer_employee_id', 36);
            $table->text('feedback_text')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->timestamps();

            $table->foreign('review_id')->references('review_id')->on('performance_reviews')->cascadeOnDelete();
            $table->foreign('peer_employee_id')->references('employee_id')->on('employees');
        });
    }
};
