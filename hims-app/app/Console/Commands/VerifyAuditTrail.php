<?php

namespace App\Console\Commands;

use App\Support\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-computes the audit hash chain and reports the first row that does not
 * match — an edited, deleted or re-ordered entry. Exit code 0 means intact.
 *
 * If old rows were pruned on purpose, the first remaining row is trusted as
 * the anchor (its stored chain_hash is taken as given) and everything after it
 * is checked.
 */
class VerifyAuditTrail extends Command
{
    protected $signature = 'hims:audit-verify {--limit=0 : Stop after this many rows (0 = all)}';

    protected $description = 'Verify the tamper-evident hash chain of the audit trail';

    public function handle(): int
    {
        $legacy = DB::table('audit_trails')->whereNull('chain_seq')->count();
        $limit = max(0, (int) $this->option('limit'));

        $previous = null;
        $expectedSeq = null;
        $checked = 0;
        $problem = null;

        DB::table('audit_trails')
            ->whereNotNull('chain_seq')
            ->orderBy('chain_seq')
            ->chunk(500, function ($rows) use (&$previous, &$expectedSeq, &$checked, &$problem, $limit) {
                foreach ($rows as $row) {
                    $seq = (int) $row->chain_seq;

                    if ($previous === null) {
                        // Anchor: genesis for seq 1, otherwise the first surviving row.
                        if ($seq === 1) {
                            $expected = hash('sha256', AuditTrail::GENESIS.AuditTrail::contentHash($row));
                            if (! hash_equals($expected, (string) $row->chain_hash)) {
                                $problem = "Row seq {$seq} ({$row->audit_id}) does not match its contents.";

                                return false;
                            }
                        } else {
                            $this->warn("Chain starts at seq {$seq}: earlier rows were pruned; verifying from there.");
                        }
                    } else {
                        if ($seq !== $expectedSeq) {
                            $problem = "Gap in chain: expected seq {$expectedSeq}, found {$seq} — row(s) deleted.";

                            return false;
                        }

                        $expected = hash('sha256', $previous.AuditTrail::contentHash($row));
                        if (! hash_equals($expected, (string) $row->chain_hash)) {
                            $problem = "Row seq {$seq} ({$row->audit_id}, {$row->action} on {$row->resource_type}) was altered.";

                            return false;
                        }
                    }

                    foreach (['before_state' => 'before_state_hash', 'after_state' => 'after_state_hash'] as $col => $hashCol) {
                        if ($row->{$hashCol} !== null) {
                            $state = json_decode((string) $row->{$col}, true);
                            if (! hash_equals((string) $row->{$hashCol}, hash('sha256', AuditTrail::canonical($state)))) {
                                $problem = "Row seq {$seq} ({$row->audit_id}): {$col} was altered.";

                                return false;
                            }
                        }
                    }

                    $previous = (string) $row->chain_hash;
                    $expectedSeq = $seq + 1;
                    $checked++;

                    if ($limit && $checked >= $limit) {
                        return false;
                    }
                }
            });

        if ($problem) {
            $this->error('AUDIT CHAIN BROKEN: '.$problem);

            return self::FAILURE;
        }

        $head = DB::table('system_settings')->where('key', AuditTrail::HEAD_KEY)->value('value');
        if ($previous !== null && $head && ! $limit) {
            [$headSeq, $headHash] = explode(':', $head, 2);
            if ((int) $headSeq !== $expectedSeq - 1 || ! hash_equals($headHash, $previous)) {
                $this->error('AUDIT CHAIN BROKEN: newest row(s) after seq '.($expectedSeq - 1).' were deleted (head says seq '.$headSeq.').');

                return self::FAILURE;
            }
        }

        $this->info("Audit chain intact: {$checked} chained row(s) verified.".($legacy ? " {$legacy} legacy row(s) predate chaining and are not covered." : ''));

        return self::SUCCESS;
    }
}
