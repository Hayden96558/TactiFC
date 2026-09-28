<?php
/**
 * TactiFC — Player Fit System.
 *
 * Users build a squad (name, position, stats, preferred position/roles) and
 * pick a tactic. The page suggests a role per player with a transparent
 * "TactiFC Tactical Fit" score. This is NOT an official EA rating.
 *
 * Squad data is stored client-side in localStorage; fit scoring is done both
 * client-side (instant) and can be re-run server-side for transparency.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Player Fit — Find Roles For Your Squad | TactiFC';
$PAGE_DESC  = 'Enter your EA Sports FC squad and see which player roles suit each player for a chosen tactic. A transparent TactiFC Tactical Fit estimate, not an official EA rating.';

$tactics = loadTactics();
$positions = allPositions();

$squadIndex = [];
foreach ($tactics as $t) {
    $squadIndex[$t['id']] = [
        'id'        => $t['id'],
        'manager'   => $t['manager'] ?? '',
        'club'      => $t['club'] ?? '',
        'season'    => $t['season'] ?? '',
        'formation' => $t['formation'] ?? '',
        'versions'  => $t['versions'] ?? [],
    ];
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Player Fit</h1>
        <p>Enter your squad and choose a tactic. TactiFC will suggest a role for each player and estimate how well they fit it. This is a transparent, rule-based estimate — clearly labelled as <strong>TactiFC Tactical Fit</strong>, not an official EA rating.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;" id="playerFitApp"
         data-position-list='<?= e(json_encode($positions)) ?>'
         data-tactics='<?= e(json_encode($squadIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
         data-role-db='<?= e(json_encode(loadPlayerRoles(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
    <div class="container">
        <div class="tactic-layout">
            <!-- Squad input -->
            <div>
                <div class="panel">
                    <div class="section-head" style="margin-bottom:14px;">
                        <div>
                            <h2 class="mb-0">Your Squad</h2>
                            <p class="muted mb-0">Add players with their key attributes. Everything is stored in your browser.</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" id="pf-sample">Load Sample Squad</button>
                    </div>

                    <div id="pfSquadList" class="squad-list"></div>

                    <div class="filter-actions mt-2">
                        <button type="button" class="btn btn-primary btn-sm" id="pf-add">+ Add Player</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="pf-clear">Clear Squad</button>
                    </div>
                </div>

                <div class="panel">
                    <div class="section-head" style="margin-bottom:14px;">
                        <div>
                            <h2 class="mb-0">Player Fit</h2>
                            <p class="muted mb-0">Suggested roles and fit for the selected tactic.</p>
                        </div>
                    </div>

                    <div class="filter-grid mb-2">
                        <div class="field">
                            <label for="pf-tactic">Choose a tactic</label>
                            <select id="pf-tactic">
                                <option value="">Select a tactic…</option>
                                <?php foreach ($tactics as $t): ?>
                                    <option value="<?= e((string) $t['id']) ?>">
                                        <?= e(($t['manager'] ?? '') . ' — ' . ($t['club'] ?? '') . ' ' . ($t['season'] ?? '') . ' (' . ($t['formation'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="pf-version">FC Version</label>
                            <select id="pf-version">
                                <?php foreach (availableVersions() as $v): ?>
                                    <option value="<?= e($v) ?>"><?= e(versionLabel($v)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="pfResults" class="fit-results">
                        <div class="empty-state">
                            <div class="big">🎯</div>
                            <p class="muted">Add players and choose a tactic to see suggested roles.</p>
                        </div>
                    </div>

                    <p class="hint mt-2">TactiFC Tactical Fit is a transparent weighted estimate based on the attributes and preferred position you enter. It is not an official EA rating and does not predict in-game performance.</p>
                </div>
            </div>

            <!-- Explanation -->
            <div>
                <div class="panel">
                    <h2>How Fit Is Calculated</h2>
                    <p class="muted">Each role is weighted toward the attributes that matter most for it, then adjusted for preferred position:</p>
                    <ul class="muted" style="padding-left:18px;">
                        <li><strong>Forward roles</strong> weight pace, shooting and dribbling most.</li>
                        <li><strong>Creative midfield roles</strong> weight passing and dribbling.</li>
                        <li><strong>Defensive roles</strong> weight defending and physicality.</li>
                        <li><strong>Wide roles</strong> weight pace and dribbling.</li>
                        <li>A matching <strong>preferred position</strong> adds a bonus; a mismatched one reduces the score.</li>
                    </ul>
                    <p class="hint">Fit bands: Excellent (85+), Good (72+), Fair (58+), Poor (below 58).</p>
                </div>

                <div class="panel">
                    <h2>Position Compatibility</h2>
                    <p class="muted">Players can be suggested for roles whose base position matches their preferred position or belongs to the same group (defence / midfield / attack).</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php $EXTRA_SCRIPTS = ['assets/js/player-fit.js']; include __DIR__ . '/includes/footer.php'; ?>
