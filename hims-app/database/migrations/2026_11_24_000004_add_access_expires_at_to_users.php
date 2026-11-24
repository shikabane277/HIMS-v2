<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-limited accounts. Trainee accounts (students on placement, interns,
 * OJT, rotating residents, orientees) must carry an end date; once it passes
 * the account can no longer sign in and any open session ends. Other roles
 * may optionally use it (e.g. a contract that ends on a known date).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'access_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('access_expires_at')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'access_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('access_expires_at');
            });
        }
    }
};
