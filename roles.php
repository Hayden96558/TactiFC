<?php
/**
 * TactiFC — Player Roles information page.
 *
 * Browse every role, filter by FC version and position.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';

$PAGE_TITLE = 'Player Roles & Focuses — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Browse every EA Sports FC player role and focus across FC25, FC26 and FC27. Filter by game version and position.';

$catalogue = roleCatalogue();
$versions  = availableVersions();
$positions = allPositions();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Player Roles &amp; Focuses</h1>
        <p>Every position has its own set of roles, and each role has focuses that change how a player behaves. Roles are version-specific — FC26 adds roles like Ball-Playing Keeper, Wide Back, Inverted Wingback and Box Crasher.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <div class="filters">
            <div class="filters-head">
                <h2>Filter roles</h2>
                <button type="button" class="btn btn-ghost btn-sm filter-collapse" data-toggle-filters>Show filters</button>
            </div>
            <div class="filter-grid" id="filterGrid">
                <div class="field">
                    <label for="roleSearch">Search roles</label>
                    <input id="roleSearch" type="search" placeholder="Role name or description…" data-role-filter autocomplete="off">
                </div>
                <div class="field">
                    <label for="roleVersion">FC Version</label>
                    <select id="roleVersion" data-role-filter data-filter-attr="data-versions">
                        <option value="">All versions</option>
                        <?php foreach ($versions as $v): ?>
                            <option value="<?= e($v) ?>"><?= e(versionLabel($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="rolePosition">Position</label>
                    <select id="rolePosition" data-role-filter data-filter-attr="data-positions">
                        <option value="">All positions</option>
                        <?php foreach ($positions as $p): ?>
                            <option value="<?= e($p) ?>"><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="filter-bar">
            <h2 class="mb-0" style="font-size:1.1rem;">All roles</h2>
            <span class="results-count" id="roleCount"><?= count($catalogue) ?> roles</span>
        </div>

        <div class="grid grid-3" id="roleGrid">
            <?php foreach ($catalogue as $role): ?>
                <article class="card role-card"
                         data-versions="<?= e(implode(',', $role['versions'])) ?>"
                         data-positions="<?= e(implode(',', $role['positions'])) ?>"
                         data-search="<?= e(normalizeText($role['name'] . ' ' . $role['description'] . ' ' . implode(' ', $role['positions']))) ?>">
                    <div class="card-top">
                        <h3 class="card-title"><?= e((string) $role['name']) ?></h3>
                        <span class="formation-badge" style="min-width:auto;"><?= count($role['versions']) ?>×</span>
                    </div>
                    <div class="role-positions">
                        <?php foreach ($role['positions'] as $p): ?>
                            <span class="badge badge-pos"><?= e($p) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="role-desc"><?= e((string) $role['description']) ?></p>
                    <div class="version-pills">
                        <?php foreach ($role['versions'] as $v): ?>
                            <span class="version-pill is-on"><?= e(versionLabel($v)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="tag-row">
                        <?php foreach ($role['focuses'] as $f): ?>
                            <span class="tag tag-accent"><?= e($f) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-actions">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('role.php?name=' . rawurlencode((string) $role['name']))) ?>">View role</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="empty-state" id="roleNoResults" hidden>No roles match your filters.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
