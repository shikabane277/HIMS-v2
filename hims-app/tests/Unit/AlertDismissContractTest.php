<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * `.hims-alert` carries two unrelated jobs, and the difference is invisible in
 * the markup: a flash message that should clear itself after a moment, and a
 * permanent explanatory banner that is part of the page.
 *
 * The layout used to auto-dismiss them by class — `querySelectorAll('.hims-alert')`
 * — so four seconds after every page load it deleted the frozen-review notice,
 * the compliance rule explainers, the account-coverage warning and every other
 * standing banner along with the flash it was aiming at. The page looked correct
 * in the response body and correct in a fresh `fetch`; only a real browser left
 * open past the timer showed the loss, which is why it survived so long.
 *
 * The fix made dismissal opt-in via `data-auto-dismiss`. That direction is the
 * safe one — a flash that forgets the marker merely lingers, whereas a banner
 * forgetting an opt-out vanishes silently — but it only holds while every flash
 * is marked and no banner is. Nothing in Blade enforces that, so it is asserted
 * here, statically, off the view sources. No database, no rendering.
 */
class AlertDismissContractTest extends TestCase
{
    /** An opening tag for an alert — not the word appearing in a JS or CSS comment. */
    private const ALERT_TAG = '/<div[^>]*\bclass="[^"]*\bhims-alert\b/';

    /** The three session keys the app flashes. A banner never reads these. */
    private const FLASH = '/session\(\s*[\'"](?:success|error|status)[\'"]/';

    /** Enough to clear the longest alert body in the app; stops a runaway window. */
    private const MAX_ELEMENT_LINES = 12;

    public function test_every_flash_alert_is_marked_and_every_banner_is_not(): void
    {
        $flashes = [];
        $banners = [];

        foreach ($this->viewFiles() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            $relative = $this->relative($path);

            foreach ($lines as $i => $line) {
                if (! preg_match(self::ALERT_TAG, $line)) {
                    continue;
                }

                $where = $relative.':'.($i + 1);
                $marked = str_contains($line, 'data-auto-dismiss');
                $body = $this->elementBody($lines, $i);

                if (preg_match(self::FLASH, $body)) {
                    $flashes[] = $where;
                    $this->assertTrue($marked,
                        "{$where} renders a session flash but has no data-auto-dismiss, so it "
                        .'stays on screen until the next navigation. Add the attribute to the '
                        .'opening tag.');

                    continue;
                }

                $banners[] = $where;
                $this->assertFalse($marked,
                    "{$where} is a standing banner — it reads no session flash — but carries "
                    .'data-auto-dismiss, so the layout will delete it four seconds after load. '
                    .'Only flash messages self-dismiss.');
            }
        }

        // Floors, not inventories: they exist so a regex that stopped matching
        // fails loudly instead of passing with nothing to check.
        $this->assertGreaterThanOrEqual(15, count($flashes),
            'Found only '.count($flashes).' flash alerts across the views — the scan has likely gone blind.');
        $this->assertGreaterThanOrEqual(20, count($banners),
            'Found only '.count($banners).' standing alerts across the views — the scan has likely gone blind.');
    }

    /**
     * Validation summaries are the case most likely to be marked by mistake:
     * they look like flashes and appear in the same slot, but the user is
     * reading them while fixing the form. Dismissing one mid-correction removes
     * the only statement of what was wrong.
     */
    public function test_validation_summaries_are_never_dismissed(): void
    {
        $checked = 0;

        foreach ($this->viewFiles() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $i => $line) {
                if (! preg_match(self::ALERT_TAG, $line)) {
                    continue;
                }

                $body = $this->elementBody($lines, $i);

                if (! str_contains($body, '$errors->') || preg_match(self::FLASH, $body)) {
                    continue;
                }

                $checked++;
                $this->assertStringNotContainsString('data-auto-dismiss', $line,
                    $this->relative($path).':'.($i + 1).' shows validation errors and must not '
                    .'self-dismiss — the user is reading it while correcting the form.');
            }
        }

        $this->assertGreaterThan(0, $checked, 'No validation-error alerts found — the scan has gone blind.');
    }

    /**
     * The two banners the review-freeze work depends on, named rather than left
     * to the blanket rule above, because losing either one silently removes the
     * only on-screen explanation of why a review can no longer be edited.
     */
    public function test_review_banners_persist(): void
    {
        $cases = [
            'resources/views/performance/show.blade.php' => 'so this review is final and can no longer be edited',
            'resources/views/performance/reviews/score.blade.php' => 'a finished review can still be edited',
        ];

        foreach ($cases as $relativePath => $sentence) {
            $lines = file(base_path($relativePath), FILE_IGNORE_NEW_LINES);
            $found = false;

            foreach ($lines as $i => $line) {
                if (! preg_match(self::ALERT_TAG, $line)) {
                    continue;
                }

                if (! str_contains($this->elementBody($lines, $i), $sentence)) {
                    continue;
                }

                $found = true;
                $this->assertStringNotContainsString('data-auto-dismiss', $line,
                    "The banner explaining the review freeze in {$relativePath} must stay on "
                    .'screen for as long as the page does.');
            }

            $this->assertTrue($found,
                "Could not find the review-freeze banner in {$relativePath} — if the wording "
                .'changed, update this test rather than deleting it.');
        }
    }

    /** The timer must select the marker, never the class it is applied to. */
    public function test_the_layout_dismisses_by_marker_not_by_class(): void
    {
        $layout = (string) file_get_contents(base_path('resources/views/layouts/hims.blade.php'));

        // Strip JS/Blade comments: the fix is explained in prose that names the
        // very selector being banned, and the ban is about code, not the notes.
        $code = preg_replace(['#//[^\n]*#', '#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', $layout);

        $this->assertStringContainsString("querySelectorAll('[data-auto-dismiss]')", $code,
            'The auto-dismiss timer is gone or no longer selects by marker. Flash messages '
            .'should clear themselves; if that is being removed, remove this test too.');

        $this->assertDoesNotMatchRegularExpression('/querySelectorAll\(\s*[\'"][^\'"]*\.hims-alert/', $code,
            'The layout selects alerts by class again. That deletes every standing banner in '
            .'the app four seconds after load — the exact bug data-auto-dismiss was added to fix.');
    }

    /**
     * `.hims-alert` was `display: flex`, which makes every child its own flex
     * item: a `<strong>` mid-sentence became its own column ("mark it |
     * finished | when you are done"), and a nested `<ul>` sat beside the prose
     * instead of under it. Alert bodies are sentences, so they lay out as prose;
     * the leading icon is spaced inline instead.
     */
    public function test_alerts_lay_out_as_prose(): void
    {
        $css = (string) file_get_contents(base_path('public/css/hims.css'));

        $this->assertTrue(
            (bool) preg_match('/^\.hims-alert\s*\{([^}]*)\}/m', $css, $rule),
            'The .hims-alert rule is missing from public/css/hims.css.'
        );

        $this->assertDoesNotMatchRegularExpression('/display\s*:\s*(flex|grid)/', $rule[1],
            'A flex or grid alert turns every inline child into its own column, breaking any '
            .'alert whose body is more than one plain sentence.');

        $this->assertStringContainsString('.hims-alert > i:first-child', $css,
            'The leading-icon spacing rule is what keeps a prose alert looking like the flex '
            .'one it replaced.');
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

    /**
     * The alert's markup, from its opening tag to its closing `</div>`.
     *
     * Alert bodies hold `<i>`, `<strong>`, `<em>` and `<ul>` but never a nested
     * `<div>`, so the first `</div>` is the right one; the line cap keeps an
     * unbalanced tag from swallowing the rest of the file.
     */
    private function elementBody(array $lines, int $start): string
    {
        $body = [];

        for ($i = $start, $end = min($start + self::MAX_ELEMENT_LINES, count($lines)); $i < $end; $i++) {
            $body[] = $lines[$i];

            if (str_contains($lines[$i], '</div>')) {
                break;
            }
        }

        return implode("\n", $body);
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
    }
}
