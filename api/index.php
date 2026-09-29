<?php
/**
 * TactiFC — Homepage.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/pitch.php';
require_once __DIR__ . '/../includes/cards.php';

$PAGE_TITLE = 'TactiFC — FC25, FC26 & FC27 Formations & Tactics';
$PAGE_DESC  = 'Explore formations, legendary manager tactics, player roles and custom setups for EA Sports FC25, FC26 and FC27. Search by manager, club, season, formation or playstyle.';

$tactics   = loadTactics();
$formations = loadFormations();
$managers  = loadManagers();

/* Pick a varied set of recommended tactics (one per manager, up to 6). */
$recommended = [];
$seenManagers = [];
foreach ($tactics as $t) {
    $m = $t['manager'] ?? '';
    if (isset($seenManagers[$m])) {
        continue;
    }
    $seenManagers[$m] = true;
    $recommended[] = $t;
    if (count($recommended) >= 6) {
        break;
    }
}

$exampleSearches = ['Mourinho Inter', 'Guardiola Barcelona', '4-3-3', 'Counter Attack', 'Ancelotti Real Madrid'];

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-center">
        <span class="hero-eyebrow">EA Sports FC25 · FC26 · FC27</span>
        <h1>Find Your Perfect <span class="grad">FC Formation</span></h1>
        <p class="hero-sub">Explore formations, legendary manager tactics, and custom setups for FC25, FC26 &amp; FC27.</p>

        <form class="big-search" action="<?= e(url('search.php')) ?>" method="get" role="search" data-live-search-form>
            <label class="visually-hidden" for="heroSearch">Search tactics</label>
            <input id="heroSearch" type="search" name="q" placeholder="Search manager, club, year, formation or playstyle..." autocomplete="off" data-live-search>
            <button type="submit" aria-label="Search">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.2"/>
                    <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </button>
            <div class="search-suggest" id="heroSuggest" hidden></div>
        </form>

        <div class="example-searches" aria-label="Example searches">
            <?php foreach ($exampleSearches as $example): ?>
                <a class="chip" href="<?= e(url('search.php?q=' . rawurlencode($example))) ?>"><?= e($example) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= e(url('formations.php')) ?>">Explore Formations</a>
            <a class="btn btn-ghost" href="<?= e(url('tactics.php')) ?>">Browse Tactics</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid grid-4">
            <div class="stat">
                <div class="stat-label">Tactics</div>
                <div class="stat-value"><?= count($tactics) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Formations</div>
                <div class="stat-value"><?= count($formations) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Managers</div>
                <div class="stat-value"><?= count($managers) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Player Roles</div>
                <div class="stat-value"><?= count(roleCatalogue()) ?></div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="recommended">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Hand-picked</div>
                <h2>Recommended Tactics</h2>
                <p>Popular recreations inspired by legendary managers and sides. Each uses version-specific player roles for FC25, FC26 and FC27.</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('tactics.php')) ?>">View all tactics</a>
        </div>

        <div class="grid grid-3">
            <?php foreach ($recommended as $tactic): ?>
                <?= renderTacticCard($tactic) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Shapes</div>
                <h2>Formation Explorer</h2>
                <p>Browse the most-used shapes in EA Sports FC, each with an interactive pitch preview.</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('formations.php')) ?>">All formations</a>
        </div>

        <div class="grid grid-4">
            <?php foreach (array_slice($formations, 0, 4) as $formation): ?>
                <?= renderFormationCard($formation) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="notice">
            <strong>About this site:</strong> Historical tactics on TactiFC are <em>inspired by</em> the managers and
            teams named. They are gameplay recreations for EA Sports FC, not exact reproductions of real-world systems.
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
