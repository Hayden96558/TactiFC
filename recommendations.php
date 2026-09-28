<?php
/**
 * TactiFC — Recommendations by category and by player role.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Tactical Recommendations — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Recommended tactics for counter-attacking, possession, high press, low block, career mode, Ultimate Team and more, plus role-based suggestions.';

$categories = recommendationCategories();
$roleGroups  = roleRecommendationGroups();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Tactical Recommendations</h1>
        <p>Starting points grouped by playing style and by player role. These are suggestions to explore — not objective claims about the “best” tactics or roles.</p>
    </div>
</div>

<section class="section" style="padding-top: 16px;">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">By playstyle</div>
                <h2>Style Categories</h2>
            </div>
        </div>

        <div class="stack">
            <?php foreach ($categories as $cat):
                $picks = tacticsByStyles($cat['styles'], 3);
            ?>
                <div class="panel" id="<?= e($cat['id']) ?>">
                    <div class="section-head" style="margin-bottom:14px;">
                        <div>
                            <h2 class="mb-0"><?= e($cat['icon']) ?> <?= e($cat['title']) ?></h2>
                            <p class="muted mb-0"><?= e($cat['blurb']) ?></p>
                        </div>
                    </div>
                    <?php if ($picks): ?>
                        <div class="grid grid-3">
                            <?php foreach ($picks as $tactic): ?>
                                <?= renderTacticCard($tactic, ['showRoles' => false]) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No tactics currently match this category.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">By player role</div>
                <h2>Role-Based Recommendations</h2>
                <p>Looking for a specific style of player? These groups show tactics that use the relevant roles. Treat them as categories to explore, not “best role” rankings.</p>
            </div>
        </div>

        <div class="stack">
            <?php foreach ($roleGroups as $group):
                $matches = [];
                foreach ($group['roles'] as $roleName) {
                    foreach (tacticsByRole($roleName) as $t) {
                        $matches[$t['id']] = $t;
                    }
                }
                $matches = array_slice(array_values($matches), 0, 3);
            ?>
                <div class="panel" id="<?= e($group['id']) ?>">
                    <h2><?= e($group['title']) ?></h2>
                    <p class="muted"><?= e($group['blurb']) ?></p>
                    <div class="pill-row mb-2">
                        <?php foreach ($group['roles'] as $roleName): ?>
                            <a class="role-badge" href="<?= e(url('role.php?name=' . rawurlencode($roleName))) ?>">
                                <span class="rb-pos">Role</span><span class="rb-sep">·</span><span><?= e($roleName) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($matches): ?>
                        <div class="grid grid-3">
                            <?php foreach ($matches as $tactic): ?>
                                <?= renderTacticCard($tactic, ['showRoles' => false]) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No tactics currently use these roles.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
