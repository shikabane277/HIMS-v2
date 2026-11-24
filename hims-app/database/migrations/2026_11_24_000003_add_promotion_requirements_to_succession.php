<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion qualification for succession.
 *
 * critical_positions gets the requirements a successor must meet — the job
 * role whose competencies apply, a minimum performance score, minimum years
 * of service and the licences/credentials the post needs. succession_candidates
 * records the promotion itself. Pay is deliberately not handled here: HR3
 * only decides whether the person QUALIFIES; the position/salary change is
 * made in HR1 (employee master).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('critical_positions', function (Blueprint $table) {
            if (! Schema::hasColumn('critical_positions', 'target_role_id')) {
                $table->char('target_role_id', 36)->nullable();
                $table->decimal('min_performance_score', 3, 2)->nullable();
                $table->unsignedTinyInteger('min_years_service')->nullable();
                $table->json('required_credentials')->nullable();
            }
        });

        Schema::table('succession_candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('succession_candidates', 'promoted_at')) {
                $table->timestamp('promoted_at')->nullable();
                $table->char('promoted_by', 36)->nullable();
                $table->text('promotion_override_reason')->nullable();
                $table->json('qualification_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('critical_positions', function (Blueprint $table) {
            $table->dropColumn(['target_role_id', 'min_performance_score', 'min_years_service', 'required_credentials']);
        });
        Schema::table('succession_candidates', function (Blueprint $table) {
            $table->dropColumn(['promoted_at', 'promoted_by', 'promotion_override_reason', 'qualification_snapshot']);
        });
    }
};
