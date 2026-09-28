<?php
/**
 * TactiFC — Favorites, recently viewed and custom tactics.
 *
 * All data is stored client-side in localStorage; this page fetches the
 * referenced tactics from PHP by ID.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Favorites & Saved Tactics | TactiFC';
$PAGE_DESC  = 'Your favorite tactics, recently viewed setups and custom Tactic Builder creations, saved locally in your browser.';

/* Encode the database once so JS can render saved cards without extra requests. */
$tacticIndex = [];
foreach (loadTactics() as $t) {
    $tacticIndex[$t['id']] = $t;
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Favorites</h1>
        <p>Everything here is saved in your own browser using localStorage — no account required.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <div class="tabs" role="tablist">
            <button class="tab is-active" data-fav-tab="favorites" role="tab" aria-selected="true">♥ Favorite Tactics <span class="muted" id="favCount">(0)</span></button>
            <button class="tab" data-fav-tab="recent" role="tab" aria-selected="false">Recently Viewed <span class="muted" id="recentCount">(0)</span></button>
            <button class="tab" data-fav-tab="custom" role="tab" aria-selected="false">Custom Tactics <span class="muted" id="customCount">(0)</span></button>
        </div>

        <div class="tab-panel is-active" data-fav-panel="favorites">
            <div class="grid grid-3" id="favGrid"></div>
            <div class="empty-state" id="favEmpty" hidden>
                <div class="big">♡</div>
                <h2>No favorites yet</h2>
                <p>Click the <strong>Favorite</strong> button on any tactic to save it here.</p>
                <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('tactics.php')) ?>">Browse tactics</a>
            </div>
        </div>

        <div class="tab-panel" data-fav-panel="recent">
            <div class="filter-bar">
                <h2 class="mb-0" style="font-size:1.1rem;">Recently Viewed</h2>
                <button type="button" class="btn btn-ghost btn-sm" data-clear-recent style="margin-left:auto;">Clear History</button>
            </div>
            <div class="grid grid-3" id="recentGrid"></div>
            <div class="empty-state" id="recentEmpty" hidden>
                <div class="big">🕘</div>
                <h2>Nothing viewed yet</h2>
                <p>The last 10 tactics you open will appear here for quick access.</p>
            </div>
        </div>

        <div class="tab-panel" data-fav-panel="custom">
            <div class="grid grid-3" id="customGrid"></div>
            <div class="empty-state" id="customEmpty" hidden>
                <div class="big">🛠️</div>
                <h2>No custom tactics yet</h2>
                <p>Build your own formation in the <a href="<?= e(url('builder.php')) ?>">Tactic Builder</a>, then save it here.</p>
            </div>
        </div>
    </div>
</section>

<script>
window.TACTIFC_INDEX = <?= json_encode($tacticIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.TACTIFC_URL = <?= json_encode(basePath()) ?>;
</script>

<?php $EXTRA_SCRIPTS = []; include __DIR__ . '/includes/footer.php'; ?>
