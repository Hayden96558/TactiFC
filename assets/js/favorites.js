/* =========================================================================
   TactiFC — favorites.js
   localStorage-backed favorites, recently viewed tactics, and custom tactics.
   ========================================================================= */
(function () {
    'use strict';

    const KEYS = {
        favorites: 'tactifc:favorites',
        recent: 'tactifc:recent',
        custom: 'tactifc:custom',
        views: 'tactifc:views'
    };
    const MAX_RECENT = 10;

    const base = (window.TACTIFC && window.TACTIFC.base) || '';

    function read(key) {
        try {
            const raw = localStorage.getItem(key);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) { return []; }
    }
    function write(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* ignore quota */ }
    }

    /* ------------------------------------------------------ Favorites API */
    function getFavorites() { return read(KEYS.favorites); }
    function isFavorite(id) { return getFavorites().indexOf(id) !== -1; }
    function toggleFavorite(id) {
        let favs = getFavorites();
        const idx = favs.indexOf(id);
        if (idx === -1) { favs.unshift(id); } else { favs.splice(idx, 1); }
        write(KEYS.favorites, favs);
        return idx === -1;
    }
    function addFavorite(id) {
        const favs = getFavorites();
        if (favs.indexOf(id) === -1) { favs.unshift(id); write(KEYS.favorites, favs); return true; }
        return false;
    }
    function removeFavorite(id) {
        const favs = getFavorites().filter((f) => f !== id);
        write(KEYS.favorites, favs);
    }

    /* --------------------------------------------------------- Recently */
    function getRecent() { return read(KEYS.recent); }
    function pushRecent(id) {
        if (!id) return;
        let recent = getRecent().filter((r) => r !== id);
        recent.unshift(id);
        recent = recent.slice(0, MAX_RECENT);
        write(KEYS.recent, recent);
    }

    /* ----------------------------------------------------------- Custom */
    function getCustom() { return read(KEYS.custom); }
    function saveCustom(tactic) {
        const customs = getCustom();
        tactic.id = tactic.id || ('custom-' + Date.now());
        tactic.savedAt = new Date().toISOString();
        const idx = customs.findIndex((c) => c.id === tactic.id);
        if (idx === -1) { customs.unshift(tactic); } else { customs[idx] = tactic; }
        write(KEYS.custom, customs);
        return tactic.id;
    }
    function deleteCustom(id) {
        write(KEYS.custom, getCustom().filter((c) => c.id !== id));
    }

    /* ------------------------------------------------------------ Views */
    /* Local view counts, used for the (clearly labelled) demo trending. */
    function getViews() {
        try {
            const raw = localStorage.getItem(KEYS.views);
            const obj = raw ? JSON.parse(raw) : {};
            return (obj && typeof obj === 'object') ? obj : {};
        } catch (e) { return {}; }
    }
    function recordView(id) {
        if (!id) return;
        const views = getViews();
        views[id] = (views[id] || 0) + 1;
        try { localStorage.setItem(KEYS.views, JSON.stringify(views)); } catch (e) { /* ignore */ }
    }
    function getViewCount(id) { const v = getViews(); return v[id] || 0; }

    /* Trending: rank tactics by local views + favorites. Local-only. */
    function getTrending(limit) {
        const index = window.TACTIFC_INDEX || {};
        const views = getViews();
        const favs = getFavorites();
        const recent = getRecent();
        const scores = {};
        Object.keys(views).forEach((id) => { scores[id] = (scores[id] || 0) + views[id] * 3; });
        favs.forEach((id) => { scores[id] = (scores[id] || 0) + 5; });
        recent.forEach((id, i) => { scores[id] = (scores[id] || 0) + Math.max(1, 5 - i); });
        const ranked = Object.keys(scores)
            .filter((id) => index[id])
            .map((id) => ({ id: id, score: scores[id], views: views[id] || 0, favorite: favs.indexOf(id) !== -1 }))
            .sort((a, b) => b.score - a.score);
        return limit ? ranked.slice(0, limit) : ranked;
    }

    function clearRecent() {
        write(KEYS.recent, []);
    }

    /* ------------------------------------------------------ Card renderer */
    function escapeHtml(s) {
        return (s || '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function tacticCard(t, opts) {
        opts = opts || {};
        const versions = Object.keys(t.versions || {});
        const versionPills = ['fc25', 'fc26', 'fc27']
            .map((v) => '<span class="version-pill' + (versions.indexOf(v) !== -1 ? ' is-on' : '') + '">' + v.toUpperCase() + '</span>')
            .join('');
        const styles = (t.style || []).slice(0, 3).map((s) => '<span class="tag">' + escapeHtml(s) + '</span>').join('');
        const href = base + '/tactic.php?id=' + encodeURIComponent(t.id);

        let actions = '<a class="btn btn-primary btn-sm" href="' + href + '">View Tactic</a>';
        if (opts.recent) {
            actions += '<button type="button" class="fav-btn btn-sm" data-fav-remove="' + escapeHtml(t.id) + '">♥ Remove</button>';
        } else {
            actions += '<button type="button" class="fav-btn is-active btn-sm" data-fav-toggle="' + escapeHtml(t.id) + '" aria-pressed="true">♥ Favorite</button>';
        }

        return '<article class="card tactic-card" data-tactic-id="' + escapeHtml(t.id) + '">' +
            '<div class="card-top"><div>' +
            '<h3 class="card-title">' + escapeHtml(t.manager || '') + '</h3>' +
            '<p class="card-club">' + escapeHtml(t.club || '') + '</p>' +
            '<span class="card-season">' + escapeHtml(t.season || '') + '</span>' +
            '</div><span class="formation-badge">' + escapeHtml(t.formation || '') + '</span></div>' +
            '<div class="version-pills">' + versionPills + '</div>' +
            '<div class="tag-row">' + styles + '</div>' +
            '<p class="card-desc">' + escapeHtml(t.description || '') + '</p>' +
            '<div class="card-actions">' + actions + '</div>' +
            '</article>';
    }

    function customCard(t) {
        const href = base + '/tactic.php?id=' + encodeURIComponent(t.id);
        const players = t.players || [];
        const roles = players.slice(0, 3).map((p) =>
            '<span class="role-badge"><span class="rb-pos">' + escapeHtml(p.position || '') + '</span><span class="rb-sep">·</span><span>' + escapeHtml(p.role || '') + '</span></span>'
        ).join('');
        return '<article class="card tactic-card">' +
            '<div class="card-top"><div>' +
            '<h3 class="card-title">' + escapeHtml(t.name || 'Custom tactic') + '</h3>' +
            '<p class="card-club">' + escapeHtml((t.version || 'fc25').toUpperCase()) + (t.style ? ' · ' + escapeHtml(t.style) : '') + '</p>' +
            '<span class="card-season">' + escapeHtml((t.formation || '')) + '</span>' +
            '</div><span class="formation-badge">' + escapeHtml(t.formation || '') + '</span></div>' +
            '<div class="key-roles"><div class="key-roles-label">Roles</div><div class="pill-row">' + roles + '</div></div>' +
            '<p class="card-desc">' + players.length + ' players assigned.</p>' +
            '<div class="card-actions">' +
            '<a class="btn btn-primary btn-sm" href="' + href + '">Load in Builder</a>' +
            '<button type="button" class="btn btn-danger btn-sm" data-custom-delete="' + escapeHtml(t.id) + '">Delete</button>' +
            '</div></article>';
    }

    /* --------------------------------------------------- Favorites page */
    function renderFavoritesPage() {
        const index = window.TACTIFC_INDEX || {};
        const favGrid = document.getElementById('favGrid');
        const recentGrid = document.getElementById('recentGrid');
        const customGrid = document.getElementById('customGrid');
        if (!favGrid && !recentGrid && !customGrid) return;

        /* Favorites */
        if (favGrid) {
            const favs = getFavorites().map((id) => index[id]).filter(Boolean);
            favGrid.innerHTML = favs.map((t) => tacticCard(t, {})).join('');
            setEmpty('favEmpty', favs.length === 0);
            setCount('favCount', favs.length);
        }

        /* Recent */
        if (recentGrid) {
            const recent = getRecent().map((id) => index[id]).filter(Boolean);
            recentGrid.innerHTML = recent.map((t) => tacticCard(t, { recent: true })).join('');
            setEmpty('recentEmpty', recent.length === 0);
            setCount('recentCount', recent.length);
        }

        /* Custom */
        if (customGrid) {
            const customs = getCustom();
            customGrid.innerHTML = customs.map(customCard).join('');
            setEmpty('customEmpty', customs.length === 0);
            setCount('customCount', customs.length);
        }
    }

    function setEmpty(id, isEmpty) {
        const el = document.getElementById(id);
        if (el) el.hidden = !isEmpty;
    }
    function setCount(id, n) {
        const el = document.getElementById(id);
        if (el) el.textContent = '(' + n + ')';
    }

    /* --------------------------------------------------- Global bindings */
    function bindToggles(root) {
        (root || document).querySelectorAll('[data-fav-toggle]').forEach((btn) => {
            if (btn._favBound) return;
            btn._favBound = true;
            const id = btn.getAttribute('data-fav-toggle');
            setButtonState(btn, isFavorite(id));
            btn.addEventListener('click', () => {
                const nowFav = toggleFavorite(id);
                setButtonState(btn, nowFav);
                if (window.showToast) window.showToast(nowFav ? 'Added to favorites' : 'Removed from favorites');
            });
        });

        (root || document).querySelectorAll('[data-fav-remove]').forEach((btn) => {
            btn.addEventListener('click', () => {
                removeFavorite(btn.getAttribute('data-fav-remove'));
                renderFavoritesPage();
                if (window.showToast) window.showToast('Removed from favorites');
            });
        });

        (root || document).querySelectorAll('[data-clear-recent]').forEach((btn) => {
            if (btn._clearBound) return;
            btn._clearBound = true;
            btn.addEventListener('click', () => {
                if (!confirm('Clear your recently viewed history?')) return;
                clearRecent();
                renderFavoritesPage();
                if (window.showToast) window.showToast('History cleared');
            });
        });

        (root || document).querySelectorAll('[data-custom-delete]').forEach((btn) => {
            btn.addEventListener('click', () => {
                deleteCustom(btn.getAttribute('data-custom-delete'));
                renderFavoritesPage();
                if (window.showToast) window.showToast('Custom tactic deleted');
            });
        });
    }

    function setButtonState(btn, active) {
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.innerHTML = (active ? '♥' : '♡') + ' Favorite';
    }

    /* Record a viewed tactic on the tactic detail page. */
    function initRecentTracking() {
        const page = document.body.getAttribute('data-page');
        if (page !== 'tactic.php') return;
        // The tactic id is available in window.TACTIFC_TACTIC.
        if (window.TACTIFC_TACTIC && window.TACTIFC_TACTIC.id) {
            pushRecent(window.TACTIFC_TACTIC.id);
            recordView(window.TACTIFC_TACTIC.id);
        }
    }

    /* Public API (used by builder.js) */
    window.TactiFCFavorites = {
        getFavorites, isFavorite, toggleFavorite, addFavorite, removeFavorite,
        getRecent, pushRecent, clearRecent,
        getCustom, saveCustom, deleteCustom,
        getViews, recordView, getViewCount, getTrending,
        bindToggles, renderFavoritesPage
    };

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function () {
        initRecentTracking();
        bindToggles(document);
        renderFavoritesPage();
    });
})();
