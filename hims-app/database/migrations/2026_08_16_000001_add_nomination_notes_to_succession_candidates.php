<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give the nomination modal's Notes field somewhere to land.
 *
 * `succession/index.blade.php` has offered a "Nomination rationale or initial
 * goals" textarea since the module shipped, and `storeCandidate()` neither
 * validated nor inserted it — there was no column. So the field posted, passed
 * validation by being ignored, and the text was discarded while the user saw a
 * success message. Silent data loss is the worst shape a bug can take: nothing
 * tells the person their reasoning is gone, and by the time anybody looks for
 * it there is nothing to recover.
 *
 * A dedicated TEXT column rather than the existing nullable `development_plan`
 * JSON column, which nothing reads or writes: prose in a JSON column would need
 * encoding rules nobody has, and a dead column adopted for a live purpose stops
 * being obviously dead. `development_plan` is left exactly as it is.
 *
 * Named to match `critical_positions.quarterly_review_notes` — the module's
 * existing, working notes field — so the two read as one convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('succession_candidates', function (Blueprint $table) {
            $table->text('nomination_notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('succession_candidates', function (Blueprint $table) {
            $table->dropColumn('nomination_notes');
        });
    }
};
