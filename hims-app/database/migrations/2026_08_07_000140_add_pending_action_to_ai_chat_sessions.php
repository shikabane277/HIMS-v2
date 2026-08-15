<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the destructive action the assistant has offered but not yet performed.
 *
 * A delete asked for in chat is not executed on the spot: the assistant
 * restates the resolved target and waits for "confirm". That pause needs
 * somewhere to live, and it has to survive between two HTTP requests, so it
 * lands on the conversation row rather than in memory.
 *
 * `pending_action_at` exists so a stale offer expires instead of firing much
 * later against a "yes" that was answering something else entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plain column adds, no index: no table rebuild on SQLite, matching
        // the approach in ..._000120.
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->text('pending_action')->nullable()->after('title');
            $table->timestamp('pending_action_at')->nullable()->after('pending_action');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['pending_action', 'pending_action_at']);
        });
    }
};
