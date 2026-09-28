<?php
/**
 * TactiFC — Compare two tactics side by side (Comparison 2.0).
 *
 * Adds: with-ball / without-ball shape switching, Tactical DNA comparison,
 * a side-by-side pitch comparison, and a fuller attribute table.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/pitch.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Compare Tactics — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Compare two EA Sports FC tactics side by side: formation, with/without-ball shapes, Tactical DNA, playstyle, width, depth, player roles and philosophy.';

$addId = isset($_GET['add']) ? trim((string) $_GET['add']) : '';
$idA   = isset($_GET['a']) ? trim((string) $_GET['a']) : $addId;
$idB   = isset($_GET['b']) ? trim((string) $_GET['b']) : '';

$all = loadTactics();
$tacticA = $idA !== '' ? getTacticById($idA) : null;
$tacticB = $idB !== '' ? getTacticById($idB) : null;

/** Choose an active version (first common version). */
$version = isset($_GET['version']) ? strtolower((string) $_GET['version']) : '';
if ($version === '') {
    $versionsA = $tacticA ? array_keys($tacticA['versions'] ?? []) : availableVersions();
    $versionsB = $tacticB ? array_keys($tacticB['versions'] ?? []) : availableVersions();
    $common = array_values(array_intersect($versionsA, $versionsB));
    $version = $common[0] ?? ($versionsA[0] ?? 'fc25');
}

/** Shape selector: base | with | without. */
$shape = isset($_GET['shape']) ? strtolower((string) $_GET['shape']) : 'base';
if (!in_array($shape, ['base', 'with', 'without'], true)) {
    $shape = 'base';
}

function versionPlayersFor(?array $tactic, string $version): array
{
    if ($tactic === null) {
        return [];
    }
    $vd = $tactic['versions'][$version] ?? (reset($tactic['versions']) ?: []);
    $raw = $vd['players'] ?? [];
    $coords = tacticSlots($tactic);
    if (!$coords) {
        $coords = formationSlots((string) ($vd['formation'] ?? $tactic['formation'] ?? ''));
    }
    $out = [];
    foreach ($raw as $i => $p) {
        $c = $coords[$i] ?? null;
        $out[] = [
            'position' => (string) ($p['position'] ?? ''),
            'role'     => (string) ($p['role'] ?? ''),
            'focus'    => (string) ($p['focus'] ?? ''),
            'familiarity' => (string) ($p['familiarity'] ?? ''),
            'x' => $c ? (float) $c['x'] : 50,
            'y' => $c ? (float) $c['y'] : 50,
        ];
    }
    return $out;
}

/** Comparison query-string builder preserving a/b/version with overrides. */
function compareUrl(array $overrides): string
{
    $params = array_merge([
        'a'       => $_GET['a'] ?? '',
        'b'       => $_GET['b'] ?? '',
        'version' => $_GET['version'] ?? '',
        'shape'   => $_GET['shape'] ?? '',
    ], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
    return url('compare.php' . ($params ? '?' . http_build_query($params) : ''));
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Compare Tactics</h1>
        <p>Put two tactics side by side across formation, with/without-ball shapes, Tactical DNA, playstyle, build-up, defensive shape, width, depth, roles and philosophy.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <form class="filters" method="get" action="<?= e(url('compare.php')) ?>">
            <div class="filter-grid">
                <div class="field">
                    <label for="c-a">Tactic A</label>
                    <select id="c-a" name="a">
                        <option value="">Select tactic…</option>
                        <?php foreach ($all as $t): ?>
                            <option value="<?= e((string) $t['id']) ?>"<?= $tacticA && $tacticA['id'] === $t['id'] ? ' selected' : '' ?>>
                                <?= e(($t['manager'] ?? '') . ' — ' . ($t['club'] ?? '') . ' ' . ($t['season'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="c-b">Tactic B</label>
                    <select id="c-b" name="b">
                        <option value="">Select tactic…</option>
                        <?php foreach ($all as $t): ?>
                            <option value="<?= e((string) $t['id']) ?>"<?= $tacticB && $tacticB['id'] === $t['id'] ? ' selected' : '' ?>>
                                <?= e(($t['manager'] ?? '') . ' — ' . ($t['club'] ?? '') . ' ' . ($t['season'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="c-version">FC Version</label>
                    <select id="c-version" name="version">
                        <?php foreach (availableVersions() as $v): ?>
                            <option value="<?= e($v) ?>"<?= $version === $v ? ' selected' : '' ?>><?= e(versionLabel($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Compare</button>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('compare.php')) ?>">Clear</a>
                </div>
            </div>
        </form>

        <?php if ($tacticA || $tacticB): ?>
            <?php
            $vdA = $tacticA['versions'][$version] ?? [];
            $vdB = $tacticB['versions'][$version] ?? [];
            $pA  = versionPlayersFor($tacticA, $version);
            $pB  = versionPlayersFor($tacticB, $version);

            $dnaA = $tacticA ? tacticDna($tacticA) : array_fill_keys(dnaCategories(), 0);
            $dnaB = $tacticB ? tacticDna($tacticB) : array_fill_keys(dnaCategories(), 0);
            $shapesA = $tacticA ? tacticShapes($tacticA) : ['base' => '', 'withBall' => '', 'withoutBall' => ''];
            $shapesB = $tacticB ? tacticShapes($tacticB) : ['base' => '', 'withBall' => '', 'withoutBall' => ''];

            $labelA = (string) ($tacticA['club'] ?? 'Tactic A');
            $labelB = (string) ($tacticB['club'] ?? 'Tactic B');

            $rowDefs = [
                'Manager'            => [$tacticA['manager'] ?? '—', $tacticB['manager'] ?? '—'],
                'Club'               => [$tacticA['club'] ?? '—', $tacticB['club'] ?? '—'],
                'Season'             => [$tacticA['season'] ?? '—', $tacticB['season'] ?? '—'],
                'Formation'          => [$vdA['formation'] ?? ($tacticA['formation'] ?? '—'), $vdB['formation'] ?? ($tacticB['formation'] ?? '—')],
                'With Ball Shape'    => [$shapesA['withBall'], $shapesB['withBall']],
                'Without Ball Shape' => [$shapesA['withoutBall'], $shapesB['withoutBall']],
                'Playstyle'          => [implode(', ', $tacticA['style'] ?? []) ?: '—', implode(', ', $tacticB['style'] ?? []) ?: '—'],
                'Build-Up'           => [$vdA['buildUp'] ?? '—', $vdB['buildUp'] ?? '—'],
                'Defensive Approach' => [$vdA['defensiveApproach'] ?? '—', $vdB['defensiveApproach'] ?? '—'],
                'Attacking Focus'    => [$vdA['attackingFocus'] ?? '—', $vdB['attackingFocus'] ?? '—'],
                'Width'              => [($vdA['width'] ?? '—') . '/10', ($vdB['width'] ?? '—') . '/10'],
                'Depth'              => [($vdA['depth'] ?? '—') . '/10', ($vdB['depth'] ?? '—') . '/10'],
                'Difficulty'         => [tacticDifficultyInfo($tacticA)['level'] ?? '—', tacticDifficultyInfo($tacticB)['level'] ?? '—'],
                'Game Modes'         => [implode(', ', tacticGameModes($tacticA ?: [])) ?: '—', implode(', ', tacticGameModes($tacticB ?: [])) ?: '—'],
            ];
            ?>

            <!-- Shape selector -->
            <div class="shape-toggle" role="tablist" aria-label="Shape view">
                <a class="btn btn-sm <?= $shape === 'base' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(compareUrl(['shape' => 'base'])) ?>" role="tab" aria-selected="<?= $shape === 'base' ? 'true' : 'false' ?>">BASE</a>
                <a class="btn btn-sm <?= $shape === 'with' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(compareUrl(['shape' => 'with'])) ?>" role="tab" aria-selected="<?= $shape === 'with' ? 'true' : 'false' ?>">WITH BALL</a>
                <a class="btn btn-sm <?= $shape === 'without' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(compareUrl(['shape' => 'without'])) ?>" role="tab" aria-selected="<?= $shape === 'without' ? 'true' : 'false' ?>">WITHOUT BALL</a>
            </div>

            <!-- Pitches -->
            <div class="compare-grid">
                <div class="panel text-center">
                    <?php if ($tacticA): ?>
                        <h3 class="mb-0"><?= e((string) ($tacticA['manager'] ?? '')) ?></h3>
                        <p class="muted"><?= e(($tacticA['club'] ?? '') . ' ' . ($tacticA['season'] ?? '')) ?></p>
                        <?= renderPitch(shapedSlots($pA, $shape, $shapesA), ['id' => 'cmp-a', 'showLabels' => true, 'interactive' => false]) ?>
                        <p class="hint mt-2"><?= e($shape === 'with' ? ('With ball: ' . $shapesA['withBall']) : ($shape === 'without' ? ('Without ball: ' . $shapesA['withoutBall']) : ('Base: ' . $shapesA['base']))) ?></p>
                        <a class="btn btn-ghost btn-sm btn-block mt-2" href="<?= e(url('tactic.php?id=' . rawurlencode((string) $tacticA['id']))) ?>">Open <?= e((string) ($tacticA['club'] ?? '')) ?></a>
                    <?php else: ?>
                        <div class="empty-state"><div class="big">➕</div><p class="muted">Select Tactic A</p></div>
                    <?php endif; ?>
                </div>
                <div class="panel text-center">
                    <?php if ($tacticB): ?>
                        <h3 class="mb-0"><?= e((string) ($tacticB['manager'] ?? '')) ?></h3>
                        <p class="muted"><?= e(($tacticB['club'] ?? '') . ' ' . ($tacticB['season'] ?? '')) ?></p>
                        <?= renderPitch(shapedSlots($pB, $shape, $shapesB), ['id' => 'cmp-b', 'showLabels' => true, 'interactive' => false]) ?>
                        <p class="hint mt-2"><?= e($shape === 'with' ? ('With ball: ' . $shapesB['withBall']) : ($shape === 'without' ? ('Without ball: ' . $shapesB['withoutBall']) : ('Base: ' . $shapesB['base']))) ?></p>
                        <a class="btn btn-ghost btn-sm btn-block mt-2" href="<?= e(url('tactic.php?id=' . rawurlencode((string) $tacticB['id']))) ?>">Open <?= e((string) ($tacticB['club'] ?? '')) ?></a>
                    <?php else: ?>
                        <div class="empty-state"><div class="big">➕</div><p class="muted">Select Tactic B</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tactical DNA comparison -->
            <div class="panel mt-2">
                <h2>Tactical DNA Comparison</h2>
                <p class="muted">Tactical Profile — Fan Recreation. Values are subjective profiles built for EA Sports FC, not objective measurements.</p>
                <div class="dna-compare">
                    <div class="dna-compare-legend">
                        <span class="dna-key dna-key-a"></span> <?= e($labelA) ?>
                        <span class="dna-key dna-key-b"></span> <?= e($labelB) ?>
                    </div>
                    <?php foreach (dnaCategories() as $cat):
                        $va = (int) ($dnaA[$cat] ?? 0);
                        $vb = (int) ($dnaB[$cat] ?? 0);
                    ?>
                        <div class="dna-compare-row">
                            <span class="dna-label"><?= e($cat) ?></span>
                            <div class="dna-compare-bars">
                                <div class="dna-compare-bar">
                                    <span class="dna-fill dna-a" style="width:<?= $va ?>%"></span>
                                    <span class="dna-compare-val"><?= $va ?></span>
                                </div>
                                <div class="dna-compare-bar">
                                    <span class="dna-fill dna-b" style="width:<?= $vb ?>%"></span>
                                    <span class="dna-compare-val"><?= $vb ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Attribute table -->
            <div class="panel mt-2">
                <h2>Side-by-side comparison <span class="muted" style="font-size:.9rem;">(<?= e(versionLabel($version)) ?>)</span></h2>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr><th>Attribute</th><th><?= e($labelA) ?></th><th><?= e($labelB) ?></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowDefs as $label => $vals): ?>
                                <tr<?= (string) $vals[0] !== (string) $vals[1] ? ' style="background:rgba(35,180,255,0.06);"' : '' ?>>
                                    <td><strong><?= e($label) ?></strong></td>
                                    <td><?= e((string) $vals[0]) ?></td>
                                    <td><?= e((string) $vals[1]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-3">Player roles &amp; focuses per position</h3>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>Pos</th><th><?= e($labelA) ?></th><th><?= e($labelB) ?></th></tr></thead>
                        <tbody>
                            <?php
                            $maxPlayers = max(count($pA), count($pB));
                            for ($i = 0; $i < $maxPlayers; $i++):
                                $a = $pA[$i] ?? null;
                                $b = $pB[$i] ?? null;
                            ?>
                                <tr>
                                    <td><span class="badge badge-pos"><?= e((string) (($a['position'] ?? '') ?: ($b['position'] ?? ''))) ?></span></td>
                                    <td>
                                        <?php if ($a): ?>
                                            <span class="badge badge-role"><?= e($a['role']) ?></span>
                                            <?php if ($a['focus']): ?><span class="badge badge-focus"><?= e($a['focus']) ?></span><?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b): ?>
                                            <span class="badge badge-role"><?= e($b['role']) ?></span>
                                            <?php if ($b['focus']): ?><span class="badge badge-focus"><?= e($b['focus']) ?></span><?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-3">Tactical philosophy</h3>
                <div class="compare-philosophy">
                    <div class="panel">
                        <h4 class="mt-0"><?= e($labelA) ?></h4>
                        <p class="muted mb-0"><?= e((string) ($tacticA['philosophy'] ?? '—')) ?></p>
                    </div>
                    <div class="panel">
                        <h4 class="mt-0"><?= e($labelB) ?></h4>
                        <p class="muted mb-0"><?= e((string) ($tacticB['philosophy'] ?? '—')) ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="big">⚖️</div>
                <h2>Pick two tactics to compare</h2>
                <p>Use the selectors above to compare any two tactics side by side across DNA, shapes, roles and philosophy.</p>
                <a class="btn btn-ghost btn-sm mt-2" href="<?= e(url('tactics.php')) ?>">Browse tactics</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
