<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes out-of-chain reviews visible, and one reviewer's review unique.
 *
 * Review authority now follows the reporting line (employees.supervisor_id)
 * rather than a role or a department. Admin and HR may still act outside that
 * line, but only in three named situations, and the review they produce must not
 * be indistinguishable from one written by the employee's actual supervisor —
 * hence a flag, a machine-readable basis, and the reviewer's own words.
 *
 * `exception_basis` is a short slug rather than free text alone so the
 * accreditation question "how many reviews were written outside the chain, and
 * why" can be answered with a GROUP BY instead of by reading prose that rots
 * into "approved by HR".
 *
 * The unique index is a backstop, not the rule. `reviewer_id` is nullable (its
 * FK is nullOnDelete, so deleting a reviewer's employee record nulls it) and
 * MySQL treats NULLs as distinct in a unique index, so two reviewer-less rows
 * both pass it. The PHP pre-check in PerformanceController::storeReview() is
 * what actually routes a repeat create to the existing review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->boolean('is_exception_review')->default(false)->after('review_type');
            $table->string('exception_basis', 30)->nullable()->after('is_exception_review');
            $table->text('exception_reason')->nullable()->after('exception_basis');
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            // Named explicitly: the generated name would run past MySQL's
            // 64-character index-name limit.
            $table->unique(
                ['employee_id', 'cycle_id', 'review_type', 'reviewer_id'],
                'pr_employee_cycle_type_reviewer_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropUnique('pr_employee_cycle_type_reviewer_unique');
        });

        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn(['is_exception_review', 'exception_basis', 'exception_reason']);
        });
    }
};
