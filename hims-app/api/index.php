<?php

/**
 * Vercel Serverless Function entry point for Laravel (HIMS)
 *
 * In Vercel's serverless runtime:
 * - The root file system is read-only.
 * - Only the `/tmp` directory is writable.
 *
 * This wrapper automatically ensures that Laravel's compiled view, cache,
 * and session directories exist in `/tmp` before handling HTTP requests.
 */

$ephemeralStorage = '/tmp/storage';
$requiredDirectories = [
    '/app',
    '/app/public',
    '/framework',
    '/framework/cache',
    '/framework/cache/data',
    '/framework/sessions',
    '/framework/views',
    '/logs',
];

foreach ($requiredDirectories as $dir) {
    $fullPath = $ephemeralStorage . $dir;
    if (!is_dir($fullPath)) {
        @mkdir($fullPath, 0755, true);
    }
}

// Ensure runtime environment variables are set for ephemeral paths
putenv('APP_STORAGE=' . $ephemeralStorage);
putenv('VIEW_COMPILED_PATH=' . $ephemeralStorage . '/framework/views');
putenv('APP_CONFIG_CACHE=/tmp/config.php');
putenv('APP_ROUTES_CACHE=/tmp/routes.php');
putenv('APP_EVENTS_CACHE=/tmp/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/packages.php');

// Forward execution to Laravel's public front controller
require __DIR__ . '/../public/index.php';
