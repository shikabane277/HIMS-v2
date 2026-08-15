<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('training_test_results');
        Schema::dropIfExists('training_tests');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('system_users');
        Schema::dropIfExists('succession_reviews');
        Schema::dropIfExists('credential_types');
    }

    public function down(): void
    {
        // Unused legacy tables dropped per system trim objective
    }
};
