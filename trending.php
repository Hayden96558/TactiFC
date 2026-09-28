<?php
/**
 * TactiFC — Trending Tactics.
 *
 * IMPORTANT: There is no backend analytics store yet. This page combines:
 *   - A curated "editor's picks" list from the database (labelled as such).
 *   - Your own local activity (views / favorites / recent) from localStorage,
 *     clearly labelled as your personal activity.
 *
 * It does NOT present fake site-wide popularity statistics.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Trending Tactics — TactiFC';
$PAGE_DESC  = 'Popular tactics to explore on TactiFC, plus your own most-viewed and favorited tactics. Site-wide popularity is not tracked yet.';

$tacticIndex = [];
foreach (loadTactics() as $t) {
    $tacticIndex[$t['id']] = $t;
}

/* Curated editor's picks: a spread across styles, clearly a fixed list. */
$editorPicks = [];
$wantedStyles = ['Possession', 'High Press', 'Counter Attack', 'Low Block', 'Attacking', 'Balanced'];
$used = [];
foreach ($wantedStyles as $style) {
    foreach (loadTactics() as $t) {
        if (in_array($t['id'], $used, true)) continue;
        if (in_array($style, $t['style'] ?? [], true)) {
            $editorPicks[] = $t;
            $used[] = $t['id'];
            break;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Trending</h1>
        <p>What's popular to explore right now. This page combines a curated editor's selection with your own local activity — site-wide popularity statistics are not tracked yet.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <div class="notice">
            <strong>No fake statistics.</strong> TactiFC does not yet have a server-side analytics
            store, so there are no site-wide view counts here. Your personal activity below is
            stored only in your browser.
        </div>

        <!-- Editor's picks -->
        <div class="section-head mt-3">
            <div>
                <div class="section-eyebrow">Curated</div>
                <h2>Editor's Picks</h2>
                <p>A hand-picked spread of tactics across different styles. This is a fixed selection, not live popularity data.</p>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($editorPicks as $tactic): ?>
                <?= renderTacticCard($tactic, ['showRoles' => false]) ?>
            <?php endforeach; ?>
        </div>

        <!-- Personal trending -->
        <div class="section-head mt-3">
            <div>
                <div class="section-eyebrow">Your Activity</div>
                <h2>Your Most Explored</h2>
                <p>Ranked from your own local views, favorites and recent history.</p>
            </div>
            <span class="demo-badge">Demo statistics · your browser only</span>
        </div>
        <div class="grid grid-3" id="trendingGrid"></div>
        <div class="empty-state" id="trendingEmpty" hidden>
            <div class="big">📈</div>
            <h2>No personal activity yet</h2>
            <p>Open some tactics, favorite a few, and they will appear here.</p>
            <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('tactics.php')) ?>">Browse tactics</a>
        </div>
    </div>
</section>

<script>
window.TACTIFC_INDEX = <?= json_encode($tacticIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.TACTIFC_URL = <?= json_encode(basePath()) ?>;
</script>
<script src="<?= e(url('assets/js/trending.js')) ?>" defer></script>

<?php $EXTRA_SCRIPTS = []; include __DIR__ . '/includes/footer.php'; ?>
