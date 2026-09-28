<?php
/**
 * TactiFC — Manager profile page.
 *
 * Accepts ?id=<manager-id> (preferred) or ?name=<Manager Name> for
 * backwards compatibility. Shows characteristics, formations used, an
 * average Tactical DNA profile, a clickable season timeline and tactics.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/pitch.php';
require_once __DIR__ . '/includes/cards.php';

$idParam   = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$nameParam = isset($_GET['name']) ? trim((string) $_GET['name']) : '';

$manager = null;
if ($idParam !== '') {
    $manager = getManagerById($idParam);
}
if ($manager === null && $nameParam !== '') {
    $manager = getManagerByName($nameParam);
}
if ($manager === null && $idParam !== '') {
    $manager = getManagerByName($idParam);
}

if ($manager === null) {
    http_response_code(404);
    $PAGE_TITLE = 'Manager not found | TactiFC';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section text-center"><div class="empty-state"><div class="big">🧑‍💼</div><h1>Manager not found</h1><p class="muted">That manager is not in our database. <a href="' . e(url('managers.php')) . '">Browse all managers</a>.</p></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$managerName  = (string) $manager['name'];
$managerId    = (string) ($manager['id'] ?? '');
$tactics      = tacticsByManager($managerName);
$styles       = $manager['styles'] ?? [];
$clubs        = $manager['clubs'] ?? [];
$characteristics = managerCharacteristics($manager);
$formations   = managerFormations($manager);
$avgDna       = managerAverageDna($manager);
$timeline     = managerTimeline($managerName);
$featured     = array_slice($tactics, 0, 3);

$PAGE_TITLE = $managerName . ' Tactics — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Tactics inspired by ' . $managerName . ' for EA Sports FC25, FC26 and FC27. ' . (string) ($manager['bio'] ?? '');

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <div class="section-eyebrow"><a href="<?= e(url('managers.php')) ?>">Managers</a></div>
        <h1><?= e($managerName) ?></h1>
        <p><?= e((string) ($manager['bio'] ?? '')) ?></p>
        <div class="tag-row">
            <?php if (!empty($manager['nationality'])): ?>
                <span class="tag tag-accent"><?= e((string) $manager['nationality']) ?></span>
            <?php endif; ?>
            <?php foreach ($styles as $style): ?>
                <span class="tag tag-brand"><?= e((string) $style) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<section class="section" style="padding-top: 16px;">
    <div class="container">
        <div class="tactic-layout">
            <!-- Timeline -->
            <div>
                <div class="panel">
                    <h2>Manager Timeline</h2>
                    <p class="muted">Each season below links to the relevant tactic recreation. Seasons without a tactic are shown for context.</p>
                    <?php if ($timeline): ?>
                        <ol class="timeline">
                            <?php foreach ($timeline as $entry): ?>
                                <li class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <a class="timeline-link" href="<?= e(url('tactic.php?id=' . rawurlencode((string) ($entry['tactic']['id'] ?? '')))) ?>">
                                            <span class="timeline-club"><?= e((string) $entry['club']) ?></span>
                                            <span class="timeline-season"><?= e((string) $entry['season']) ?></span>
                                        </a>
                                        <div class="timeline-meta">
                                            <span class="formation-badge"><?= e((string) ($entry['tactic']['formation'] ?? '')) ?></span>
                                            <?php foreach (array_slice($entry['tactic']['style'] ?? [], 0, 2) as $s): ?>
                                                <span class="tag"><?= e((string) $s) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="muted">No timeline entries available yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <div class="panel">
                    <h2>Tactical Characteristics</h2>
                    <ul class="characteristics">
                        <?php foreach ($characteristics as $c): ?>
                            <li><?= e($c) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="hint">Broad tactical descriptions derived from this database's recreations — not an absolute description of every team the manager coached.</p>
                </div>

                <div class="panel">
                    <h2>Formations Used</h2>
                    <div class="pill-row">
                        <?php foreach ($formations as $f): ?>
                            <a class="chip" href="<?= e(url('tactics.php?manager=' . rawurlencode($managerName) . '&formation=' . rawurlencode($f))) ?>"><?= e($f) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="panel">
                    <h2>Average DNA Profile</h2>
                    <p class="muted">Tactical Profile — Fan Recreation</p>
                    <?= renderDna($avgDna, ['compact' => true]) ?>
                    <p class="hint mt-2">Averaged across <?= count($tactics) ?> recreation<?= count($tactics) === 1 ? '' : 's' ?> in this database.</p>
                </div>

                <div class="panel">
                    <h2>Known Clubs</h2>
                    <div class="pill-row">
                        <?php foreach ($clubs as $club): ?>
                            <a class="chip" href="<?= e(url('search.php?q=' . rawurlencode((string) $club))) ?>"><?= e((string) $club) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Featured</div>
                <h2>Featured Tactics</h2>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($featured as $tactic): ?>
                <?= renderTacticCard($tactic) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Recreations</div>
                <h2>All Tactics</h2>
                <p><?= count($tactics) ?> tactic<?= count($tactics) === 1 ? '' : 's' ?> inspired by <?= e($managerName) ?>.</p>
            </div>
        </div>

        <?php if ($tactics): ?>
            <div class="grid grid-3">
                <?php foreach ($tactics as $tactic): ?>
                    <?= renderTacticCard($tactic) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">No tactics available for this manager yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
