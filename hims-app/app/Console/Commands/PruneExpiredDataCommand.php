<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GDPR Article 5(1)(e) — Storage Limitation & Automated Data Retention Policy.
 *
 * Purges expired authentication tokens, one-time passwords, stale AI chat
 * transcripts, and aged audit logs beyond their lawful retention period.
 */
class PruneExpiredDataCommand extends Command
{
    protected $signature = 'hims:prune-expired-data
                            {--chat-days=90 : Retention period in days for AI chat transcripts}
                            {--audit-days=365 : Retention period in days for audit trail logs}
                            {--dry-run : Report row counts without deleting}';

    protected $description = 'Enforce GDPR Article 5(1)(e) storage limitation by pruning expired OTPs, reset tokens, AI chats, and aged logs';

    public function handle(): int
    {
        $chatDays = max(1, (int) $this->option('chat-days'));
        $auditDays = max(30, (int) $this->option('audit-days'));
        $dryRun = (bool) $this->option('dry-run');

        $otpCutoff = now()->subDay();
        $chatCutoff = now()->subDays($chatDays);
        $auditCutoff = now()->subDays($auditDays);

        $stats = [
            'login_otps' => 0,
            'password_reset_tokens' => 0,
            'ai_chat_messages' => 0,
            'ai_chat_sessions' => 0,
            'audit_trails' => 0,
        ];

        if (Schema::hasTable('login_otps')) {
            $query = DB::table('login_otps')->where('created_at', '<', $otpCutoff);
            $stats['login_otps'] = $dryRun ? $query->count() : $query->delete();
        }

        if (Schema::hasTable('password_reset_tokens')) {
            $query = DB::table('password_reset_tokens')->where('created_at', '<', $otpCutoff);
            $stats['password_reset_tokens'] = $dryRun ? $query->count() : $query->delete();
        }

        if (Schema::hasTable('ai_chat_messages')) {
            $query = DB::table('ai_chat_messages')->where('created_at', '<', $chatCutoff);
            $stats['ai_chat_messages'] = $dryRun ? $query->count() : $query->delete();
        }

        if (Schema::hasTable('ai_chat_sessions')) {
            $query = DB::table('ai_chat_sessions')->where('updated_at', '<', $chatCutoff);
            $stats['ai_chat_sessions'] = $dryRun ? $query->count() : $query->delete();
        }

        if (Schema::hasTable('audit_trails')) {
            $query = DB::table('audit_trails')->where('created_at', '<', $auditCutoff);
            $stats['audit_trails'] = $dryRun ? $query->count() : $query->delete();
        }

        $mode = $dryRun ? '[DRY-RUN]' : '[PURGED]';
        $this->info("{$mode} GDPR Data Retention Summary: " . json_encode($stats));

        return self::SUCCESS;
    }
}

