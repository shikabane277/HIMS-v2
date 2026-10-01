<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'totp_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('totp_secret', 64)->nullable()->after('two_factor_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'totp_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('totp_secret');
            });
        }
    }
};
