<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Global search returns destinations, and eight of them are deep links: the URL
 * carries a fragment naming one row, and the view is expected to have an element
 * with that id so the browser scrolls to it. GlobalSearchController is the only
 * writer of those fragments; the views are the only readers.
 *
 * v2.5.0-beta.3 added the search endpoint and the eight anchors together, and
 * three of the eight were attached to the wrong element. Two went on a table's
 * <thead> header row instead of the <tbody> data row, and one went on the KPI
 * Scores row of performance/show while naming the goals loop's variable. All
 * three therefore printed a variable that did not exist at that point in the
 * template, which is a fatal ErrorException, not a blank:
 *
 *   learning/cpd/index          $cpd in <thead>          — 500 on every request
 *   competency/domains/show     $competency in <thead>   — 500 once a category exists
 *   performance/show            $g in the $k loop        — 500 once a KPI is scored
 *
 * Three live pages were down. The suite was green, and would have stayed green:
 * learning.cpd.index and competency.domains.show are rendered by no test at all,
 * and performance.show is rendered only by ReviewAuthorityTest, which gates
 * itself to MySQL — so `php artisan test` reported it as a skip. A page that no
 * test renders cannot be protected by a rendering test that does not exist, which
 * is why this check is static: it needs no database, no fixtures and no MySQL,
 * so it runs in the default sqlite suite where the gap actually was.
 *
 * The rule it enforces is the one all three violated: an id built from a row's
 * own field must sit inside the loop that binds that row. Scope is checked
 * against the nearest enclosing @foreach/@forelse rather than by parsing the
 * template, because that is precisely the mistake being guarded — the anchor
 * drifting out of its loop, or onto a header row that has no loop at all.
 */
class SearchAnchorContractTest extends TestCase
{
    private const CONTROLLER = 'app/Http/Controllers/GlobalSearchController.php';

    /**
     * Every fragment global search emits must exist as an id in some view.
     * A deep link to an anchor no template renders is a silent no-op: the page
     * loads at the top and the row the user picked is never highlighted.
     */
    public function test_every_search_fragment_has_a_view_that_renders_it(): void
    {
        $missing = [];

        foreach ($this->searchFragments() as $prefix) {
            if ($this->anchorSites($prefix) === []) {
                $missing[] = $prefix;
            }
        }

        $this->assertSame([], $missing,
            'GlobalSearchController deep-links to these fragments, but no view renders a '
            ."matching id:\n  ".implode("\n  ", $missing)
            ."\nEither add the anchor to the row's element or stop appending the fragment — a "
            .'link to a missing anchor lands the user at the top of the page with no indication '
            .'which row they asked for.');
    }

    /**
     * The regression itself. An anchor whose expression names a variable the
     * enclosing loop does not bind is a fatal undefined-variable error, so this
     * is a 500 on the whole page rather than one wrong href.
     */
    public function test_every_search_anchor_sits_inside_the_loop_that_binds_its_row(): void
    {
        $offenders = [];

        foreach ($this->searchFragments() as $prefix) {
            foreach ($this->anchorSites($prefix) as $site) {
                if ($site['variable'] !== $site['loopVariable']) {
                    $offenders[] = sprintf(
                        '%s:%d — id="%s..." prints %s, but the nearest enclosing loop binds %s',
                        $site['file'], $site['line'], $prefix, $site['variable'],
                        $site['loopVariable'] ?? 'nothing (the element is outside any loop)'
                    );
                }
            }
        }

        $this->assertSame([], $offenders,
            "These search anchors print a variable their enclosing loop does not bind:\n  "
            .implode("\n  ", $offenders)
            ."\nBlade compiles this to a bare PHP variable read, so it is an ErrorException and "
            .'the entire page returns 500 — not a missing id. Move the id onto the row inside '
            .'the loop; a <thead> row is never the right host for a per-record anchor.');
    }

    /** Sanity floor: a regex that matches nothing would make both tests vacuous. */
    public function test_the_scan_actually_finds_the_known_anchors(): void
    {
        $fragments = $this->searchFragments();

        $this->assertGreaterThanOrEqual(8, count($fragments),
            'Expected at least the eight known search fragments. Fewer means the pattern that '
            .'reads them out of GlobalSearchController has stopped matching, and both checks '
            .'above are passing on an empty list.');

        $sites = 0;
        foreach ($fragments as $prefix) {
            $sites += count($this->anchorSites($prefix));
        }

        $this->assertGreaterThanOrEqual(8, $sites,
            'Expected to locate at least eight anchor sites across the views.');
    }

    /** Fragment prefixes global search appends, e.g. "review-goal-". */
    private function searchFragments(): array
    {
        $source = (string) file_get_contents(base_path(self::CONTROLLER));

        preg_match_all("/\.'#([a-z-]+-)'\./", $source, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Every place a view builds an id from this prefix, with the loop variable in
     * scope at that line. The nearest preceding @foreach/@forelse is the scope
     * test on purpose — see the class docblock.
     *
     * @return array<int, array{file: string, line: int, variable: string, loopVariable: ?string}>
     */
    private function anchorSites(string $prefix): array
    {
        $sites = [];
        $pattern = '/id="'.preg_quote($prefix, '/').'\{\{\s*(\$\w+)/';

        foreach ($this->viewFiles() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $i => $line) {
                if (! preg_match($pattern, $line, $found)) {
                    continue;
                }

                $sites[] = [
                    'file' => $this->relative($path),
                    'line' => $i + 1,
                    'variable' => $found[1],
                    'loopVariable' => $this->enclosingLoopVariable($lines, $i),
                ];
            }
        }

        return $sites;
    }

    /** The variable bound by the nearest loop above this line, if any. */
    private function enclosingLoopVariable(array $lines, int $from): ?string
    {
        for ($i = $from; $i >= 0; $i--) {
            if (preg_match('/@(?:foreach|forelse)\(.*\bas\s+(\$\w+)\s*\)/', $lines[$i], $found)) {
                return $found[1];
            }
        }

        return null;
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
