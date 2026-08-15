<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Restore recognition after the August 14 removal migration. */
    public function up(): void
    {
        if (! Schema::hasTable('recognition_badges')) {
            Schema::create('recognition_badges', function (Blueprint $table) {
                $table->char('badge_id', 36)->primary();
                $table->string('badge_name', 100)->unique();
                $table->string('badge_icon', 50)->nullable();
                $table->string('badge_color', 7)->nullable();
                $table->string('hospital_value', 100)->nullable();
                $table->text('description')->nullable();
                $table->integer('points_value')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recognition_posts')) {
            Schema::create('recognition_posts', function (Blueprint $table) {
                $table->char('post_id', 36)->primary();
                $table->char('author_id', 36);
                $table->char('recipient_id', 36);
                $table->char('badge_id', 36)->nullable();
                $table->string('post_type', 20)->default('peer');
                $table->text('message');
                $table->boolean('is_public')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->string('moderation_status', 20)->default('approved');
                $table->char('moderated_by', 36)->nullable();
                $table->text('moderation_note')->nullable();
                $table->char('link_to_review_id', 36)->nullable();
                $table->timestamps();

                $table->foreign('author_id')->references('employee_id')->on('employees');
                $table->foreign('recipient_id')->references('employee_id')->on('employees');
                $table->foreign('badge_id')->references('badge_id')->on('recognition_badges')->nullOnDelete();
                $table->foreign('moderated_by')->references('employee_id')->on('employees')->nullOnDelete();
                $table->index(['moderation_status', 'is_public', 'created_at']);
                $table->index(['author_id', 'created_at']);
                $table->index(['recipient_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('recognition_reactions')) {
            Schema::create('recognition_reactions', function (Blueprint $table) {
                $table->char('reaction_id', 36)->primary();
                $table->char('post_id', 36);
                $table->char('employee_id', 36);
                $table->string('reaction_type', 20)->default('clap');
                $table->timestamps();

                $table->unique(['post_id', 'employee_id']);
                $table->foreign('post_id')->references('post_id')->on('recognition_posts')->cascadeOnDelete();
                $table->foreign('employee_id')->references('employee_id')->on('employees');
            });
        }

        if (! Schema::hasTable('recognition_comments')) {
            Schema::create('recognition_comments', function (Blueprint $table) {
                $table->char('comment_id', 36)->primary();
                $table->char('post_id', 36);
                $table->char('author_id', 36);
                $table->text('comment_text');
                $table->string('moderation_status', 20)->default('approved');
                $table->timestamps();

                $table->foreign('post_id')->references('post_id')->on('recognition_posts')->cascadeOnDelete();
                $table->foreign('author_id')->references('employee_id')->on('employees');
                $table->index(['post_id', 'moderation_status', 'created_at']);
            });
        }

        $this->seedCoreBadges();
        $this->createLeaderboardView();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP VIEW IF EXISTS v_recognition_leaderboard');
        }

        Schema::dropIfExists('recognition_comments');
        Schema::dropIfExists('recognition_reactions');
        Schema::dropIfExists('recognition_posts');
        Schema::dropIfExists('recognition_badges');
    }

    private function seedCoreBadges(): void
    {
        $now = now();
        $badges = [
            ['badge_name' => 'Compassion (Kalinga)', 'badge_icon' => 'bi bi-heart-pulse-fill', 'badge_color' => '#be123c', 'hospital_value' => 'Compassion', 'description' => 'For exceptional care, empathy, and attention to patients or colleagues.', 'points_value' => 5],
            ['badge_name' => 'Teamwork (Bayanihan)', 'badge_icon' => 'bi bi-people-fill', 'badge_color' => '#0369a1', 'hospital_value' => 'Teamwork', 'description' => 'For stepping in, sharing responsibility, and helping the team deliver safely.', 'points_value' => 5],
            ['badge_name' => 'Innovation (Diskarte)', 'badge_icon' => 'bi bi-lightbulb-fill', 'badge_color' => '#a16207', 'hospital_value' => 'Innovation', 'description' => 'For a practical improvement that removes friction or improves service.', 'points_value' => 5],
            ['badge_name' => 'Clinical Excellence', 'badge_icon' => 'bi bi-shield-check', 'badge_color' => '#047857', 'hospital_value' => 'Excellence', 'description' => 'For consistently precise, safe, and evidence-based clinical work.', 'points_value' => 8],
        ];

        foreach ($badges as $badge) {
            DB::table('recognition_badges')->insertOrIgnore(array_merge($badge, [
                'badge_id' => (string) Str::uuid(),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function createLeaderboardView(): void
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
            WHERE rp.moderation_status = 'approved' AND rp.is_public = 1
            GROUP BY rp.recipient_id, e.first_name, e.last_name, e.department_id, d.name, DATE_FORMAT(rp.created_at, '%Y-%m-01')
        SQL);
    }
};
