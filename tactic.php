<?php
/**
 * TactiFC — Tactic detail page.
 *
 * Shows the tactic across FC25 / FC26 / FC27 with version-specific player
 * roles, focuses and role familiarity, plus:
 *   - Tactical overview strip
 *   - Tactic DNA profile
 *   - Base / With Ball / Without Ball shapes (with movement overlay)
 *   - Tactical phases ("How This Tactic Works")
 *   - "How To Play" practical guide
 *   - Tactical adjustments
 *   - Variations
 *   - Share / print / duplicate / copy actions
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/pitch.php';
require_once __DIR__ . '/includes/cards.php';
require_once __DIR__ . '/includes/share.php';

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$tactic = $id !== '' ? getTacticById($id) : null;

if ($tactic === null) {
    http_response_code(404);
    $PAGE_TITLE = 'Tactic not found | TactiFC';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section text-center"><div class="empty-state"><div class="big">⚽</div><h1>Tactic not found</h1><p class="muted">That tactic does not exist. <a href="' . e(url('tactics.php')) . '">Browse all tactics</a>.</p></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$manager = (string) ($tactic['manager'] ?? '');
$club    = (string) ($tactic['club'] ?? '');
$season  = (string) ($tactic['season'] ?? '');
$form    = (string) ($tactic['formation'] ?? '');
$styles  = $tactic['style'] ?? [];
$versions = array_keys($tactic['versions'] ?? []);
$firstVersion = $versions[0] ?? 'fc25';
$slots   = tacticSlots($tactic);

/* Build per-version players aligned to the pitch slots (by index). */
$versionPlayers = [];
foreach ($versions as $v) {
    $vd = $tactic['versions'][$v] ?? [];
    $players = [];
    $rawPlayers = $vd['players'] ?? [];
    $coords = $slots;
    if (!empty($vd['formation']) && $vd['formation'] !== $form) {
        $alt = formationSlots((string) $vd['formation']);
        if ($alt) {
            $coords = $alt;
        }
    }
    if (!$coords) {
        $coords = formationSlots((string) ($vd['formation'] ?? $form));
    }
    foreach ($rawPlayers as $i => $p) {
        $coord = $coords[$i] ?? null;
        $players[] = [
            'position'    => (string) ($p['position'] ?? ''),
            'role'        => (string) ($p['role'] ?? ''),
            'focus'       => (string) ($p['focus'] ?? ''),
            'familiarity' => (string) ($p['familiarity'] ?? ''),
            'x'           => $coord !== null ? (float) $coord['x'] : 50,
            'y'           => $coord !== null ? (float) $coord['y'] : 50,
        ];
    }
    $versionPlayers[$v] = $players;
}

$shapes   = tacticShapes($tactic);
$dna      = tacticDna($tactic);
$phases   = tacticPhases($tactic);
$howTo    = tacticHowToPlay($tactic);
$adjust   = tacticAdjustments($tactic);
$variations = tacticVariations($tactic);
$diff     = tacticDifficultyInfo($tactic);
$gameModes = tacticGameModes($tactic);
$tags     = tacticTags($tactic);

$PAGE_TITLE = $manager . ' — ' . $club . ' ' . $season . ' | TactiFC';
$PAGE_DESC  = $form . ' tactic inspired by ' . $manager . ' at ' . $club . ' (' . $season . ') for EA Sports FC25, FC26 and FC27.';

$EXTRA_SCRIPTS = ['assets/js/roles.js', 'assets/js/tactic.js'];
include __DIR__ . '/includes/header.php';
?>

<div class="tactic-hero">
    <div class="container">
        <div class="tactic-hero-top">
            <div>
                <div class="section-eyebrow"><a href="<?= e(url('tactics.php')) ?>">Tactics</a></div>
                <h1><?= e($manager) ?></h1>
                <div class="tactic-meta">
                    <span><a href="<?= e(url('manager.php?name=' . rawurlencode($manager))) ?>"><?= e($manager) ?></a></span>
                    <span class="dot">•</span>
                    <span><a href="<?= e(url('search.php?q=' . rawurlencode($club))) ?>"><?= e($club) ?></a></span>
                    <span class="dot">•</span>
                    <span><?= e($season) ?></span>
                    <span class="dot">•</span>
                    <span><a href="<?= e(url('formation.php?id=' . rawurlencode((string) ($tactic['formation'] ?? '')))) ?>"><?= e($form) ?></a></span>
                </div>
                <div class="tag-row mt-2">
                    <?php foreach ($tags as $tag): ?>
                        <a class="tag" href="<?= e(url('tactics.php?tag=' . rawurlencode((string) $tag))) ?>"><?= e((string) $tag) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="stack" style="align-items:flex-end;">
                <button type="button" class="fav-btn" data-fav-toggle="<?= e($id) ?>" aria-pressed="false">&#9825; Favorite</button>
                <?= renderTacticActions($tactic) ?>
            </div>
        </div>
    </div>
</div>

<!-- Overview strip -->
<section class="section" style="padding-top: 18px; padding-bottom: 0;">
    <div class="container">
        <?= renderOverviewStrip($tactic) ?>
    </div>
</section>

<section class="section" style="padding-top: 26px;">
    <div class="container">
        <div class="tactic-layout">
            <!-- Pitch column -->
            <div>
                <div class="panel">
                    <div class="shape-toggle" role="tablist" aria-label="Pitch view">
                        <button type="button" class="btn btn-primary btn-sm shape-btn is-active" data-shape="base" role="tab" aria-selected="true">BASE <span class="shape-sub"><?= e($shapes['base']) ?></span></button>
                        <button type="button" class="btn btn-ghost btn-sm shape-btn" data-shape="with" role="tab" aria-selected="false">WITH BALL <span class="shape-sub"><?= e($shapes['withBall']) ?></span></button>
                        <button type="button" class="btn btn-ghost btn-sm shape-btn" data-shape="without" role="tab" aria-selected="false">WITHOUT BALL <span class="shape-sub"><?= e($shapes['withoutBall']) ?></span></button>
                    </div>

                    <div class="shape-meta" id="shapeMeta">
                        <strong>In possession:</strong> <span data-shape-meta="with"><?= e($shapes['withBall']) ?></span>
                        <span class="dim">·</span>
                        <strong>Out of possession:</strong> <span data-shape-meta="without"><?= e($shapes['withoutBall']) ?></span>
                    </div>

                    <div class="version-tabs" role="tablist" aria-label="FC version">
                        <?php foreach ($versions as $i => $v): ?>
                            <button type="button" class="version-tab<?= $i === 0 ? ' is-active' : '' ?>" data-version="<?= e($v) ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e(versionLabel($v)) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="pitch-host" id="pitchHost">
                        <?php foreach ($versions as $i => $v): ?>
                            <div class="pitch-version" data-version="<?= e($v) ?>" <?= $i === 0 ? '' : 'hidden' ?>>
                                <?= renderPitch($versionPlayers[$v], ['id' => 'pitch-' . $v, 'showLabels' => true]) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pitch-controls">
                        <label class="switch">
                            <input type="checkbox" id="showMovement">
                            <span class="switch-track"><span class="switch-thumb"></span></span>
                            <span class="switch-label">Show Movement</span>
                        </label>
                        <span class="hint">Movement arrows are a visual interpretation of the selected roles, not a simulation of the game's animation.</span>
                    </div>

                    <p class="hint text-center mt-2">Click a player to open full role details.</p>
                </div>

                <!-- Tactical DNA -->
                <div class="panel">
                    <div class="section-head" style="margin-bottom:14px;">
                        <div>
                            <h2 class="mb-0">Tactic DNA</h2>
                            <p class="muted mb-0">Tactical Profile — Fan Recreation</p>
                        </div>
                        <span class="tag tag-accent">Subjective profile</span>
                    </div>
                    <?= renderDna($dna) ?>
                    <p class="hint mt-2">These values describe the tactical intent of this recreation for EA Sports FC. They are not objective measurements of the original historical team.</p>
                </div>

                <!-- Phases -->
                <div class="panel">
                    <h2>How This Tactic Works</h2>
                    <p class="muted">A phase-by-phase breakdown of how the recreation is designed to function.</p>
                    <div class="phases">
                        <?php $n = 0; foreach (tacticPhaseLabels() as $key => $label): $n++; ?>
                            <div class="phase">
                                <div class="phase-num"><?= $n ?></div>
                                <div class="phase-body">
                                    <h3><?= e($label) ?></h3>
                                    <p><?= e($phases[$key]) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- How to play -->
                <?php if ($howTo): ?>
                <div class="panel">
                    <h2>How To Play</h2>
                    <p class="muted">A practical guide for applying this tactic in a match.</p>
                    <div class="howto-grid">
                        <?php foreach (howToPlayLabels() as $key => $label): if (empty($howTo[$key])) continue; ?>
                            <div class="howto">
                                <h3><?= e($label) ?></h3>
                                <ul>
                                    <?php foreach ($howTo[$key] as $item): ?>
                                        <li><?= e((string) $item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Adjustments -->
                <?php if ($adjust): ?>
                <div class="panel">
                    <h2>Tactical Adjustments</h2>
                    <p class="muted">Suggested options for common match situations. These are tactical options to consider, not guaranteed counters.</p>
                    <div class="adjust-grid">
                        <?php foreach ($adjust as $adj): ?>
                            <div class="adjust">
                                <h3><?= e((string) ($adj['situation'] ?? '')) ?></h3>
                                <ul>
                                    <?php foreach (($adj['options'] ?? []) as $opt): ?>
                                        <li><?= e((string) $opt) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="panel">
                    <h2>Tactical Philosophy</h2>
                    <p class="muted"><?= e((string) ($tactic['philosophy'] ?? '')) ?></p>
                    <div class="notice mt-2">
                        <strong>Inspired by</strong> <?= e($manager) ?>'s <?= e($club) ?> side (<?= e($season) ?>). This is an approximation built for EA Sports FC, not an exact replica of the original system.
                    </div>
                </div>

                <!-- Player roles table -->
                <div class="panel" id="roles">
                    <h2>Player Roles</h2>
                    <p class="muted">Each position shows its role and focus for the selected FC version. Roles and focuses are version-specific — FC25, FC26 and FC27 can differ.</p>
                    <div class="table-scroll mt-2">
                        <table class="data-table" id="rolesTable">
                            <thead>
                                <tr>
                                    <th>Pos</th>
                                    <th>Role</th>
                                    <th>Focus</th>
                                    <th>Familiarity</th>
                                    <th>Instructions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Variations -->
                <?php if ($variations): ?>
                <div class="panel">
                    <h2>Variations</h2>
                    <p class="muted">Alternate shapes this tactic can be adapted into. Each variation can use different player roles.</p>
                    <div class="variation-grid">
                        <?php foreach ($variations as $var):
                            $varFormation = getFormationById(strtolower(str_replace(' ', '-', (string) $var)));
                        ?>
                            <a class="variation-card" href="<?= e(url('builder.php?formation=' . rawurlencode((string) $var))) ?>">
                                <span class="formation-badge"><?= e((string) $var) ?></span>
                                <span class="variation-hint">Open in Builder</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <p class="hint mt-2">Variations are starting suggestions — change roles and focus to suit your squad.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Side column -->
            <div>
                <div class="panel">
                    <h2>Version Settings</h2>
                    <div class="version-tabs" role="tablist" aria-label="FC version settings">
                        <?php foreach ($versions as $i => $v): ?>
                            <button type="button" class="version-tab version-tab-side<?= $i === 0 ? ' is-active' : '' ?>" data-version="<?= e($v) ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e(versionLabel($v)) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($versions as $i => $v):
                        $vd = $tactic['versions'][$v] ?? [];
                        $width = (int) ($vd['width'] ?? 5);
                        $depth = (int) ($vd['depth'] ?? 5);
                    ?>
                        <div class="version-panel version-panel-side<?= $i === 0 ? ' is-active' : '' ?>" data-version="<?= e($v) ?>">
                            <div class="stat-grid">
                                <div class="stat">
                                    <div class="stat-label">Formation</div>
                                    <div class="stat-value"><?= e((string) ($vd['formation'] ?? $form)) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="stat-label">Build-Up</div>
                                    <div class="stat-value"><?= e((string) ($vd['buildUp'] ?? '—')) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="stat-label">Defensive</div>
                                    <div class="stat-value"><?= e((string) ($vd['defensiveApproach'] ?? '—')) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="stat-label">Attacking Focus</div>
                                    <div class="stat-value"><?= e((string) ($vd['attackingFocus'] ?? '—')) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="stat-label">Width</div>
                                    <div class="stat-value"><?= $width ?>/10</div>
                                    <div class="stat-bar"><span style="width:<?= (int) ($width * 10) ?>%"></span></div>
                                </div>
                                <div class="stat">
                                    <div class="stat-label">Depth</div>
                                    <div class="stat-value"><?= $depth ?>/10</div>
                                    <div class="stat-bar"><span style="width:<?= (int) ($depth * 10) ?>%"></span></div>
                                </div>
                            </div>

                            <?php if (!empty($vd['notes'])): ?>
                                <p class="muted mt-2"><strong>Notes:</strong> <?= e((string) $vd['notes']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($vd['playerInstructions'])): ?>
                                <p class="muted"><strong>Player Instructions:</strong> <?= e((string) $vd['playerInstructions']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Difficulty -->
                <div class="panel">
                    <h2>Difficulty</h2>
                    <div class="difficulty-badge difficulty-<?= e(strtolower($diff['level'])) ?>"><?= e($diff['level']) ?></div>
                    <p class="muted mt-2"><?= e($diff['blurb']) ?></p>
                    <p class="stat-label" style="margin-top:12px;">Requires understanding of:</p>
                    <ul class="muted" style="padding-left:18px;">
                        <?php foreach ($diff['why'] as $why): ?>
                            <li><?= e($why) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="hint">Complexity describes the setup itself, not how "good" the tactic is.</p>
                </div>

                <!-- Game modes -->
                <?php if ($gameModes): ?>
                <div class="panel">
                    <h2>Game Modes</h2>
                    <p class="muted">Designed with these EA Sports FC modes in mind.</p>
                    <div class="pill-row">
                        <?php foreach ($gameModes as $mode): ?>
                            <a class="tag tag-brand" href="<?= e(url('tactics.php?mode=' . rawurlencode((string) $mode))) ?>"><?= e((string) $mode) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="panel">
                    <h2>Key Roles</h2>
                    <?php $keyRoles = tacticKeyRoles($tactic, 6); ?>
                    <div class="role-list">
                        <?php foreach ($keyRoles as $kr): ?>
                            <div class="role-item">
                                <div class="role-item-head">
                                    <span class="badge badge-pos"><?= e($kr['position']) ?></span>
                                    <span class="badge badge-role"><?= e($kr['role']) ?></span>
                                    <?php if ($kr['focus'] !== ''): ?><span class="badge badge-focus"><?= e($kr['focus']) ?></span><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a class="btn btn-ghost btn-sm btn-block mt-2" href="<?= e(url('roles.php')) ?>">Explore all roles</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($variations): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="notice">
            <strong>Variation preview:</strong> Switch between shapes using the buttons above. Variations let you adapt the same tactical idea to a different formation without starting over.
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Player role detail modal -->
<div class="modal-backdrop" id="roleModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="roleModalTitle">
        <div class="modal-head">
            <div>
                <div class="section-eyebrow" id="roleModalPos">Position</div>
                <h2 id="roleModalTitle" class="mt-0">Role</h2>
            </div>
            <button type="button" class="modal-close" id="roleModalClose" aria-label="Close">&times;</button>
        </div>
        <div id="roleModalBody"></div>
    </div>
</div>

<script>
window.TACTIFC_TACTIC = <?= json_encode([
    'id'       => $id,
    'manager'  => $manager,
    'club'     => $club,
    'season'   => $season,
    'formation'=> $form,
    'style'    => array_map('strval', $styles),
    'shapes'   => $shapes,
    'versions' => $versionPlayers,
    'settings' => array_combine($versions, array_map(static fn ($v) => $tactic['versions'][$v] ?? [], $versions)),
    'roleDb'   => loadPlayerRoles(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
