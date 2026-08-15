<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_recognition_leaderboard');
        Schema::dropIfExists('recognition_comments');
        Schema::dropIfExists('recognition_reactions');
        Schema::dropIfExists('recognition_posts');
        Schema::dropIfExists('recognition_badges');
    }

    public function down(): void
    {
        // Social recognition dropped permanently per project refactoring objective
    }
};
