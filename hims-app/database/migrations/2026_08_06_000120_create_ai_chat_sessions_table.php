<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds multi-conversation support to the AI assistant.
 *
 * Before this, ai_chat_messages was one flat per-user log: every message a user
 * ever sent lived in a single undivided stream, so there was no way to start a
 * new chat or look back at a previous one. This introduces ai_chat_sessions
 * (one row per conversation) and points each message at its session.
 *
 * WHY session_id carries NO foreign key: adding an FK through Schema::table()
 * makes Laravel's SQLite grammar rebuild the whole table (copy to __temp__,
 * drop, rename). That rebuild reconstructs columns from BlueprintState, which
 * does not capture CHECK constraints — so the role enum('user','ai'), which
 * SQLite implements as `varchar check (role in (...))`, would be silently
 * downgraded to a plain varchar in the test database while MySQL keeps a real
 * ENUM. The same rebuild also copies rows with `pragma foreign_keys` off, so an
 * FK that MySQL would reject on existing history passes green in CI. Ownership
 * is instead enforced in AiController, which scopes every session read/write by
 * auth()->id(); deleting a session explicitly deletes its messages first.
 *
 * The `seq` column exists because created_at cannot order a conversation:
 * AiController wrote one `now()` value to both the question and the answer row,
 * and the column has whole-second precision, so the two halves of a turn tie.
 * That was invisible while history was only ever displayed, but the replayed
 * turn list sent to the model must be in the true order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            // Derived from the first user message; null until that arrives.
            $table->string('title', 120)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // Sidebar list: this user's conversations, most recent first.
            $table->index(['user_id', 'updated_at']);
        });

        // Plain column adds + a separate CREATE INDEX: no table rebuild on
        // SQLite, no FK validation against existing rows on MySQL.
        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->char('session_id', 36)->nullable()->after('user_id');
            $table->unsignedInteger('seq')->nullable()->after('message');
            // Ordered replay of one conversation.
            $table->index(['session_id', 'seq'], 'ai_chat_messages_session_seq_index');
        });

        $this->adoptLegacyMessages();
    }

    public function down(): void
    {
        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropIndex('ai_chat_messages_session_seq_index');
            $table->dropColumn(['session_id', 'seq']);
        });

        Schema::dropIfExists('ai_chat_sessions');
    }

    /**
     * Give every pre-existing message a home so old history is not orphaned:
     * one "Earlier conversation" session per user, holding everything that user
     * had already said. UUIDs are generated in PHP here because that is how the
     * whole app makes them — MySQL's UUID() is never used.
     */
    private function adoptLegacyMessages(): void
    {
        $userIds = DB::table('ai_chat_messages')
            ->whereNull('session_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $sessionId = (string) Str::uuid();

            $bounds = DB::table('ai_chat_messages')
                ->where('user_id', $userId)
                ->whereNull('session_id')
                ->selectRaw('MIN(created_at) AS first_at, MAX(created_at) AS last_at')
                ->first();

            DB::table('ai_chat_sessions')->insert([
                'id' => $sessionId,
                'user_id' => $userId,
                'title' => 'Earlier conversation',
                'created_at' => $bounds->first_at ?? now(),
                'updated_at' => $bounds->last_at ?? now(),
            ]);

            // seq is left null for adopted rows — created_at is the only
            // ordering information that exists for them, and the reader falls
            // back to it. New messages get a real seq from here on.
            DB::table('ai_chat_messages')
                ->where('user_id', $userId)
                ->whereNull('session_id')
                ->update(['session_id' => $sessionId]);
        }
    }
};
