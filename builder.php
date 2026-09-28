<?php
/**
 * TactiFC — Tactic Builder.
 *
 * Users choose a formation and FC version, then assign each pitch position a
 * role, focus, familiarity and instructions. Supports base / with-ball /
 * without-ball views, save, duplicate, rename, delete and import/export.
 * Custom tactics are saved to localStorage (no account required).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';

$PAGE_TITLE = 'Tactic Builder — Design Your Own FC Tactic | TactiFC';
$PAGE_DESC  = 'Build your own custom EA Sports FC tactic: choose a formation, assign version-specific player roles, focuses and instructions, switch between base and with/without-ball shapes, then save or export it.';

$formations = loadFormations();
$versions   = availableVersions();

$prefillId = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$prefillFormation = isset($_GET['formation']) ? trim((string) $_GET['formation']) : '';
$prefillTactic = $prefillId !== '' ? getTacticById($prefillId) : null;

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Tactic Builder</h1>
        <p>Choose a formation and FC version, then click a player to set their position, role, focus, familiarity and instructions. Role options automatically match the selected position and game version. Saved locally — no account needed.</p>
    </div>
</div>

<section class="section" style="padding-top: 10px;">
    <div class="container">
        <div id="builderApp"
             class="builder-layout"
             data-role-db='<?= e(json_encode(loadPlayerRoles(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
             data-formations='<?= e(json_encode($formations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
             data-prefill='<?= e(json_encode([
                 'tactic'    => $prefillTactic,
                 'formation' => $prefillFormation,
             ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
            <!-- Pitch + preview -->
            <div>
                <div class="filters">
                    <div class="filter-grid">
                        <div class="field">
                            <label for="b-name">Tactic name</label>
                            <input id="b-name" type="text" placeholder="e.g. My Counter-Press 4-3-3" maxlength="80" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="b-formation">Formation</label>
                            <select id="b-formation">
                                <?php foreach ($formations as $f): ?>
                                    <option value="<?= e((string) $f['id']) ?>"><?= e((string) $f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="b-version">FC Version</label>
                            <select id="b-version">
                                <?php foreach ($versions as $v): ?>
                                    <option value="<?= e($v) ?>"><?= e(versionLabel($v)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="b-style">Playstyle</label>
                            <select id="b-style">
                                <option value="">Select a style…</option>
                                <?php foreach (tacticStyles() as $s): ?>
                                    <option value="<?= e($s) ?>"><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions mt-2">
                        <button type="button" class="btn btn-ghost btn-sm" id="b-reset">Reset Formation</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="b-clear">Clear Pitch</button>
                        <button type="button" class="btn btn-primary btn-sm" id="b-save">Save Tactic</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="b-export">Export JSON</button>
                        <label class="btn btn-ghost btn-sm" for="b-import">Import JSON</label>
                        <input id="b-import" type="file" accept="application/json,.json" hidden>
                    </div>
                </div>

                <div class="panel">
                    <div class="shape-toggle" role="tablist" aria-label="Builder shape view">
                        <button type="button" class="btn btn-primary btn-sm bshape-btn is-active" data-bshape="base" role="tab" aria-selected="true">BASE</button>
                        <button type="button" class="btn btn-ghost btn-sm bshape-btn" data-bshape="with" role="tab" aria-selected="false">WITH BALL</button>
                        <button type="button" class="btn btn-ghost btn-sm bshape-btn" data-bshape="without" role="tab" aria-selected="false">WITHOUT BALL</button>
                    </div>

                    <div class="pitch-host" id="builderPitchHost">
                        <!-- Pitch rendered by JS -->
                    </div>
                    <p class="hint text-center mt-2">Click a player marker to edit. <strong>Drag</strong> a marker in Base view to move it. With/without-ball views preview how the shape morphs.</p>
                </div>
            </div>

            <!-- Sidebar editor -->
            <aside class="builder-sidebar">
                <div class="panel builder-controls">
                    <h2 class="mt-0">Selected Player</h2>
                    <div id="noSelection" class="hint">Click a player on the pitch to edit their role.</div>

                    <div id="selectionForm" hidden>
                        <div class="field">
                            <label for="p-position">Position</label>
                            <select id="p-position"></select>
                        </div>
                        <div class="field">
                            <label for="p-role">Role</label>
                            <select id="p-role"></select>
                        </div>
                        <div class="field">
                            <label for="p-focus">Focus</label>
                            <select id="p-focus"></select>
                        </div>
                        <div class="field">
                            <label for="p-fam">Role Familiarity</label>
                            <select id="p-fam">
                                <option value="">None</option>
                                <option value="Role">Role</option>
                                <option value="Role+">Role+</option>
                                <option value="Role++">Role++</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="p-instructions">Player Instructions</label>
                            <textarea id="p-instructions" rows="3" placeholder="e.g. Stay wide, overlap, get in behind…" maxlength="240"></textarea>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm btn-block" id="p-clear">Clear player</button>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="mt-0">Squad Roles</h2>
                    <div class="builder-player-list" id="builderPlayerList"></div>
                </div>

                <div class="panel">
                    <h2 class="mt-0">Role Info</h2>
                    <div id="roleInfo" class="hint">Select a player and role to see a description.</div>
                </div>

                <div class="panel">
                    <h2 class="mt-0">Saved Tactics</h2>
                    <div id="savedList" class="builder-player-list"></div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php $EXTRA_SCRIPTS = ['assets/js/builder.js']; include __DIR__ . '/includes/footer.php'; ?>
