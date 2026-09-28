<?php
/**
 * TactiFC — Single player role detail + role comparison.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$name = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
$role = $name !== '' ? getRoleFromCatalogue($name) : null;

$compareWith = isset($_GET['compare']) ? trim((string) $_GET['compare']) : '';
$compareRole = $compareWith !== '' ? getRoleFromCatalogue($compareWith) : null;

if ($role === null) {
    http_response_code(404);
    $PAGE_TITLE = 'Role not found | TactiFC';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section text-center"><div class="empty-state"><div class="big">🎽</div><h1>Role not found</h1><p class="muted">That role does not exist. <a href="' . e(url('roles.php')) . '">Browse all roles</a>.</p></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$roleName = (string) $role['name'];
$tactics  = tacticsByRole($roleName);

$PAGE_TITLE = $roleName . ' — Player Role | TactiFC';
$PAGE_DESC  = $role['description'] . ' Available in ' . implode(', ', array_map('versionLabel', $role['versions'])) . ' for positions ' . implode(', ', $role['positions']) . '.';

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <div class="section-eyebrow"><a href="<?= e(url('roles.php')) ?>">Player Roles</a></div>
        <h1><?= e($roleName) ?></h1>
        <div class="tag-row">
            <?php foreach ($role['positions'] as $p): ?>
                <span class="badge badge-pos"><?= e($p) ?></span>
            <?php endforeach; ?>
            <?php foreach ($role['versions'] as $v): ?>
                <span class="version-pill is-on"><?= e(versionLabel($v)) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<section class="section" style="padding-top: 16px;">
    <div class="container">
        <div class="tactic-layout">
            <div>
                <div class="panel">
                    <h2>Description</h2>
                    <p class="muted"><?= e((string) $role['description']) ?></p>

                    <div class="role-behaviours mt-2">
                        <div class="behaviour">
                            <h4>Attacking Behaviour</h4>
                            <ul>
                                <?php foreach (($role['attacking'] ?? []) as $b): ?>
                                    <li><?= e((string) $b) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="behaviour">
                            <h4>Defensive Behaviour</h4>
                            <ul>
                                <?php foreach (($role['defending'] ?? []) as $b): ?>
                                    <li><?= e((string) $b) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Compare tool -->
                <div class="panel">
                    <h2>Compare this role</h2>
                    <form method="get" action="<?= e(url('role.php')) ?>">
                        <input type="hidden" name="name" value="<?= e($roleName) ?>">
                        <div class="filter-grid">
                            <div class="field">
                                <label for="compare">Compare with</label>
                                <select id="compare" name="compare">
                                    <option value="">Select a role…</option>
                                    <?php foreach (roleCatalogue() as $r): ?>
                                        <?php if (strcasecmp($r['name'], $roleName) === 0) continue; ?>
                                        <option value="<?= e($r['name']) ?>"<?= $compareRole && strcasecmp($compareRole['name'], $r['name']) === 0 ? ' selected' : '' ?>><?= e($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary btn-sm">Compare</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($compareRole): ?>
                        <div class="compare-table mt-2">
                            <div class="ch">Attribute</div>
                            <div class="ch"><?= e($roleName) ?></div>
                            <div class="ch"><?= e((string) $compareRole['name']) ?></div>

                            <div class="cl">Positions</div>
                            <div class="cc"><?= e(implode(', ', $role['positions'])) ?></div>
                            <div class="cc"><?= e(implode(', ', $compareRole['positions'])) ?></div>

                            <div class="cl">Focuses</div>
                            <div class="cc"><?= e(implode(', ', $role['focuses'])) ?></div>
                            <div class="cc"><?= e(implode(', ', $compareRole['focuses'])) ?></div>

                            <div class="cl">Versions</div>
                            <div class="cc"><?= e(implode(', ', array_map('versionLabel', $role['versions']))) ?></div>
                            <div class="cc is-diff"><?= e(implode(', ', array_map('versionLabel', $compareRole['versions']))) ?></div>

                            <div class="cl">Description</div>
                            <div class="cc"><?= e((string) $role['description']) ?></div>
                            <div class="cc is-diff"><?= e((string) $compareRole['description']) ?></div>

                            <div class="cl">Attacking</div>
                            <div class="cc"><?= e(implode('; ', $role['attacking'] ?? [])) ?></div>
                            <div class="cc is-diff"><?= e(implode('; ', $compareRole['attacking'] ?? [])) ?></div>

                            <div class="cl">Defending</div>
                            <div class="cc"><?= e(implode('; ', $role['defending'] ?? [])) ?></div>
                            <div class="cc is-diff"><?= e(implode('; ', $compareRole['defending'] ?? [])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="panel">
                    <h2>Focus Options</h2>
                    <div class="pill-row">
                        <?php foreach ($role['focuses'] as $f): ?>
                            <span class="badge badge-focus"><?= e($f) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="hint mt-2">Focuses adjust how aggressively a role is played — for example Defend, Balanced or Attack.</p>
                </div>

                <div class="panel">
                    <h2>Familiarity</h2>
                    <p class="muted">FC25 introduced Role Familiarity so you can see how comfortable a player is in a role.</p>
                    <ul class="muted" style="padding-left:18px;">
                        <li><strong>Role</strong> — competent</li>
                        <li><strong>Role+</strong> — strong</li>
                        <li><strong>Role++</strong> — elite</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($tactics): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">In practice</div>
                <h2>Tactics using <?= e($roleName) ?></h2>
            </div>
        </div>
        <div class="grid grid-3">
            <?php foreach (array_slice($tactics, 0, 6) as $tactic): ?>
                <?= renderTacticCard($tactic, ['showRoles' => false]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
