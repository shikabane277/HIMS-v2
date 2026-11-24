<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Position of each audit row in the tamper-evident hash chain written by
 * App\Support\AuditTrail and checked by `php artisan hims:audit-verify`.
 * Rows written before this migration keep a null seq and are reported as
 * "legacy, unchained" by the verifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_trails', 'chain_seq')) {
            Schema::table('audit_trails', function (Blueprint $table) {
                $table->unsignedBigInteger('chain_seq')->nullable()->unique()->after('audit_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('audit_trails', 'chain_seq')) {
            Schema::table('audit_trails', function (Blueprint $table) {
                $table->dropUnique(['chain_seq']);
                $table->dropColumn('chain_seq');
            });
        }
    }
};
