<?php
/**
 * TactiFC — Browse all tactics with filters.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/pitch.php';
require_once __DIR__ . '/../includes/cards.php';

$PAGE_TITLE = 'All Tactics — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Browse every tactics recreation for EA Sports FC25, FC26 and FC27. Filter by manager, club, season, formation, playstyle, player role or FC version.';

/* Accept filters from GET (safe, escaped on output). */
$filters = [
    'manager'   => isset($_GET['manager']) ? (string) $_GET['manager'] : '',
    'club'      => isset($_GET['club']) ? (string) $_GET['club'] : '',
    'season'    => isset($_GET['season']) ? (string) $_GET['season'] : '',
    'formation' => isset($_GET['formation']) ? (string) $_GET['formation'] : '',
    'style'     => isset($_GET['style']) ? (string) $_GET['style'] : '',
    'role'      => isset($_GET['role']) ? (string) $_GET['role'] : '',
    'position'  => isset($_GET['position']) ? (string) $_GET['position'] : '',
    'version'   => isset($_GET['version']) ? (string) $_GET['version'] : '',
    'tag'       => isset($_GET['tag']) ? (string) $_GET['tag'] : '',
    'mode'      => isset($_GET['mode']) ? (string) $_GET['mode'] : '',
    'q'         => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
];

$results = $filters['q'] !== '' ? searchTactics($filters['q']) : loadTactics();
$results = filterTactics($filters + []); // q is not a filterTactics key, ignored safely

/* If a text query is present, intersect it with the structured filters. */
if ($filters['q'] !== '') {
    $structured = filterTactics($filters);
    $ids = array_column($structured, 'id');
    $results = array_values(array_filter($results, static fn ($t) => in_array($t['id'], $ids, true)));
}

$hasFilters = (bool) array_filter($filters);

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>All Tactics</h1>
        <p>Every recreation in the TactiFC database. Combine filters to narrow things down, or search across manager, club, season, formation, playstyle and player roles.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <form class="filters" method="get" action="<?= e(url('tactics.php')) ?>">
            <div class="filters-head">
                <h2>Filters</h2>
                <button type="button" class="btn btn-ghost btn-sm filter-collapse" data-toggle-filters>Show filters</button>
            </div>

            <div class="filter-grid" id="filterGrid">
                <div class="field">
                    <label for="f-q">Search</label>
                    <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. mourinho inter 2009" autocomplete="off">
                </div>
                <div class="field">
                    <label for="f-manager">Manager</label>
                    <select id="f-manager" name="manager">
                        <option value="">Any manager</option>
                        <?php foreach (tacticManagers() as $m): ?>
                            <option value="<?= e($m) ?>"<?= strcasecmp($filters['manager'], $m) === 0 ? ' selected' : '' ?>><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-club">Club</label>
                    <select id="f-club" name="club">
                        <option value="">Any club</option>
                        <?php foreach (tacticClubs() as $c): ?>
                            <option value="<?= e($c) ?>"<?= strcasecmp($filters['club'], $c) === 0 ? ' selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-season">Season</label>
                    <select id="f-season" name="season">
                        <option value="">Any season</option>
                        <?php foreach (tacticSeasons() as $s): ?>
                            <option value="<?= e($s) ?>"<?= $filters['season'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-formation">Formation</label>
                    <select id="f-formation" name="formation">
                        <option value="">Any formation</option>
                        <?php foreach (tacticFormations() as $fo): ?>
                            <option value="<?= e($fo) ?>"<?= $filters['formation'] === $fo ? ' selected' : '' ?>><?= e($fo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-style">Playstyle</label>
                    <select id="f-style" name="style">
                        <option value="">Any style</option>
                        <?php foreach (tacticStyles() as $st): ?>
                            <option value="<?= e($st) ?>"<?= strcasecmp($filters['style'], $st) === 0 ? ' selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-role">Player Role</label>
                    <select id="f-role" name="role">
                        <option value="">Any role</option>
                        <?php foreach (roleCatalogue() as $role): ?>
                            <option value="<?= e($role['name']) ?>"<?= strcasecmp($filters['role'], $role['name']) === 0 ? ' selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-position">Position</label>
                    <select id="f-position" name="position">
                        <option value="">Any position</option>
                        <?php foreach (allPositions() as $p): ?>
                            <option value="<?= e($p) ?>"<?= $filters['position'] === $p ? ' selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-version">FC Version</label>
                    <select id="f-version" name="version">
                        <option value="">All versions</option>
                        <?php foreach (availableVersions() as $v): ?>
                            <option value="<?= e($v) ?>"<?= strtolower($filters['version']) === $v ? ' selected' : '' ?>><?= e(versionLabel($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-mode">Game Mode</label>
                    <select id="f-mode" name="mode">
                        <option value="">Any mode</option>
                        <?php foreach (allGameModes() as $mode): ?>
                            <option value="<?= e($mode) ?>"<?= $filters['mode'] === $mode ? ' selected' : '' ?>><?= e($mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-tag">Tag</label>
                    <select id="f-tag" name="tag">
                        <option value="">Any tag</option>
                        <?php foreach (allTags() as $tag): ?>
                            <option value="<?= e($tag) ?>"<?= strcasecmp($filters['tag'], $tag) === 0 ? ' selected' : '' ?>><?= e($tag) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('tactics.php')) ?>">Reset</a>
                </div>
            </div>
        </form>

        <div class="filter-bar">
            <h2 class="mb-0" style="font-size:1.1rem;"><?= $hasFilters ? 'Filtered tactics' : 'All tactics' ?></h2>
            <span class="results-count"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($results): ?>
            <div class="grid grid-3">
                <?php foreach ($results as $tactic): ?>
                    <?= renderTacticCard($tactic) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="big">🔍</div>
                <h2>No tactics found</h2>
                <p>Try loosening your filters, or <a href="<?= e(url('tactics.php')) ?>">reset them</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
