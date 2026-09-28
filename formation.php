<?php
/**
 * TactiFC — Single formation detail page.
 *
 * Shows base / with-ball / without-ball shapes, tactical characteristics,
 * strengths, limitations, common roles, variations and example tactics.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/pitch.php';
require_once __DIR__ . '/includes/cards.php';

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$formation = $id !== '' ? getFormationById($id) : null;

if ($formation === null) {
    http_response_code(404);
    $PAGE_TITLE = 'Formation not found | TactiFC';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section text-center"><div class="empty-state"><div class="big">⚽</div><h1>Formation not found</h1><p class="muted">That formation does not exist. <a href="' . e(url('formations.php')) . '">Browse all formations</a>.</p></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$name  = (string) $formation['name'];
$slots = formationSlots($id);
if (!$slots && !empty($formation['slots'])) {
    $slots = $formation['slots'];
}
$tactics = filterTactics(['formation' => $name]);
$commonRoles = commonRolesForFormation($name);
$managers = managersForFormation($name);

/* Rough with/without ball shapes derived from the formation's own slots. */
$basePlayers = array_map(static fn ($s) => [
    'position' => (string) $s['pos'],
    'role'     => '',
    'focus'    => '',
    'x'        => (float) $s['x'],
    'y'        => (float) $s['y'],
], $slots);

/* Derive sensible with-ball / without-ball shape labels for this formation. */
$formationShapeMap = [
    '4-3-3'         => ['with' => '3-2-5', 'without' => '4-4-2'],
    '4-3-3-holding' => ['with' => '3-2-5', 'without' => '4-1-4-1'],
    '4-2-3-1'       => ['with' => '3-2-5', 'without' => '4-4-2'],
    '4-4-2'         => ['with' => '4-2-4', 'without' => '4-4-2'],
    '4-4-2-holding' => ['with' => '4-2-4', 'without' => '4-4-2'],
    '4-1-2-1-2'     => ['with' => '4-2-4', 'without' => '4-4-2'],
    '4-2-2-2'       => ['with' => '4-2-4', 'without' => '4-4-2'],
    '4-3-2-1'       => ['with' => '4-2-4', 'without' => '4-4-1-1'],
    '4-1-4-1'       => ['with' => '3-2-5', 'without' => '4-1-4-1'],
    '3-5-2'         => ['with' => '3-2-5', 'without' => '5-3-2'],
    '3-4-2-1'       => ['with' => '3-2-5', 'without' => '5-4-1'],
    '3-4-3'         => ['with' => '3-2-5', 'without' => '5-4-1'],
    '5-3-2'         => ['with' => '3-2-5', 'without' => '5-4-1'],
    '5-2-3'         => ['with' => '3-2-5', 'without' => '5-4-1'],
];
$shapeLabels = $formationShapeMap[$id] ?? ['with' => '3-2-5', 'without' => '4-4-2'];

$withPlayers    = shapedSlots($basePlayers, 'with', ['withBall' => $shapeLabels['with'], 'withoutBall' => $shapeLabels['without']]);
$withoutPlayers = shapedSlots($basePlayers, 'without', ['withBall' => $shapeLabels['with'], 'withoutBall' => $shapeLabels['without']]);

/* Shape labels: express the shape as a count of defenders-midfielders-attackers. */
function formationShapeLabel(array $players): string
{
    $def = 0; $mid = 0; $att = 0;
    foreach ($players as $p) {
        $g = positionGroup((string) $p['position']);
        if ($g === 'gk') continue;
        if ($g === 'def') $def++;
        elseif ($g === 'mid') $mid++;
        else $att++;
    }
    return $def . '-' . $mid . '-' . $att;
}

$baseLabel    = (string) $name;
$withLabel    = formationShapeLabel($withPlayers);
$withoutLabel = formationShapeLabel($withoutPlayers);

/* Variations: alternates for this formation family. */
$variationMap = [
    '4-3-3'         => ['4-2-3-1', '4-3-3 Holding', '4-1-4-1'],
    '4-3-3-holding' => ['4-3-3', '4-2-3-1', '4-1-4-1'],
    '4-2-3-1'       => ['4-3-3', '4-4-2', '4-4-2 Holding'],
    '4-4-2'         => ['4-2-3-1', '4-4-2 Holding', '4-1-2-1-2'],
    '4-4-2-holding' => ['4-4-2', '4-1-4-1', '5-3-2'],
    '4-1-2-1-2'     => ['4-2-2-2', '4-4-2', '4-3-3'],
    '4-2-2-2'       => ['4-1-2-1-2', '4-4-2', '4-2-3-1'],
    '3-5-2'         => ['3-4-2-1', '5-3-2', '3-4-3'],
    '3-4-2-1'       => ['3-4-3', '3-5-2', '5-2-3'],
    '3-4-3'         => ['3-4-2-1', '3-5-2', '5-2-3'],
    '5-3-2'         => ['3-5-2', '5-2-3', '4-4-2 Holding'],
    '5-2-3'         => ['5-3-2', '3-4-3', '3-4-2-1'],
    '4-1-4-1'       => ['4-3-3 Holding', '4-2-3-1', '4-4-2 Holding'],
    '4-3-2-1'       => ['4-3-3', '4-2-3-1', '4-1-2-1-2'],
];
$variations = $variationMap[$id] ?? [];

$PAGE_TITLE = $name . ' Formation — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = $name . ' football formation for EA Sports FC. ' . (string) ($formation['description'] ?? '');

$EXTRA_SCRIPTS = ['assets/js/formation-shapes.js'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <div class="section-eyebrow"><a href="<?= e(url('formations.php')) ?>">Formations</a></div>
        <h1><?= e($name) ?></h1>
        <p><?= e((string) ($formation['label'] ?? '')) ?></p>
        <div class="tag-row">
            <span class="tag tag-brand">Base: <?= e($baseLabel) ?></span>
            <span class="tag">With Ball: <?= e($withLabel) ?></span>
            <span class="tag">Without Ball: <?= e($withoutLabel) ?></span>
        </div>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <div class="tactic-layout">
            <div class="panel">
                <div class="shape-toggle" role="tablist" aria-label="Formation shape">
                    <button type="button" class="btn btn-primary btn-sm fshape-btn is-active" data-fshape="base" role="tab" aria-selected="true">BASE <span class="shape-sub"><?= e($baseLabel) ?></span></button>
                    <button type="button" class="btn btn-ghost btn-sm fshape-btn" data-fshape="with" role="tab" aria-selected="false">WITH BALL <span class="shape-sub"><?= e($withLabel) ?></span></button>
                    <button type="button" class="btn btn-ghost btn-sm fshape-btn" data-fshape="without" role="tab" aria-selected="false">WITHOUT BALL <span class="shape-sub"><?= e($withoutLabel) ?></span></button>
                </div>

                <div class="formation-pitch-stage">
                    <div class="fp-shape" data-fshape-panel="base">
                        <?= renderPitch($basePlayers, ['id' => 'fp-base', 'showLabels' => false, 'interactive' => false]) ?>
                    </div>
                    <div class="fp-shape" data-fshape-panel="with" hidden>
                        <?= renderPitch($withPlayers, ['id' => 'fp-with', 'showLabels' => false, 'interactive' => false]) ?>
                    </div>
                    <div class="fp-shape" data-fshape-panel="without" hidden>
                        <?= renderPitch($withoutPlayers, ['id' => 'fp-without', 'showLabels' => false, 'interactive' => false]) ?>
                    </div>
                </div>
                <p class="hint text-center mt-2">Shape changes are a visual interpretation of how the formation typically morphs — not a simulation of the game's animation.</p>
            </div>

            <div>
                <div class="panel">
                    <h2>Overview</h2>
                    <p class="muted"><?= e((string) ($formation['description'] ?? '')) ?></p>

                    <div class="role-behaviours">
                        <div class="behaviour">
                            <h4>Strengths</h4>
                            <ul>
                                <?php foreach (($formation['strengths'] ?? []) as $s): ?>
                                    <li><?= e((string) $s) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="behaviour">
                            <h4>Limitations</h4>
                            <ul>
                                <?php foreach (($formation['weaknesses'] ?? []) as $w): ?>
                                    <li><?= e((string) $w) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Tactical Characteristics</h2>
                    <div class="stat-grid">
                        <div class="stat">
                            <div class="stat-label">Base Shape</div>
                            <div class="stat-value"><?= e($baseLabel) ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">With Ball</div>
                            <div class="stat-value"><?= e($withLabel) ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Without Ball</div>
                            <div class="stat-value"><?= e($withoutLabel) ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Positions</div>
                            <div class="stat-value"><?= count($slots) ?></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Positions</h2>
                    <div class="pill-row">
                        <?php foreach ($slots as $slot): ?>
                            <span class="badge badge-pos"><?= e((string) $slot['pos']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="tactic-layout">
            <div class="panel">
                <h2>Common Roles</h2>
                <p class="muted">Roles most frequently used in this shape across the TactiFC database.</p>
                <?php if ($commonRoles): ?>
                    <div class="table-scroll">
                        <table class="data-table">
                            <thead><tr><th>Position</th><th>Role</th><th>Used in</th></tr></thead>
                            <tbody>
                                <?php foreach ($commonRoles as $cr): ?>
                                    <tr>
                                        <td><span class="badge badge-pos"><?= e($cr['position']) ?></span></td>
                                        <td><a href="<?= e(url('role.php?name=' . rawurlencode($cr['role']))) ?>"><?= e($cr['role']) ?></a></td>
                                        <td><?= (int) $cr['count'] ?> tactic<?= $cr['count'] === 1 ? '' : 's' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">No role data available for this formation yet.</p>
                <?php endif; ?>
            </div>

            <div>
                <?php if ($variations): ?>
                <div class="panel">
                    <h2>Common Variations</h2>
                    <p class="muted">Related shapes this formation can shift into.</p>
                    <div class="variation-grid">
                        <?php foreach ($variations as $v): ?>
                            <?php $vId = strtolower(str_replace(' ', '-', $v)); ?>
                            <?php if (getFormationById($vId)): ?>
                                <a class="variation-card" href="<?= e(url('formation.php?id=' . rawurlencode($vId))) ?>">
                                    <span class="formation-badge"><?= e($v) ?></span>
                                    <span class="variation-hint">View formation</span>
                                </a>
                            <?php else: ?>
                                <span class="variation-card is-static">
                                    <span class="formation-badge"><?= e($v) ?></span>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($managers): ?>
                <div class="panel">
                    <h2>Example Managers</h2>
                    <p class="muted">Managers in the database who use this shape.</p>
                    <div class="pill-row">
                        <?php foreach ($managers as $m): ?>
                            <a class="chip" href="<?= e(url('manager.php?name=' . rawurlencode($m))) ?>"><?= e($m) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($tactics): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Examples</div>
                <h2>Example Tactics using the <?= e($name) ?></h2>
                <p><?= count($tactics) ?> tactic<?= count($tactics) === 1 ? '' : 's' ?> use this shape.</p>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($tactics as $tactic): ?>
                <?= renderTacticCard($tactic) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
