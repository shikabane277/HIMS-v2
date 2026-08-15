<?php

namespace Tests\Unit;

use App\Support\CredentialStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the one thing CredentialStatus exists to guarantee: that the PHP
 * answer and the SQL answer are the same answer.
 *
 * The class has two implementations of one decision — of() for PHP and
 * caseSql() for queries — because a status derived on every read cannot be
 * computed in only one place and still be usable both in a Blade loop and in a
 * GROUP BY. Two implementations of one rule is exactly the shape that drifts,
 * and this is the class whose drift previously produced a dashboard where a
 * credential was counted as valid and expiring at the same time.
 *
 * The stakes are no longer cosmetic. ScanCredentialExpiry raises alerts off
 * these predicates, so a divergence does not merely make two tiles disagree —
 * it warns a nurse about a licence the screen calls fine, or stays silent about
 * one it calls expiring. The boundary days are where that happens, so those are
 * what is enumerated below rather than a comfortable date in the middle.
 */
class CredentialStatusTest extends TestCase
{
    /**
     * Offsets in days from today, and the status each must produce.
     *
     * Expressed relative to WINDOW_DAYS rather than the literal 30, so widening
     * the warning window moves the boundary cases with it instead of turning
     * every one of them red.
     */
    public static function boundaryDates(): array
    {
        $window = CredentialStatus::WINDOW_DAYS;

        return [
            'long lapsed' => [-400, CredentialStatus::EXPIRED],
            'lapsed yesterday' => [-1, CredentialStatus::EXPIRED],
            'expires today' => [0, CredentialStatus::EXPIRING],
            'mid-window' => [(int) ($window / 2), CredentialStatus::EXPIRING],
            'last day of the window' => [$window, CredentialStatus::EXPIRING],
            'day after the window' => [$window + 1, CredentialStatus::ACTIVE],
            'far future' => [400, CredentialStatus::ACTIVE],
        ];
    }

    #[DataProvider('boundaryDates')]
    public function test_php_and_sql_agree_on_every_boundary_date(int $offsetDays, string $expected): void
    {
        $date = now()->addDays($offsetDays)->toDateString();

        $this->assertSame($expected, CredentialStatus::of($date), "PHP disagreed for {$date}");
        $this->assertSame($expected, $this->statusViaSql($date), "SQL disagreed for {$date}");
    }

    /**
     * A credential expiring today is expiring, not expired.
     *
     * Called out separately because this is the case the original inline
     * queries got wrong: they compared a DATE column against a DATETIME, so
     * today's implied 00:00 sorted as already past and the credential silently
     * left the "expiring soon" list partway through the morning — on the very
     * day somebody most needed to see it.
     */
    public function test_a_credential_expiring_today_is_expiring_not_expired(): void
    {
        $today = now()->toDateString();

        $this->assertSame(CredentialStatus::EXPIRING, CredentialStatus::of($today));
        $this->assertSame(CredentialStatus::EXPIRING, $this->statusViaSql($today));
    }

    public function test_a_missing_expiry_date_is_no_expiry_in_both_paths(): void
    {
        $this->assertSame(CredentialStatus::NO_EXPIRY, CredentialStatus::of(null));
        $this->assertSame(CredentialStatus::NO_EXPIRY, CredentialStatus::of(''));
        $this->assertSame(CredentialStatus::NO_EXPIRY, $this->statusViaSql(null));
    }

    /** A stored DATETIME must read the same as the DATE it falls on. */
    public function test_a_datetime_is_truncated_to_its_date(): void
    {
        $todayAtNoon = now()->toDateString().' 12:00:00';

        $this->assertSame(CredentialStatus::EXPIRING, CredentialStatus::of($todayAtNoon));
    }

    /**
     * The four states partition the table.
     *
     * This is the property the old inline queries broke: "valid" was written as
     * expiry_date >= now(), which swallowed the expiring window, so a
     * credential was counted twice and the tiles on one page added up to more
     * than the number of credentials that existed. Summing the scopes back to
     * the row count is the cheapest way to keep that from returning.
     */
    public function test_the_scopes_partition_the_table(): void
    {
        $this->withCredentialTable(function () {
            $total = DB::table('test_credentials')->count();

            $expired = CredentialStatus::whereExpired(DB::table('test_credentials'))->count();
            $expiring = CredentialStatus::whereExpiring(DB::table('test_credentials'))->count();
            $active = CredentialStatus::whereActive(DB::table('test_credentials'))->count();
            $noExpiry = DB::table('test_credentials')->whereNull('expiry_date')->count();

            $this->assertSame(7, $total);
            $this->assertSame(2, $expired);
            $this->assertSame(2, $expiring);
            $this->assertSame(2, $active);
            $this->assertSame(1, $noExpiry);
            $this->assertSame(
                $total,
                $expired + $expiring + $active + $noExpiry,
                'The four scopes no longer partition the table — some rows are counted twice or not at all.'
            );
        });
    }

    /** The scopes and the CASE must select the same rows, not merely count alike. */
    public function test_the_scopes_and_the_case_expression_select_the_same_rows(): void
    {
        $this->withCredentialTable(function () {
            $viaCase = DB::table('test_credentials')
                ->selectRaw(CredentialStatus::caseSql('expiry_date').' as status, id', CredentialStatus::caseBindings())
                ->get()
                ->groupBy('status')
                ->map(fn ($rows) => $rows->pluck('id')->sort()->values()->all());

            $viaScopes = [
                CredentialStatus::EXPIRED => CredentialStatus::whereExpired(DB::table('test_credentials')),
                CredentialStatus::EXPIRING => CredentialStatus::whereExpiring(DB::table('test_credentials')),
                CredentialStatus::ACTIVE => CredentialStatus::whereActive(DB::table('test_credentials')),
            ];

            foreach ($viaScopes as $status => $query) {
                $this->assertSame(
                    $viaCase[$status] ?? [],
                    $query->pluck('id')->sort()->values()->all(),
                    "The {$status} scope and the CASE expression disagree about which rows qualify."
                );
            }
        });
    }

    public function test_days_remaining_counts_forward_and_backward(): void
    {
        $this->assertSame(0, CredentialStatus::daysRemaining(now()->toDateString()));
        $this->assertSame(10, CredentialStatus::daysRemaining(now()->addDays(10)->toDateString()));
        $this->assertSame(-3, CredentialStatus::daysRemaining(now()->subDays(3)->toDateString()));
        $this->assertNull(CredentialStatus::daysRemaining(null));
    }

    /** Every state must have a label and a badge colour; none may fall through to a blank. */
    public function test_every_state_has_a_label_and_a_badge_class(): void
    {
        foreach ([
            CredentialStatus::ACTIVE,
            CredentialStatus::EXPIRING,
            CredentialStatus::EXPIRED,
            CredentialStatus::NO_EXPIRY,
        ] as $state) {
            $this->assertNotSame('', CredentialStatus::label($state));
            $this->assertNotSame('', CredentialStatus::badgeClass($state));
        }

        $this->assertSame('Expiring soon', CredentialStatus::label(CredentialStatus::EXPIRING));
        $this->assertSame('yellow', CredentialStatus::badgeClass(CredentialStatus::EXPIRING));
    }

    /**
     * Runs caseSql() through the database rather than re-reading it in PHP.
     *
     * The date is inlined as the "column" expression so no table is needed —
     * what is under test is the CASE itself, and its two bindings still arrive
     * from caseBindings() in the order the SQL expects them.
     */
    private function statusViaSql(?string $date): string
    {
        $column = $date === null ? 'NULL' : "'".$date."'";

        return DB::selectOne(
            'SELECT '.CredentialStatus::caseSql($column).' AS status',
            CredentialStatus::caseBindings()
        )->status;
    }

    /** A throwaway table holding two rows in each state, plus one with no expiry. */
    private function withCredentialTable(callable $assertions): void
    {
        Schema::create('test_credentials', function (Blueprint $table) {
            $table->increments('id');
            $table->date('expiry_date')->nullable();
        });

        $window = CredentialStatus::WINDOW_DAYS;

        foreach ([-400, -1, 0, $window, $window + 1, 400] as $offset) {
            DB::table('test_credentials')->insert([
                'expiry_date' => now()->addDays($offset)->toDateString(),
            ]);
        }

        DB::table('test_credentials')->insert(['expiry_date' => null]);

        try {
            $assertions();
        } finally {
            Schema::drop('test_credentials');
        }
    }
}
