<?php

/**
 * Vercel Serverless Function entry point for Laravel (HIMS)
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

putenv('APP_STORAGE=' . $ephemeralStorage);
putenv('VIEW_COMPILED_PATH=' . $ephemeralStorage . '/framework/views');
putenv('APP_CONFIG_CACHE=/tmp/config.php');
putenv('APP_ROUTES_CACHE=/tmp/routes.php');
putenv('APP_EVENTS_CACHE=/tmp/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/packages.php');

if (file_exists(__DIR__ . '/hims-app/public/index.php')) {
    require __DIR__ . '/hims-app/public/index.php';
} else {
    require __DIR__ . '/public/index.php';
}
