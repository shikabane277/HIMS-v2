<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Below 768px a HIMS table is not a table. `thead` is hidden, every `tr` becomes
 * a bordered card and every `td` prints its own heading from
 * `content: attr(data-label)` — so the label in the markup is the only thing
 * naming the value once the header row is gone.
 *
 * What that replaced was worse, and worth stating because it is the reason the
 * convention exists at all. The mobile block used to set
 * `.hims-table { min-width: 520px }` with `overflow-x: auto` on the card body
 * beside it, which did not make wide tables scrollable — it guaranteed *every*
 * table in the app was wider than the phone reading it, and then handed the
 * reader a sideways scrollbar as compensation. A horizontally clipped table
 * gives no signal that anything exists to its right: the gap-analysis table cut
 * off after its third column looks exactly like a table that has three columns,
 * and the two that decide whether a row needs acting on — Below Requirement and
 * Suggested Response — are the ones that vanish.
 *
 * The stacking fix removes that, and buys a new failure mode in exchange. It is
 * invisible at the width developers work at: a `td` added without `data-label`
 * looks perfect on a desktop, where the real `th` above it supplies the heading,
 * and renders on a phone as a bare value under nothing at all. Nobody resizes a
 * browser to check a column they just added, so this asserts it statically off
 * the sources instead. No database, no rendering, no browser.
 *
 * Scoped per `.hims-table` element rather than per file, unlike its sibling
 * `TableAlignmentContractTest`: `data-label` is read by exactly one selector,
 * and four views (`employees/show`, `performance/show`, `training/sessions/show`,
 * `training/venues/index`) also carry a plain `<table style="width:100%">` used
 * as a two-column key/value list. Those cells are never stacked, so a label on
 * one would be markup nothing reads — the same defect this file bans on
 * `colspan` cells.
 */
class ResponsiveTableContractTest extends TestCase
{
    /** A `.hims-table` element, captured with its body. */
    private const TABLE = '#<table\b[^>]*\bhims-table\b[^>]*>(.*?)</table>#is';

    /** An opening `<td>` tag with its attributes. */
    private const CELL = '/<td\b([^>]*)>/i';

    /** A header cell, captured with its text. */
    private const HEADER = '#<th\b[^>]*>(.*?)</th>#is';

    /** The mobile heading itself. Every occurrence in the views is double-quoted. */
    private const LABEL = '/\bdata-label\s*=\s*"([^"]*)"/i';

    /**
     * Headings with no `th` text to match, because the header is deliberately
     * blank. Derived by reading the ten views that have one — eleven such columns,
     * because `succession/candidates/show` has two. Ten are a trailing action
     * column taking this exemption: `dashboard/partials/staff`,
     * `learning/accounts`, `learning/assignments/index`,
     * `learning/assignments/show`, `learning/courses/show`, `learning/cpd/index`,
     * `performance/cycles/show`, `succession/candidates/show`,
     * `succession/positions/index` and `succession/positions/show`. The eleventh
     * is that last-but-two file's evidence table, whose blank-headed gap column is
     * labelled "Status" and is exempt by a different route — it matches the `th`
     * text of the milestone table beside it, as the third test below explains.
     *
     * The blank `<th>` takes three shapes, so grepping for one spelling finds a
     * subset rather than the set: a bare `<th></th>`, an empty width-setting
     * `<th style="width:…">`, and a gated one —
     * `@can('record-completion')<th class="text-end"></th>@endcan` in
     * `learning/courses/show`, `@if($canVerify)<th></th>@endif` in
     * `learning/cpd/index`. Where the header is gated the cell beneath it is gated
     * on the same condition, which is what keeps the two halves of the column
     * appearing and disappearing together.
     *
     * A column headed by a blank cell on a desktop still needs a name on a phone,
     * where the buttons in it are no longer the last thing in a row.
     */
    private const HEADERLESS_LABELS = ['Actions'];

    /**
     * The rule the whole section rests on. A cell with no heading is not
     * degraded on a phone, it is unidentified: the value is printed with the
     * label column beside it empty, and nothing on screen says what it is.
     *
     * `colspan` cells are exempt because the CSS exempts them — an empty-state
     * sentence spanning the table belongs to no column, so it stays one block
     * with nothing in front of it.
     */
    public function test_every_data_cell_carries_a_mobile_heading(): void
    {
        $offenders = [];

        foreach ($this->himsTables() as [$relative, $body]) {
            preg_match_all(self::CELL, $body, $cells, PREG_SET_ORDER);

            foreach ($cells as [$whole, $attributes]) {
                if (preg_match('/\bcolspan\b/i', $attributes) || preg_match(self::LABEL, $attributes)) {
                    continue;
                }

                $offenders[] = $relative.': '.trim($whole);
            }
        }

        $this->assertSame([], $offenders,
            "These .hims-table cells have no data-label:\n  ".implode("\n  ", $offenders)
            ."\nBelow 768px the header row is hidden and each cell prints its own heading from "
            .'attr(data-label), so one of these renders on a phone as a value under no heading at '
            .'all — correct on every desktop, unreadable on the device most staff use. Add '
            .'data-label="<the column\'s th text>" to the cell.');
    }

    /**
     * The other direction, and it matters because dead markup is indistinguishable
     * from working markup. `.hims-table td[colspan]::before { content: none }`
     * suppresses the heading on a spanning cell, so a `data-label` there prints
     * nowhere — while looking, to the next reader, like the convention being
     * followed. That is how a labelling pass gets recorded as complete over cells
     * it never covered.
     */
    public function test_a_spanning_cell_does_not_carry_a_heading_the_css_suppresses(): void
    {
        $offenders = [];

        foreach ($this->himsTables() as [$relative, $body]) {
            preg_match_all(self::CELL, $body, $cells, PREG_SET_ORDER);

            foreach ($cells as [$whole, $attributes]) {
                if (preg_match('/\bcolspan\b/i', $attributes) && preg_match(self::LABEL, $attributes)) {
                    $offenders[] = $relative.': '.trim($whole);
                }
            }
        }

        $this->assertSame([], $offenders,
            "These spanning cells carry a data-label the CSS never prints:\n  "
            .implode("\n  ", $offenders)
            ."\n.hims-table td[colspan]::before sets content: none, because a row spanning the "
            .'whole table belongs to no column. Remove the attribute — left in place it reads as '
            .'a labelled cell to anyone auditing the next table.');
    }

    /**
     * A heading that names the wrong column is the mistake this catches, and it
     * is the likely one: the labels were added by copying header text down a
     * `<tr>`, so an off-by-one slip produces a card whose values sit under
     * plausible, adjacent, wrong names. On a desktop nothing shows.
     *
     * Compared against the `th` text anywhere in the same file rather than in the
     * same table, for the reason its sibling gives for the alignment pairing:
     * matching a cell to its own header means counting through `@can`-wrapped
     * headers, `colspan`s and conditional columns, and a parser that fragile
     * fails on edits that are fine. Two tables in one file share a vocabulary
     * — `succession/candidates/show` labels its evidence table's blank-headed
     * gap column "Status", which is the word its milestone table heads a column
     * with — and a typo is caught either way.
     */
    public function test_every_mobile_heading_names_a_real_column(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $path) {
            $source = (string) file_get_contents($path);

            if (! str_contains($source, 'hims-table')) {
                continue;
            }

            $headers = [];
            preg_match_all(self::HEADER, $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $headers[] = $this->text($match[1]);
            }

            $relative = $this->relative($path);

            foreach ($this->himsTables($path) as [, $body]) {
                preg_match_all(self::CELL, $body, $cells, PREG_SET_ORDER);

                foreach ($cells as [, $attributes]) {
                    if (! preg_match(self::LABEL, $attributes, $label)) {
                        continue;
                    }

                    $value = trim($label[1]);

                    if (in_array($value, self::HEADERLESS_LABELS, true)) {
                        continue;
                    }

                    foreach ($headers as $header) {
                        if (strcasecmp($header, $value) === 0) {
                            continue 2;
                        }
                    }

                    $offenders[$relative.': data-label="'.$value.'"'] = true;
                }
            }
        }

        $this->assertSame([], array_keys($offenders),
            "These data-labels match no table header in their own file:\n  "
            .implode("\n  ", array_keys($offenders))
            ."\nA label is the phone's copy of the th above it, so the two say the same words or "
            .'one of them is wrong — most likely a label copied from the neighbouring column. If '
            .'the column is genuinely headed by an empty <th>, add the label to '
            .'HEADERLESS_LABELS in this file with a note saying which view needs it.');
    }

    /**
     * The two halves of the mechanism, asserted together because either alone is
     * worse than neither. Labels with no stacking prints nothing and changes
     * nothing; stacking with no labels turns every table in the app into a
     * column of anonymous values. Only the pair is a readable table.
     */
    public function test_the_mobile_block_hides_the_header_row_and_prints_the_labels(): void
    {
        $mobile = $this->mobileBlock();

        $hidden = false;

        foreach ($this->rules($mobile) as [$selector, $declarations]) {
            if (str_contains($selector, '.hims-table thead')
                && preg_match('/display\s*:\s*none/', $declarations)) {
                $hidden = true;
                break;
            }
        }

        $this->assertTrue($hidden,
            'The @media (max-width: 768px) block no longer hides .hims-table thead. Without that '
            .'the header row stacks as a card of its own above every record — column names with '
            .'no values, then values labelled twice.');

        $this->assertStringContainsString('attr(data-label)', $mobile,
            'Nothing in the @media (max-width: 768px) block reads attr(data-label) any more, so '
            .'every data-label in the views is inert and each stacked cell prints a bare value '
            .'under no heading. The labels and the ::before that renders them ship together.');
    }

    /**
     * The regression itself, pinned from the CSS side so it cannot be reasoned
     * back in as a fix for a wide table. The two declarations worked as a pair:
     * `min-width: 520px` made every table wider than the viewport whatever it
     * held, and `overflow-x: auto` on the card body around it turned that into a
     * scrollbar — so the columns past the fold were not clipped by accident, they
     * were clipped by construction, and the scrollbar made it look intentional.
     *
     * Scoped to the mobile block for the card-body half: the one surviving
     * `overflow-x: auto` on `.hims-card .card-body` is keyed to
     * `body.ai-rail-docked` and is correct — docking the AI rail narrows a
     * desktop column that is still laid out as a grid.
     */
    public function test_the_horizontal_scroll_regression_cannot_return(): void
    {
        $offenders = [];

        foreach ($this->rules($this->css()) as [$selector, $declarations]) {
            if (str_contains($selector, '.hims-table') && preg_match('/(^|[\s;])min-width\s*:/', $declarations)) {
                $offenders[] = $selector;
            }
        }

        $this->assertSame([], $offenders,
            'These rules put a min-width back on a HIMS table: '.implode(', ', $offenders)
            .'. A floor wider than a phone makes overflow the guaranteed state rather than the '
            .'rare one, and the columns that fall off the right are invisible rather than cut '
            .'off — a table clipped after three columns reads as a table with three columns. '
            .'Below 768px a table stacks; it does not need a width.');

        $scrollers = [];

        foreach ($this->rules($this->mobileBlock()) as [$selector, $declarations]) {
            if (str_contains($selector, 'card-body') && preg_match('/overflow-x\s*:\s*auto/', $declarations)) {
                $scrollers[] = $selector;
            }
        }

        $this->assertSame([], $scrollers,
            'The mobile block scrolls a card body horizontally again: '.implode(', ', $scrollers)
            .'. That was the other half of the defect — the scrollbar that made a guaranteed '
            .'overflow look deliberate. Below 768px every value must be reachable by scrolling '
            .'down; nothing may ask for a sideways swipe.');
    }

    /**
     * Floors, not inventories: they exist so a regex that stopped matching fails
     * loudly instead of passing with nothing to check — which is the failure mode
     * that matters most here, because every assertion above is a scan for
     * absence. Measured at the time of writing: 35 views, 63 `.hims-table`
     * elements and 322 labelled cells.
     */
    public function test_the_scan_still_sees_the_tables_it_describes(): void
    {
        $views = 0;
        $tables = 0;
        $labelled = 0;

        foreach ($this->viewFiles() as $path) {
            $source = (string) file_get_contents($path);

            if (! str_contains($source, 'hims-table')) {
                continue;
            }

            $views++;

            foreach ($this->himsTables($path) as [, $body]) {
                $tables++;
                $labelled += preg_match_all(self::LABEL, $body);
            }
        }

        $this->assertGreaterThanOrEqual(30, $views,
            "Scanned only {$views} views containing a hims-table — the scan has likely gone blind.");
        $this->assertGreaterThanOrEqual(40, $tables,
            "Matched only {$tables} .hims-table elements. If the class was renamed, update the "
            .'selector in this test and in public/css/hims.css together.');
        $this->assertGreaterThanOrEqual(200, $labelled,
            "Found only {$labelled} labelled table cells across the views — the scan has likely "
            .'gone blind, and every assertion in this file passes on an empty set.');
    }

    /**
     * Every `.hims-table` in the app as `[relative path, inner markup]` pairs, or
     * just those in one file. Region-scoped rather than file-scoped so the four
     * plain key/value tables stay out of it; all 67 `<table>` tags in the views
     * open and close in the same file, so a non-greedy match to `</table>` is
     * enough without a real parser.
     */
    private function himsTables(?string $only = null): array
    {
        $tables = [];

        foreach ($only === null ? $this->viewFiles() : [$only] as $path) {
            $source = (string) file_get_contents($path);

            if (! str_contains($source, 'hims-table')) {
                continue;
            }

            preg_match_all(self::TABLE, $source, $regions, PREG_SET_ORDER);

            foreach ($regions as $region) {
                $tables[] = [$this->relative($path), $region[1]];
            }
        }

        return $tables;
    }

    /** The `@media (max-width: 768px)` block's own declarations. */
    private function mobileBlock(): string
    {
        $css = $this->css();

        $this->assertTrue(
            (bool) preg_match('/@media\s*\(\s*max-width\s*:\s*768px\s*\)\s*\{/', $css, $open, PREG_OFFSET_CAPTURE),
            'public/css/hims.css has no @media (max-width: 768px) block. That block is the entire '
            .'mobile table treatment — without it the labels in the views render nowhere.'
        );

        // Brace-matched, not regex-bounded: the block holds a dozen nested rules,
        // so the first `}` after it closes a rule and not the media query.
        $start = $open[0][1] + strlen($open[0][0]);
        $depth = 1;

        for ($i = $start, $length = strlen($css); $i < $length && $depth > 0; $i++) {
            $depth += ['{' => 1, '}' => -1][$css[$i]] ?? 0;
        }

        return substr($css, $start, $i - $start - 1);
    }

    /** Every `selector { declarations }` pair in a chunk of CSS. */
    private function rules(string $css): array
    {
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        return array_map(fn (array $rule): array => [trim($rule[1]), $rule[2]], $matches);
    }

    private function css(): string
    {
        $css = (string) file_get_contents(base_path('public/css/hims.css'));

        // Strip comments before matching, exactly as TableAlignmentContractTest
        // does and for the same reason: the section is explained in prose that
        // quotes the declarations being asserted on — including the literal
        // `.hims-table { min-width: 520px }` this file bans — so the assertions
        // are about code, not about the notes.
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** A header cell's visible words, with icons, entities and spacing normalised. */
    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
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
