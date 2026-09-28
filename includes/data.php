<?php
/**
 * TactiFC — Shared data bootstrap.
 *
 * Loads the JSON data sources and prepares a few values that every page
 * needs (site base path, available versions, page metadata defaults).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Detect the URL path the app is being served from, so links work no
 * matter which port / document root MAMP uses. Returns e.g. "/TactiFC".
 */
function basePath(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }
    // Normalise any trailing slash.
    return $base = rtrim($dir, '/');
}

/** Build an absolute-in-app URL (relative to the project root). */
function url(string $path = ''): string
{
    return basePath() . '/' . ltrim($path, '/');
}

/** Current page filename, used to highlight the active nav item. */
function currentPage(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
}

/** Default site metadata; pages may override before including the header. */
$SITE = $SITE ?? [];
$SITE += [
    'name'        => 'TactiFC',
    'tagline'     => 'Find Your Perfect FC Formation',
    'description' => 'Explore formations, legendary manager tactics, player roles and custom setups for EA Sports FC25, FC26 and FC27.',
];
