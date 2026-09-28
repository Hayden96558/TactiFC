<?php
/**
 * TactiFC — Manager database.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Manager Tactics — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Browse legendary football managers and the tactics inspired by their greatest sides, playable in EA Sports FC25, FC26 and FC27.';

$managers = loadManagers();
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Manager Database</h1>
        <p>Discover the tactical identities of legendary managers. Each profile links to gameplay recreations inspired by their greatest sides.</p>
    </div>
</div>

<section class="section" style="padding-top: 16px;">
    <div class="container">
        <div class="filters">
            <div class="filter-grid">
                <div class="field">
                    <label for="managerSearch">Search managers</label>
                    <input id="managerSearch" type="search" placeholder="Name, club or style…" data-filter-target="#managerGrid .manager-card" autocomplete="off">
                </div>
                <div class="field">
                    <label for="managerStyle">Playstyle</label>
                    <select id="managerStyle" data-filter-target="#managerGrid .manager-card" data-filter-attr="data-styles">
                        <option value="">All styles</option>
                        <?php
                        $allStyles = [];
                        foreach ($managers as $m) {
                            foreach ($m['styles'] ?? [] as $s) { $allStyles[$s] = true; }
                        }
                        $allStyles = array_keys($allStyles);
                        sort($allStyles);
                        foreach ($allStyles as $s): ?>
                            <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid grid-3" id="managerGrid">
            <?php foreach ($managers as $manager): ?>
                <?= renderManagerCard($manager) ?>
            <?php endforeach; ?>
        </div>
        <p class="empty-state" id="managerNoResults" hidden>No managers match your search.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
