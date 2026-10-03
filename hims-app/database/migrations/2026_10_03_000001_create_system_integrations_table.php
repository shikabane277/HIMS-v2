<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_integrations')) {
            Schema::create('system_integrations', function (Blueprint $table) {
                $table->char('integration_id', 36)->primary();
                $table->string('system_code', 50)->unique();
                $table->string('system_name', 150);
                $table->text('description')->nullable();
                $table->string('base_url', 255)->nullable();
                $table->text('api_key')->nullable();
                $table->integer('timeout')->default(30);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_sync_status', 50)->default('idle');
                $table->text('last_sync_message')->nullable();
                $table->integer('synced_records_count')->default(0);
                $table->timestamps();
            });

            DB::table('system_integrations')->insert([
                [
                    'integration_id' => (string) Str::uuid(),
                    'system_code' => 'hr1',
                    'system_name' => 'HR1 - Employee Master & Clinical Credentials',
                    'description' => 'Feeds staff clinical licenses, PRC credentials, board certifications, and validity dates.',
                    'base_url' => env('HR1_API_BASE_URL', ''),
                    'api_key' => env('HR1_API_KEY', ''),
                    'timeout' => (int) env('HR1_API_TIMEOUT', 30),
                    'is_active' => true,
                    'last_synced_at' => null,
                    'last_sync_status' => 'idle',
                    'last_sync_message' => 'Awaiting initial synchronization from HR1 API.',
                    'synced_records_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'integration_id' => (string) Str::uuid(),
                    'system_code' => 'hr2',
                    'system_name' => 'HR2 - Competency & Talent Development System',
                    'description' => 'Feeds competency framework domains, categories, competencies, and staff proficiency assessments.',
                    'base_url' => env('HR2_API_BASE_URL', ''),
                    'api_key' => env('HR2_API_KEY', ''),
                    'timeout' => (int) env('HR2_API_TIMEOUT', 30),
                    'is_active' => true,
                    'last_synced_at' => null,
                    'last_sync_status' => 'idle',
                    'last_sync_message' => 'Awaiting initial synchronization from HR2 API.',
                    'synced_records_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_integrations');
    }
};
