<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * public/css/hims.css is the whole domain UI — 1,500 hand-authored lines served
 * straight out of public/, deliberately outside the Vite build. That choice costs
 * one thing, and it is not obvious: a Vite bundle's filename contains its own
 * hash, so a new build is a new URL, while this file's URL is constant forever.
 *
 * The deployed origin sends `Cache-Control: max-age=14400` and sits behind
 * Cloudflare. A constant URL plus a four-hour max-age means that for four hours
 * after a release, the browser and the shared edge cache both keep the *previous*
 * stylesheet while the origin serves the *new* HTML.
 *
 * The failure that produces is quiet, which is what makes it worth a test. The
 * page is not unstyled — everything that existed before the release still looks
 * right, because its rules are in the cached copy. Only markup whose classes are
 * new renders bare. v2.5.0-beta.3 added .hims-tabs, .hims-modal, .hims-checklist
 * and #ai-rail in a single commit, so on deploy the Learning and Recognition tab
 * strips rendered as underlined links, and the AI rail — with no position:fixed
 * to lift it out of flow — laid out at the foot of the document, where the last
 * line of openRail(), `input.focus()`, scrolled the page to the bottom rather
 * than opening a panel. Three unrelated-looking bug reports, one stale file.
 *
 * The favicon hit the same wall a release earlier and was fixed the same way, so
 * the fix was already house style; it had simply never been applied to the file
 * that changes on every release. These assertions are static and cheap, and they
 * exist because nothing else in the stack notices: the file is correct on disk,
 * correct in the repo, correct in the container, and correct in every local
 * test — it is only wrong in the one place nobody can grep.
 */
class AssetCacheBustingContractTest extends TestCase
{
    /** The one partial allowed to name the stylesheet. */
    private const PARTIAL = 'resources/views/partials/app-css.blade.php';

    /**
     * Every standalone <head> in the app. The Breeze layouts and welcome page are
     * absent on purpose — they load app.css through @vite, which hashes filenames
     * itself and so cannot go stale this way.
     */
    private const DOCUMENTS = [
        'resources/views/layouts/hims.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/auth/forgot-password.blade.php',
        'resources/views/auth/reset-password.blade.php',
    ];

    /** The rendered link must carry the running file's own hash, not just any query. */
    public function test_the_stylesheet_url_is_keyed_to_the_file_contents(): void
    {
        $expected = substr((string) md5_file(public_path('css/hims.css')), 0, 8);

        $html = view('partials.app-css')->render();

        $this->assertMatchesRegularExpression('#/css/hims\.css\?v=[0-9a-f]{8}#', $html,
            'The stylesheet link has lost its cache-busting query. A constant URL behind '
            .'Cache-Control: max-age=14400 and Cloudflare means the next release ships new '
            .'markup to clients still holding the previous stylesheet.');

        $this->assertStringContainsString('/css/hims.css?v='.$expected, $html,
            'The version in the link is not this file\'s md5. A hash that does not track the '
            .'contents is a version number someone has to remember to bump — which is the '
            .'failure mode, not the fix.');
    }

    /**
     * A hardcoded version is the trap this replaces: it looks identical in the
     * rendered HTML and stops working the first time someone edits the CSS
     * without editing the number.
     */
    public function test_the_version_is_derived_from_the_file_not_written_by_hand(): void
    {
        $partial = (string) file_get_contents(base_path(self::PARTIAL));

        $this->assertStringContainsString('md5_file', $partial,
            'The stylesheet version must be computed from the file itself.');

        $this->assertStringContainsString('is_file(', $partial,
            'Guard the hash behind is_file() so a checkout missing public/css/hims.css renders '
            .'rather than throwing.');
    }

    /** One definition, four documents — the reason this is a partial at all. */
    public function test_every_standalone_head_loads_the_stylesheet_through_the_partial(): void
    {
        foreach (self::DOCUMENTS as $relativePath) {
            $source = (string) file_get_contents(base_path($relativePath));

            $this->assertStringContainsString("@include('partials.app-css')", $source,
                "{$relativePath} has its own <head> and must pull the stylesheet from the shared "
                .'partial, or its URL will drift from the other three.');
        }
    }

    /**
     * The regression itself: any view that writes the link by hand reintroduces an
     * unversioned URL for whichever pages it serves, and the app would look
     * correct everywhere else while that one path went stale.
     */
    public function test_no_view_writes_an_unversioned_stylesheet_link(): void
    {
        $offenders = [];
        $partial = str_replace('\\', '/', base_path(self::PARTIAL));

        foreach ($this->viewFiles() as $path) {
            if (str_replace('\\', '/', $path) === $partial) {
                continue;
            }

            foreach (file($path, FILE_IGNORE_NEW_LINES) as $i => $line) {
                // A link that names the stylesheet but appends no query string.
                if (preg_match('/asset\(\s*[\'"]css\/hims\.css[\'"]\s*\)(?!\s*\.)/', $line)) {
                    $offenders[] = $this->relative($path).':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders,
            "These views link public/css/hims.css with no cache-busting version:\n  "
            .implode("\n  ", $offenders)
            ."\nUse @include('partials.app-css') instead — a bare asset() URL never changes, so "
            .'clients keep the stylesheet they cached before the release.');
    }

    /**
     * The favicon fix this one generalises. Kept here rather than in its own file
     * because the two links are the app's only unbuilt static assets, and the
     * reasoning is identical: content-keyed URL or nothing.
     */
    public function test_the_favicon_stays_content_hashed(): void
    {
        $html = view('partials.favicon')->render();

        $this->assertMatchesRegularExpression('#/favicon\.ico\?v=[0-9a-f]{8}#', $html,
            'The favicon link has lost its content hash. Browsers cache favicons harder than '
            .'any other asset — Chrome keeps them in a store a hard reload does not clear.');
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
