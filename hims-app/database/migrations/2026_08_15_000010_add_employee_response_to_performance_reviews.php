<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->text('employee_response')->nullable()->after('improvements_text');
            $table->timestamp('employee_response_submitted_at')->nullable()->after('employee_response');
            $table->timestamp('employee_acknowledged_at')->nullable()->after('employee_response_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'employee_response',
                'employee_response_submitted_at',
                'employee_acknowledged_at',
            ]);
        });
    }
};
