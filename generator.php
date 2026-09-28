<?php
/**
 * TactiFC — Tactic Generator.
 *
 * Two tools in one page:
 *   1. Tactic Generator — build a rule-based setup from style/formation/
 *      strength/manager inspiration.
 *   2. Recommendation Engine — find existing tactics that match your
 *      preferences, with an explanation of why each appeared.
 *
 * Everything runs locally in PHP/JS. No external AI API.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/pitch.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Tactic Generator & Recommendations — TactiFC';
$PAGE_DESC  = 'Generate a custom EA Sports FC tactic from your preferred style, formation and squad strengths, or get database recommendations that explain why each tactic fits.';

$styles = ['Possession', 'Counter Attack', 'High Press', 'Low Block', 'Direct', 'Balanced'];
$strengths = ['Fast Wingers', 'Strong Striker', 'Creative CAM', 'Defensive Midfield', 'Attacking Fullbacks', 'Strong Centre Backs'];
$formationList = [];
foreach (loadFormations() as $f) {
    $formationList[(string) $f['name']] = (string) $f['name'];
}
$managerNames = [];
foreach (loadManagers() as $m) {
    $managerNames[] = (string) $m['name'];
}
$versions = availableVersions();

/* ---- Handle form submissions ------------------------------------------ */
$generated = null;
$recs = [];
$recPrefs = [];
$mode = isset($_GET['tool']) ? (string) $_GET['tool'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['generate'])) {
    $generated = generateTactic([
        'style'       => (string) ($_GET['style'] ?? 'Balanced'),
        'formation'   => (string) ($_GET['formation'] ?? '4-3-3'),
        'strength'    => (string) ($_GET['strength'] ?? 'Fast Wingers'),
        'inspiration' => (string) ($_GET['inspiration'] ?? ''),
        'version'     => (string) ($_GET['version'] ?? 'fc26'),
    ]);
    $mode = 'generate';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['recommend'])) {
    $recPrefs = [
        'playstyle'  => (string) ($_GET['playstyle'] ?? ''),
        'formation'  => (string) ($_GET['rec_formation'] ?? ''),
        'strength'   => (string) ($_GET['rec_strength'] ?? ''),
        'defensive'  => (string) ($_GET['defensive'] ?? ''),
        'gameMode'   => (string) ($_GET['rec_mode'] ?? ''),
        'version'    => (string) ($_GET['rec_version'] ?? ''),
    ];
    $recs = recommendTactics(array_filter($recPrefs, static fn ($v) => $v !== ''), 6);
    $mode = 'recommend';
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Generator &amp; Recommendations</h1>
        <p>Generate a fresh tactical setup from your preferences, or ask the recommendation engine to surface tactics from the database that match your style — with an explanation of why each appeared.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <div class="tools-grid">
            <!-- Generator -->
            <div class="panel" id="generator">
                <div class="section-eyebrow">Tool 1</div>
                <h2 class="mt-0">Tactic Generator</h2>
                <p class="muted">Rule-based generation using the existing database and version-specific role data. No external AI.</p>

                <form method="get" action="<?= e(url('generator.php')) ?>">
                    <input type="hidden" name="tool" value="generate">
                    <input type="hidden" name="generate" value="1">

                    <div class="field mb-2">
                        <label for="g-style">Preferred Style</label>
                        <select id="g-style" name="style">
                            <?php foreach ($styles as $s): ?>
                                <option value="<?= e($s) ?>"<?= (($_GET['style'] ?? '') === $s) ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="g-formation">Formation</label>
                        <select id="g-formation" name="formation">
                            <?php foreach ($formationList as $f): ?>
                                <option value="<?= e($f) ?>"<?= (($_GET['formation'] ?? '4-3-3') === $f) ? ' selected' : '' ?>><?= e($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="g-strength">Main Strength</label>
                        <select id="g-strength" name="strength">
                            <?php foreach ($strengths as $s): ?>
                                <option value="<?= e($s) ?>"<?= (($_GET['strength'] ?? '') === $s) ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="g-inspiration">Manager Inspiration <span class="dim">(optional)</span></label>
                        <select id="g-inspiration" name="inspiration">
                            <option value="">None</option>
                            <?php foreach ($managerNames as $m): ?>
                                <option value="<?= e($m) ?>"<?= (($_GET['inspiration'] ?? '') === $m) ? ' selected' : '' ?>><?= e($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="g-version">FC Version</label>
                        <select id="g-version" name="version">
                            <?php foreach ($versions as $v): ?>
                                <option value="<?= e($v) ?>"<?= (($_GET['version'] ?? 'fc26') === $v) ? ' selected' : '' ?>><?= e(versionLabel($v)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Generate Tactic</button>
                </form>
            </div>

            <!-- Recommendation engine -->
            <div class="panel" id="recommend">
                <div class="section-eyebrow">Tool 2</div>
                <h2 class="mt-0">Recommendation Engine</h2>
                <p class="muted">Find tactics from the database that match your preferences. Each result explains why it appeared.</p>

                <form method="get" action="<?= e(url('generator.php')) ?>">
                    <input type="hidden" name="tool" value="recommend">
                    <input type="hidden" name="recommend" value="1">

                    <div class="field mb-2">
                        <label for="r-playstyle">Playstyle</label>
                        <select id="r-playstyle" name="playstyle">
                            <option value="">Any</option>
                            <?php foreach (tacticStyles() as $s): ?>
                                <option value="<?= e($s) ?>"<?= (($_GET['playstyle'] ?? '') === $s) ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="r-formation">Preferred Formation</label>
                        <select id="r-formation" name="rec_formation">
                            <option value="">Any</option>
                            <?php foreach ($formationList as $f): ?>
                                <option value="<?= e($f) ?>"<?= (($_GET['rec_formation'] ?? '') === $f) ? ' selected' : '' ?>><?= e($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="r-strength">Main Strength</label>
                        <select id="r-strength" name="rec_strength">
                            <option value="">Any</option>
                            <?php foreach ($strengths as $s): ?>
                                <option value="<?= e($s) ?>"<?= (($_GET['rec_strength'] ?? '') === $s) ? ' selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="r-defensive">Defensive Preference</label>
                        <select id="r-defensive" name="defensive">
                            <option value="">Any</option>
                            <option value="Compact"<?= (($_GET['defensive'] ?? '') === 'Compact') ? ' selected' : '' ?>>Compact</option>
                            <option value="Low Block"<?= (($_GET['defensive'] ?? '') === 'Low Block') ? ' selected' : '' ?>>Low Block</option>
                            <option value="High Press"<?= (($_GET['defensive'] ?? '') === 'High Press') ? ' selected' : '' ?>>High Press</option>
                            <option value="Defensive"<?= (($_GET['defensive'] ?? '') === 'Defensive') ? ' selected' : '' ?>>Defensive</option>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="r-mode">Game Mode</label>
                        <select id="r-mode" name="rec_mode">
                            <option value="">Any</option>
                            <?php foreach (allGameModes() as $m): ?>
                                <option value="<?= e($m) ?>"<?= (($_GET['rec_mode'] ?? '') === $m) ? ' selected' : '' ?>><?= e($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="r-version">FC Version</label>
                        <select id="r-version" name="rec_version">
                            <option value="">Any</option>
                            <?php foreach ($versions as $v): ?>
                                <option value="<?= e($v) ?>"<?= (($_GET['rec_version'] ?? '') === $v) ? ' selected' : '' ?>><?= e(versionLabel($v)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Find Tactics</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if ($generated): ?>
<section class="section" id="results" style="padding-top:0;">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Generated</div>
                <h2>Your Generated <?= e($generated['style']) ?> Setup</h2>
                <p>Built from the <?= e($generated['formation']) ?> shape using <?= e(versionLabel($generated['version'])) ?>-valid player roles.</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('builder.php')) ?>">Open in Builder</a>
        </div>

        <div class="tactic-layout">
            <div class="panel">
                <?= renderPitch($generated['players'], ['id' => 'generated-pitch', 'showLabels' => true, 'interactive' => false]) ?>
            </div>
            <div>
                <div class="panel">
                    <h2>Settings</h2>
                    <div class="stat-grid">
                        <div class="stat"><div class="stat-label">Style</div><div class="stat-value"><?= e($generated['style']) ?></div></div>
                        <div class="stat"><div class="stat-label">Formation</div><div class="stat-value"><?= e($generated['formation']) ?></div></div>
                        <div class="stat"><div class="stat-label">Build Up</div><div class="stat-value"><?= e($generated['settings']['buildUp']) ?></div></div>
                        <div class="stat"><div class="stat-label">Defensive</div><div class="stat-value"><?= e($generated['settings']['defensiveApproach']) ?></div></div>
                        <div class="stat"><div class="stat-label">Width</div><div class="stat-value"><?= (int) $generated['settings']['width'] ?>/10</div></div>
                        <div class="stat"><div class="stat-label">Depth</div><div class="stat-value"><?= (int) $generated['settings']['depth'] ?>/10</div></div>
                    </div>
                    <p class="muted mt-2"><?= e($generated['settings']['blurb']) ?></p>
                    <?php if (!empty($generated['inspiration'])): ?>
                        <p class="muted"><strong>Manager inspiration:</strong> <?= e($generated['inspiration']) ?></p>
                    <?php endif; ?>
                    <p class="muted"><strong>Main strength:</strong> <?= e($generated['strength']) ?></p>
                </div>

                <div class="panel">
                    <h2>Generated Roles</h2>
                    <div class="table-scroll">
                        <table class="data-table">
                            <thead><tr><th>Pos</th><th>Role</th><th>Focus</th></tr></thead>
                            <tbody>
                                <?php foreach ($generated['players'] as $p): ?>
                                    <tr>
                                        <td><span class="badge badge-pos"><?= e($p['position']) ?></span></td>
                                        <td><a href="<?= e(url('role.php?name=' . rawurlencode((string) $p['role']))) ?>"><?= e((string) $p['role']) ?></a></td>
                                        <td><span class="badge badge-focus"><?= e((string) $p['focus']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($mode === 'recommend'): ?>
<section class="section" id="results" style="padding-top:0;">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Recommendations</div>
                <h2><?= count($recs) ?> matching tactic<?= count($recs) === 1 ? '' : 's' ?></h2>
                <p>Ranked by how closely each tactic matches your preferences. Each card shows why it appeared.</p>
            </div>
        </div>

        <?php if ($recs): ?>
            <div class="stack">
                <?php foreach ($recs as $rec): ?>
                    <div class="rec-card">
                        <div class="rec-main">
                            <?= renderTacticCard($rec['tactic'], ['showRoles' => false]) ?>
                        </div>
                        <div class="rec-why">
                            <div class="rec-why-head">
                                <h3 class="mt-0">Why this appeared</h3>
                                <span class="rec-score" title="Match score from your preferences">Match <?= (int) $rec['score'] ?></span>
                            </div>
                            <p class="muted mb-0">Matches your preferences:</p>
                            <ul class="rec-reasons">
                                <?php foreach ($rec['reasons'] as $reason): ?>
                                    <li><span class="check">✓</span> <?= e($reason) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="big">🔍</div>
                <h2>No matching tactics</h2>
                <p>Try loosening your preferences — for example, choose "Any" for formation or game mode.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($mode === ''): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="notice">
            <strong>How this works:</strong> The generator applies transparent rule-based logic to the existing database and player-role data, then validates every role against the selected FC version. The recommendation engine scores tactics against your choices and explains each match. Neither tool claims to produce an "objectively optimal" tactic.
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
