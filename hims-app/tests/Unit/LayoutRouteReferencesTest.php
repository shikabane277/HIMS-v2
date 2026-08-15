<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every route('...') name used by the app shell must actually be registered.
 *
 * This exists because it already broke once: the topbar bell's "Mark all read"
 * button was wired to route('notifications.read-all') while no such route was
 * ever added, so layouts/hims.blade.php threw a RouteNotFoundException — on
 * every one of the 50 pages that extend it.
 *
 * Nothing caught it. The shell is only exercised when a domain view renders,
 * and every domain view needs MySQL, so on the sqlite connection phpunit uses
 * the entire shell went untested. A missing route name is a fatal error found
 * by opening any page, which makes it exactly the kind of thing to assert
 * statically rather than hope a feature test covers.
 *
 * Scanning the compiled Blade source keeps this DB-free and layout-agnostic.
 */
class LayoutRouteReferencesTest extends TestCase
{
    /** Views that make up the shell every authenticated page inherits. */
    private const SHELL_VIEWS = [
        'resources/views/layouts/hims.blade.php',
        'resources/views/partials/ai-rail.blade.php',
    ];

    public function test_every_referenced_route_name_exists(): void
    {
        $registered = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->all();

        $total = 0;

        foreach (self::SHELL_VIEWS as $relativePath) {
            $path = base_path($relativePath);

            $this->assertFileExists($path, "Shell view {$relativePath} is missing.");

            // route('name') and route("name"), with or without further arguments.
            preg_match_all('/\broute\(\s*[\'"]([^\'"]+)[\'"]/',
                (string) file_get_contents($path), $matches);

            $referenced = array_values(array_unique($matches[1]));
            $total += count($referenced);

            foreach ($referenced as $name) {
                $this->assertContains($name, $registered,
                    "{$relativePath} calls route('{$name}') but no route with that name is "
                    .'registered. Every page extending this view would throw RouteNotFoundException.');
            }
        }

        // A markup-only partial legitimately has none, but zero across the whole
        // shell would mean the regex stopped matching and this test went blind.
        $this->assertGreaterThan(0, $total,
            'No route() calls found in any shell view — the pattern may have gone stale.');
    }
}
