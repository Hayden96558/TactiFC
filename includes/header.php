<?php
/**
 * TactiFC — Reusable site header + navigation.
 *
 * Pages should set $SITE / $PAGE_TITLE / $PAGE_DESC before including this.
 */

declare(strict_types=1);

require_once __DIR__ . '/data.php';

$pageTitle = $PAGE_TITLE ?? ($SITE['name'] . ' — FC25, FC26 & FC27 Formations & Tactics');
$pageDesc  = $PAGE_DESC ?? $SITE['description'];
$page      = currentPage();
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonical = $scheme . '://' . $host . url($page);
$ogImage   = $scheme . '://' . $host . url('assets/images/og-default.svg');

$navItems = [
    'index.php'           => 'Home',
    'formations.php'      => 'Formations',
    'managers.php'        => 'Managers',
    'tactics.php'         => 'Tactics',
    'roles.php'           => 'Player Roles',
    'generator.php'       => 'Generator',
    'recommendations.php' => 'Recommendations',
    'builder.php'         => 'Builder',
    'compare.php'         => 'Compare',
    'favorites.php'       => 'Favorites',
];

/* Which nav item should be highlighted for detail pages. */
$activeMap = [
    'tactic.php'    => 'tactics.php',
    'search.php'    => 'tactics.php',
    'manager.php'   => 'managers.php',
    'formation.php' => 'formations.php',
    'role.php'      => 'roles.php',
    'trending.php'  => 'favorites.php',
];
$active = $activeMap[$page] ?? $page;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="#0b1220">
<link rel="canonical" href="<?= e($canonical) ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="TactiFC">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">

<!-- Base path for JavaScript asset + data lookups. -->
<script>
    window.TACTIFC = {
        base: <?= json_encode(basePath()) ?>,
        versions: <?= json_encode(availableVersions()) ?>
    };
</script>
</head>
<body data-page="<?= e($page) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="TactiFC home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" width="30" height="30" role="img" aria-label="TactiFC">
                    <circle cx="16" cy="16" r="15" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="M16 1v30M1 16h30" stroke="currentColor" stroke-width="1.4" opacity=".5"/>
                    <circle cx="16" cy="16" r="4.5" fill="currentColor"/>
                </svg>
            </span>
            <span class="brand-text">Tacti<span class="brand-accent">FC</span></span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="primaryNav" aria-label="Toggle navigation">
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
        </button>

        <nav class="primary-nav" id="primaryNav" aria-label="Primary">
            <ul class="nav-list">
                <?php foreach ($navItems as $file => $label): ?>
                    <li>
                        <a href="<?= e(url($file)) ?>"<?= $active === $file ? ' class="is-active" aria-current="page"' : '' ?>>
                            <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form class="nav-search" action="<?= e(url('search.php')) ?>" method="get" role="search">
                <label class="visually-hidden" for="navSearchInput">Search tactics</label>
                <svg class="nav-search-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input id="navSearchInput" type="search" name="q" placeholder="Search…" autocomplete="off" data-live-search>
                <div class="search-suggest" id="navSuggest" hidden></div>
            </form>
        </nav>
    </div>
</header>

<main id="main">
