<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL auto-commits DDL, so keep this idempotent in case a later
        // backfill statement fails and the migration is rerun.
        if (! Schema::hasColumn('employees', 'is_people_manager')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->boolean('is_people_manager')->default(false)->after('employment_status');
            });
        }

        // Preserve every existing reporting relationship. Anyone already named
        // as a supervisor becomes a People Manager without changing account role.
        // Read the ids first because MySQL rejects updating a table from a
        // subquery that reads that same table (error 1093).
        DB::table('employees')
            ->whereNotNull('supervisor_id')
            ->distinct()
            ->pluck('supervisor_id')
            ->filter()
            ->chunk(500)
            ->each(function ($managerIds) {
                DB::table('employees')
                    ->whereIn('employee_id', $managerIds->all())
                    ->update(['is_people_manager' => true]);
            });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_people_manager');
        });
    }
};
