<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('critical_positions', function (Blueprint $table) {
            $table->timestamp('last_reviewed_at')->nullable()->after('estimated_vacancy_date');
            $table->char('last_reviewed_by', 36)->nullable()->after('last_reviewed_at');
            $table->text('quarterly_review_notes')->nullable()->after('last_reviewed_by');
            $table->foreign('last_reviewed_by')->references('employee_id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('critical_positions', function (Blueprint $table) {
            $table->dropForeign(['last_reviewed_by']);
            $table->dropColumn(['last_reviewed_at', 'last_reviewed_by', 'quarterly_review_notes']);
        });
    }
};
