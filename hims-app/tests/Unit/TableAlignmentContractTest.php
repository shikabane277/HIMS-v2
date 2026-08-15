<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Every table header in the app used to sit centred above left-aligned data.
 *
 * The cause was an omission, not a mistake: `.hims-table th` declared no
 * `text-align`, so it fell through to the user-agent default, which is `center`
 * for `th` and `left` for `td`. Bootstrap's reboot hides that with
 * `th { text-align: inherit }` — and HIMS loads no Bootstrap CSS, only the
 * bootstrap-icons font, so nothing supplied the missing declaration. All 59
 * `.hims-table` elements across 33 views were affected, which is why it read as
 * a systemic layout fault rather than one broken page.
 *
 * The fix is a single declaration in `public/css/hims.css`, so the risk now is
 * that it gets dropped or overridden again — invisibly, because a header still
 * looks deliberate when it is centred. This asserts the declaration and the
 * pairing rule that goes with it, statically, off the sources. No database, no
 * rendering, no browser.
 */
class TableAlignmentContractTest extends TestCase
{
    /** An opening `<th>` or `<td>` tag with its attributes. */
    private const CELL = '/<(th|td)\b([^>]*)>/i';

    /** The three alignment utilities in hims.css. `text-start` is the default. */
    private const ALIGN_CLASS = '/\btext-(center|end|start)\b/';

    /**
     * A header is the label of the column beneath it, so it starts where that
     * column starts. Left is not a style preference here — it is the alignment
     * of every `td` that has not opted out.
     */
    public function test_table_headers_declare_their_own_alignment(): void
    {
        $css = $this->css();

        $this->assertTrue(
            (bool) preg_match('/^\.hims-table th\s*\{([^}]*)\}/m', $css, $rule),
            'The .hims-table th rule is missing from public/css/hims.css.'
        );

        $this->assertMatchesRegularExpression('/text-align\s*:\s*left/', $rule[1],
            'public/css/hims.css no longer sets `text-align: left` on .hims-table th. Without it '
            .'the user-agent default applies — `center` for th, `left` for td — and every header '
            .'in the app goes back to sitting centred above its data. No Bootstrap CSS is loaded '
            .'to supply `th { text-align: inherit }` in its place.');
    }

    /**
     * The declaration above is only worth having if nothing later undoes it.
     * The responsive blocks at the end of the file already restyle
     * `.hims-table th` padding, so they are the likely place for a stray
     * `text-align` to land.
     */
    public function test_no_later_rule_re_centres_a_table_header(): void
    {
        $css = $this->css();
        $offenders = [];

        // Every rule whose selector list mentions .hims-table th, past the first.
        preg_match_all('/([^{}]*\.hims-table th[^{}]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach (array_slice($rules, 1) as $rule) {
            if (preg_match('/text-align\s*:\s*(center|right)/', $rule[2])) {
                $offenders[] = trim($rule[1]);
            }
        }

        $this->assertSame([], $offenders,
            'A later rule re-centres .hims-table th: '.implode(', ', $offenders).'. A header that '
            .'does not start where its column starts is the exact defect the base rule fixes.');
    }

    /**
     * A column that is not left-aligned says so on both its `th` and its `td`s.
     * Checked per view rather than per column: matching a header to its cells
     * by index means counting through `@can`-wrapped headers, `colspan`s and
     * conditional columns, and a parser that fragile fails on edits that are
     * fine. Comparing the *set* of alignments used on headers against the set
     * used on data cells in the same file catches the realistic mistake —
     * aligning one half of a column and forgetting the other — without
     * pretending to understand the markup.
     *
     * `colspan` cells are skipped: an empty-state row centred across the whole
     * table belongs to no column, so it has no header to agree with.
     */
    public function test_a_column_and_its_header_agree_on_alignment(): void
    {
        $checked = 0;

        foreach ($this->viewFiles() as $path) {
            $source = (string) file_get_contents($path);

            if (! str_contains($source, 'hims-table')) {
                continue;
            }

            $checked++;
            $headers = [];
            $data = [];

            preg_match_all(self::CELL, $source, $cells, PREG_SET_ORDER);

            foreach ($cells as [, $tag, $attributes]) {
                if (! preg_match(self::ALIGN_CLASS, $attributes, $align)) {
                    continue;
                }

                if (strtolower($tag) === 'th') {
                    $headers[] = $align[0];
                } elseif (! preg_match('/\bcolspan\b/i', $attributes)) {
                    $data[] = $align[0];
                }
            }

            $headers = array_values(array_unique($headers));
            $data = array_values(array_unique($data));
            $relative = $this->relative($path);

            $this->assertSame([], array_values(array_diff($headers, $data)),
                "{$relative} aligns a table header with ".implode(', ', array_diff($headers, $data))
                .' but no data cell in the file uses it. The header will not line up with the '
                .'column it labels — put the same class on the cells, or drop it from the header.');

            $this->assertSame([], array_values(array_diff($data, $headers)),
                "{$relative} aligns table data with ".implode(', ', array_diff($data, $headers))
                .' but no header does. Add the same class to that column\'s th.');
        }

        // A floor, not an inventory: it exists so a regex that stopped matching
        // fails loudly instead of passing with nothing to check.
        $this->assertGreaterThanOrEqual(30, $checked,
            "Scanned only {$checked} views containing a hims-table — the scan has likely gone blind.");
    }

    /**
     * Alignment is expressed with the shared utility classes, never inline.
     * Two reasons, and the first is load-bearing for the test above: an inline
     * `style="text-align:center"` is invisible to a class scan, so one cell
     * styled that way would silently exempt its column from the pairing check.
     * The second is that the convention is then greppable in one vocabulary —
     * the app had both spellings, which is how the columns that were paired
     * correctly stayed indistinguishable from the ones that were not.
     */
    public function test_cells_express_alignment_with_the_shared_classes(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $path) {
            $source = (string) file_get_contents($path);

            preg_match_all(self::CELL, $source, $cells, PREG_SET_ORDER);

            foreach ($cells as [$whole, , $attributes]) {
                if (preg_match('/text-align/i', $attributes)) {
                    $offenders[] = $this->relative($path).': '.trim($whole);
                }
            }
        }

        $this->assertSame([], $offenders,
            "These table cells set text-align inline:\n  ".implode("\n  ", $offenders)
            ."\nUse .text-center / .text-end / .text-start so the alignment of a column is "
            .'visible to a grep — and to the header/data pairing check in this file.');
    }

    /** The tables themselves, so a rename of `.hims-table` cannot pass silently. */
    public function test_the_app_still_has_the_tables_this_contract_describes(): void
    {
        $tables = 0;
        $headers = 0;

        foreach ($this->viewFiles() as $path) {
            $source = (string) file_get_contents($path);
            $tables += preg_match_all('/<table[^>]*\bclass="[^"]*\bhims-table\b/', $source);
            $headers += preg_match_all('/<th\b/i', $source);
        }

        $this->assertGreaterThanOrEqual(40, $tables,
            "Found only {$tables} .hims-table elements. If the class was renamed, update the "
            .'selector in this test and in public/css/hims.css together.');
        $this->assertGreaterThanOrEqual(250, $headers,
            "Found only {$headers} table headers across the views — the scan has likely gone blind.");
    }

    private function css(): string
    {
        $css = (string) file_get_contents(base_path('public/css/hims.css'));

        // Strip comments before matching. The fix is explained in prose that
        // quotes the very declarations being asserted on — including a literal
        // `th { text-align: inherit }` whose braces would end a rule body early
        // — so the assertions are about code, not about the notes.
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** Every Blade view in the app. */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
    }
}
