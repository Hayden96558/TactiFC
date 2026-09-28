<?php
/**
 * TactiFC — Formation explorer.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/pitch.php';
require_once __DIR__ . '/includes/cards.php';

$PAGE_TITLE = 'Formation Explorer — FC25, FC26 & FC27 | TactiFC';
$PAGE_DESC  = 'Browse football formations for EA Sports FC25, FC26 and FC27, each with an interactive pitch showing player positions.';

$formations = loadFormations();
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="container">
        <h1>Formation Explorer</h1>
        <p>Choose a shape to see how players are arranged on the pitch. Every formation links straight to tactics that use it in EA Sports FC25, FC26 and FC27.</p>
    </div>
</div>

<section class="section" style="padding-top: 16px;">
    <div class="container">
        <div class="grid grid-3">
            <?php foreach ($formations as $formation): ?>
                <?= renderFormationCard($formation) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
