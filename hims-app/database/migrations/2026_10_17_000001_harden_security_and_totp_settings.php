<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure system_settings table exists and has default record access password
        if (Schema::hasTable('system_settings')) {
            $existing = DB::table('system_settings')->where('key', 'record_access_password_hash')->first();
            if (! $existing) {
                DB::table('system_settings')->insert([
                    'key' => 'record_access_password_hash',
                    'value' => Hash::make('Hospital@2026!'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Harden users table: allow longer totp_secret for encryption and add totp_consumed_counter
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'totp_consumed_counter')) {
                    $table->unsignedBigInteger('totp_consumed_counter')->nullable()->after('totp_secret');
                }
            });

            // If not sqlite, enlarge totp_secret to text so it holds Crypt payload
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('ALTER TABLE users MODIFY totp_secret TEXT NULL');
            }
        }

        // 3. Ensure system_integrations table api_key can hold encrypted payload
        if (Schema::hasTable('system_integrations') && DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE system_integrations MODIFY api_key TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'totp_consumed_counter')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('totp_consumed_counter');
            });
        }

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->where('key', 'record_access_password_hash')->delete();
        }
    }
};
