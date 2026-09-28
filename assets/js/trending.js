/* =========================================================================
   TactiFC — trending.js
   Renders the personal "most explored" list from localStorage activity.
   Labelled as demo statistics (browser-only). No fake site-wide data.
   ========================================================================= */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    function esc(s) {
        return (s || '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function render() {
        const grid = document.getElementById('trendingGrid');
        if (!grid) return;
        const FAV = window.TactiFCFavorites;
        const index = window.TACTIFC_INDEX || {};
        const base = (window.TACTIFC && window.TACTIFC.base) || '';
        if (!FAV) return;

        const ranked = FAV.getTrending(9);
        const empty = document.getElementById('trendingEmpty');

        if (!ranked.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;

        grid.innerHTML = ranked.map((entry) => {
            const t = index[entry.id];
            if (!t) return '';
            const href = base + '/tactic.php?id=' + encodeURIComponent(t.id);
            const versions = Object.keys(t.versions || {});
            const pills = ['fc25', 'fc26', 'fc27']
                .map((v) => '<span class="version-pill' + (versions.indexOf(v) !== -1 ? ' is-on' : '') + '">' + v.toUpperCase() + '</span>')
                .join('');
            return '<article class="card tactic-card">' +
                '<div class="card-top"><div>' +
                '<h3 class="card-title">' + esc(t.manager) + '</h3>' +
                '<p class="card-club">' + esc(t.club) + '</p>' +
                '<span class="card-season">' + esc(t.season) + '</span>' +
                '</div><span class="formation-badge">' + esc(t.formation) + '</span></div>' +
                '<div class="version-pills">' + pills + '</div>' +
                '<div class="trend-stats">' +
                '<span class="trend-stat" title="Local view count">👁 ' + entry.views + ' view' + (entry.views === 1 ? '' : 's') + '</span>' +
                (entry.favorite ? '<span class="trend-stat trend-fav">♥ Favorited</span>' : '') +
                '</div>' +
                '<div class="card-actions"><a class="btn btn-primary btn-sm" href="' + href + '">View Tactic</a></div>' +
                '</article>';
        }).join('');
    }

    ready(render);
})();
