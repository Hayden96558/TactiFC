<?php
/**
 * TactiFC — Reusable site footer + script includes.
 */
declare(strict_types=1);
?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <span class="brand-text">Tacti<span class="brand-accent">FC</span></span>
            <p>Formations, manager tactics and player roles for EA Sports FC25, FC26 &amp; FC27.</p>
        </div>

        <div class="footer-col">
            <h3>Explore</h3>
            <ul>
                <li><a href="<?= e(url('formations.php')) ?>">Formations</a></li>
                <li><a href="<?= e(url('managers.php')) ?>">Managers</a></li>
                <li><a href="<?= e(url('tactics.php')) ?>">Tactics</a></li>
                <li><a href="<?= e(url('roles.php')) ?>">Player Roles</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>Tools</h3>
            <ul>
                <li><a href="<?= e(url('generator.php')) ?>">Tactic Generator</a></li>
                <li><a href="<?= e(url('builder.php')) ?>">Tactic Builder</a></li>
                <li><a href="<?= e(url('player-fit.php')) ?>">Player Fit</a></li>
                <li><a href="<?= e(url('compare.php')) ?>">Compare Tactics</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>Discover</h3>
            <ul>
                <li><a href="<?= e(url('recommendations.php')) ?>">Recommendations</a></li>
                <li><a href="<?= e(url('trending.php')) ?>">Trending</a></li>
                <li><a href="<?= e(url('favorites.php')) ?>">Favorites &amp; History</a></li>
                <li><a href="<?= e(url('roles.php')) ?>">Player Roles</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>About</h3>
            <p class="footer-note">
                Historical tactics are <strong>inspired by</strong> the managers and teams referenced.
                They are approximations created for EA Sports FC, not exact recreations.
            </p>
        </div>
    </div>
    <div class="footer-bottom container">
        <p>&copy; <?= date('Y') ?> TactiFC. An unofficial fan resource. Not affiliated with EA Sports.</p>
        <p class="footer-disclaimer">EA Sports FC is a trademark of Electronic Arts. All club and manager names are used descriptively.</p>
    </div>
</footer>

<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/search.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/favorites.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/share.js')) ?>" defer></script>
<?php if (!empty($EXTRA_SCRIPTS)): foreach ($EXTRA_SCRIPTS as $script): ?>
<script src="<?= e(url($script)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
