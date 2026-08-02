<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The succession "new critical position" form posts estimated_vacancy_date
     * and SuccessionController::storePosition writes it, but the column was
     * never created. Add it.
     */
    public function up(): void
    {
        Schema::table('critical_positions', function (Blueprint $table) {
            $table->date('estimated_vacancy_date')->nullable()->after('vacancy_risk');
        });
    }

    public function down(): void
    {
        Schema::table('critical_positions', function (Blueprint $table) {
            $table->dropColumn('estimated_vacancy_date');
        });
    }
};
