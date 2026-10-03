<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recognition_posts')) {
            Schema::table('recognition_posts', function (Blueprint $table) {
                if (! Schema::hasColumn('recognition_posts', 'points_status')) {
                    $table->string('points_status', 20)->default('pending')->after('moderation_note');
                }
                if (! Schema::hasColumn('recognition_posts', 'points_admitted_by')) {
                    $table->char('points_admitted_by', 36)->nullable()->after('points_status');
                }
                if (! Schema::hasColumn('recognition_posts', 'points_admitted_at')) {
                    $table->timestamp('points_admitted_at')->nullable()->after('points_admitted_by');
                }
            });

            // Mark pre-existing rows as admitted so existing historical data retains points
            DB::table('recognition_posts')
                ->where('moderation_status', 'approved')
                ->whereNull('points_admitted_at')
                ->update([
                    'points_status' => 'admitted',
                    'points_admitted_at' => now(),
                ]);
        }

        $this->updateLeaderboardView();
    }

    public function down(): void
    {
        if (Schema::hasTable('recognition_posts')) {
            Schema::table('recognition_posts', function (Blueprint $table) {
                if (Schema::hasColumn('recognition_posts', 'points_admitted_at')) {
                    $table->dropColumn('points_admitted_at');
                }
                if (Schema::hasColumn('recognition_posts', 'points_admitted_by')) {
                    $table->dropColumn('points_admitted_by');
                }
                if (Schema::hasColumn('recognition_posts', 'points_status')) {
                    $table->dropColumn('points_status');
                }
            });
        }
    }

    private function updateLeaderboardView(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW v_recognition_leaderboard AS
            SELECT
                rp.recipient_id AS employee_id,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                e.department_id,
                d.name AS department_name,
                COUNT(rp.post_id) AS total_recognitions,
                SUM(COALESCE(rb.points_value, 1)) AS total_points,
                DATE_FORMAT(rp.created_at, '%Y-%m-01') AS month
            FROM recognition_posts rp
            JOIN employees e ON rp.recipient_id = e.employee_id
            JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN recognition_badges rb ON rp.badge_id = rb.badge_id
            WHERE rp.moderation_status = 'approved' AND rp.is_public = 1 AND rp.points_status = 'admitted'
            GROUP BY rp.recipient_id, e.first_name, e.last_name, e.department_id, d.name, DATE_FORMAT(rp.created_at, '%Y-%m-01')
        SQL);
    }
};
