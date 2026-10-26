<?php

declare(strict_types=1);

/**
 * ArtAlly — Luxury Art Auction Institution
 * Bootstrap & Autoloader
 */

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DATA_PATH', STORAGE_PATH . '/data');
define('VIEWS_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');

// Ensure storage directories exist
foreach ([
    STORAGE_PATH,
    DATA_PATH,
    STORAGE_PATH . '/uploads/artworks',
    STORAGE_PATH . '/uploads/profiles',
    STORAGE_PATH . '/private/documents',
    STORAGE_PATH . '/sessions',
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// Configure default timezone
date_default_timezone_set('UTC');

// Load .env if present
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

// Simple PSR-4 Autoloader for App\ namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Load global helpers
require_once APP_PATH . '/Core/helpers.php';
