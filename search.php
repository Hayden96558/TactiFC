<?php
/**
 * TactiFC — Search page with advanced filtering.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

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
];

$hasQuery = ($q !== '') || (bool) array_filter($filters);

if ($q !== '') {
    $textResults = searchTactics($q);
    $structured  = filterTactics($filters);
    $ids = array_column($structured, 'id');
    $results = array_values(array_filter($textResults, static fn ($t) => in_array($t['id'], $ids, true)));
} elseif ($hasQuery) {
    $results = filterTactics($filters);
} else {
    $results = [];
}

$PAGE_TITLE = 'Search Tactics | TactiFC';
$PAGE_DESC  = 'Search EA Sports FC25, FC26 and FC27 tactics by manager, club, season, formation, playstyle, player role or focus.';

$examples = ['mour → Mourinho', 'inter → Inter Milan', '2009 → 2009/10', '433 → 4-3-3', 'counter → Counter Attack', 'Deep-Lying Playmaker'];

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Search</h1>
        <p>Search across manager, club, season, formation, playstyle, player position, player role and focus. Combine free text with structured filters.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <form class="filters" method="get" action="<?= e(url('search.php')) ?>">
            <div class="filters-head">
                <h2>Find a tactic</h2>
                <button type="button" class="btn btn-ghost btn-sm filter-collapse" data-toggle-filters>Show filters</button>
            </div>

            <div class="field mb-2">
                <label for="s-q">Search terms</label>
                <input id="s-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search manager, club, year, formation, playstyle or role…" autocomplete="off" data-live-search>
                <div class="search-suggest" id="searchSuggest" hidden></div>
            </div>

            <div class="filter-grid" id="filterGrid">
                <div class="field">
                    <label for="s-manager">Manager</label>
                    <select id="s-manager" name="manager">
                        <option value="">Any manager</option>
                        <?php foreach (tacticManagers() as $m): ?>
                            <option value="<?= e($m) ?>"<?= strcasecmp($filters['manager'], $m) === 0 ? ' selected' : '' ?>><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-club">Club</label>
                    <select id="s-club" name="club">
                        <option value="">Any club</option>
                        <?php foreach (tacticClubs() as $c): ?>
                            <option value="<?= e($c) ?>"<?= strcasecmp($filters['club'], $c) === 0 ? ' selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-season">Season</label>
                    <select id="s-season" name="season">
                        <option value="">Any season</option>
                        <?php foreach (tacticSeasons() as $s): ?>
                            <option value="<?= e($s) ?>"<?= $filters['season'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-formation">Formation</label>
                    <select id="s-formation" name="formation">
                        <option value="">Any formation</option>
                        <?php foreach (tacticFormations() as $fo): ?>
                            <option value="<?= e($fo) ?>"<?= $filters['formation'] === $fo ? ' selected' : '' ?>><?= e($fo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-style">Playstyle</label>
                    <select id="s-style" name="style">
                        <option value="">Any style</option>
                        <?php foreach (tacticStyles() as $st): ?>
                            <option value="<?= e($st) ?>"<?= strcasecmp($filters['style'], $st) === 0 ? ' selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-role">Player Role</label>
                    <select id="s-role" name="role">
                        <option value="">Any role</option>
                        <?php foreach (roleCatalogue() as $role): ?>
                            <option value="<?= e($role['name']) ?>"<?= strcasecmp($filters['role'], $role['name']) === 0 ? ' selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-position">Position</label>
                    <select id="s-position" name="position">
                        <option value="">Any position</option>
                        <?php foreach (allPositions() as $p): ?>
                            <option value="<?= e($p) ?>"<?= $filters['position'] === $p ? ' selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-version">FC Version</label>
                    <select id="s-version" name="version">
                        <option value="">All versions</option>
                        <?php foreach (availableVersions() as $v): ?>
                            <option value="<?= e($v) ?>"<?= strtolower($filters['version']) === $v ? ' selected' : '' ?>><?= e(versionLabel($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-mode">Game Mode</label>
                    <select id="s-mode" name="mode">
                        <option value="">Any mode</option>
                        <?php foreach (allGameModes() as $mode): ?>
                            <option value="<?= e($mode) ?>"<?= $filters['mode'] === $mode ? ' selected' : '' ?>><?= e($mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-tag">Tag</label>
                    <select id="s-tag" name="tag">
                        <option value="">Any tag</option>
                        <?php foreach (allTags() as $tag): ?>
                            <option value="<?= e($tag) ?>"<?= strcasecmp($filters['tag'], $tag) === 0 ? ' selected' : '' ?>><?= e($tag) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('search.php')) ?>">Clear</a>
                </div>
            </div>
        </form>

        <?php if (!$hasQuery): ?>
            <div class="panel">
                <h2>Search tips</h2>
                <p class="muted">Partial terms work, and you can combine words. Try:</p>
                <div class="pill-row">
                    <?php foreach ($examples as $ex): ?>
                        <span class="chip"><?= e($ex) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="hint mt-2">All search terms must match (e.g. <em>pep barca</em> finds Guardiola Barcelona tactics). Numeric formation shorthand like <em>433</em> finds 4-3-3.</p>
            </div>
        <?php else: ?>
            <div class="filter-bar">
                <h2 class="mb-0" style="font-size:1.1rem;">Results<?= $q !== '' ? ' for “' . e($q) . '”' : '' ?></h2>
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
                    <p>Try fewer or different terms, or <a href="<?= e(url('search.php')) ?>">clear your search</a>.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
