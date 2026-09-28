/* =========================================================================
   TactiFC — search.js
   Live search suggestions for the header and hero search inputs.
   Data is fetched from a small JSON endpoint rendered by PHP.
   ========================================================================= */
(function () {
    'use strict';

    const base = (window.TACTIFC && window.TACTIFC.base) || '';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    /* Build a search index from data embedded on the page where available,
       otherwise fall back to the server endpoint. */
    let INDEX = null;

    function getIndex() {
        if (INDEX) return Promise.resolve(INDEX);
        if (window.TACTIFC_SEARCH_INDEX) {
            INDEX = window.TACTIFC_SEARCH_INDEX;
            return Promise.resolve(INDEX);
        }
        return fetch(base + '/api/search-index.php', { headers: { 'Accept': 'application/json' } })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => { INDEX = Array.isArray(data) ? data : []; return INDEX; })
            .catch(() => { INDEX = []; return INDEX; });
    }

    function normalize(s) {
        return (s || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function score(item, tokens) {
        const hay = item.haystack || '';
        let total = 0;
        for (const token of tokens) {
            if (!token) continue;
            const idx = hay.indexOf(token);
            if (idx === -1) {
                // numeric formation shorthand
                if (/^\d{3,5}$/.test(token)) {
                    const dashed = token.split('').join('-');
                    if (hay.indexOf(dashed) !== -1) { total += 2; continue; }
                }
                return -1;
            }
            total += (idx === 0 ? 3 : 1);
        }
        return total;
    }

    function attach(input) {
        const suggest = input.parentElement.querySelector('.search-suggest');
        if (!suggest) return;

        let timer = null;
        let currentItems = [];

        function hide() {
            suggest.hidden = true;
            suggest.innerHTML = '';
        }

        function render(items) {
            currentItems = items;
            if (!items.length) {
                suggest.innerHTML = '<div class="suggest-empty">No matches</div>';
                suggest.hidden = false;
                return;
            }
            // Group by type for a clearer dropdown.
            const groups = {};
            const order = ['manager', 'club', 'tactic', 'formation', 'role', 'tag'];
            const labels = { manager: 'Managers', club: 'Clubs', tactic: 'Tactics', formation: 'Formations', role: 'Roles', tag: 'Tags' };
            items.forEach((it) => {
                const t = it.type || 'tactic';
                (groups[t] = groups[t] || []).push(it);
            });
            let html = '';
            order.forEach((type) => {
                if (!groups[type]) return;
                html += '<div class="suggest-group"><div class="suggest-group-label">' + (labels[type] || type) + '</div>';
                groups[type].forEach((it) => {
                    const href = base + '/' + it.url;
                    html += '<a href="' + href + '">' +
                        '<strong>' + escapeHtml(it.title) + '</strong>' +
                        '<span class="suggest-meta">' + escapeHtml(it.meta || '') + '</span>' +
                        '</a>';
                });
                html += '</div>';
            });
            suggest.innerHTML = html;
            suggest.hidden = false;
        }

        function search(value) {
            const q = normalize(value);
            if (q.length < 2) { hide(); return; }
            const tokens = q.split(/\s+/).filter(Boolean);
            getIndex().then((index) => {
                const scored = index
                    .map((item) => ({ item, s: score(item, tokens) }))
                    .filter((x) => x.s >= 0)
                    .sort((a, b) => b.s - a.s)
                    .slice(0, 8)
                    .map((x) => x.item);
                render(scored);
            });
        }

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => search(input.value), 120);
        });
        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 2) search(input.value);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' && !suggest.hidden) {
                e.preventDefault();
                const first = suggest.querySelector('a');
                if (first) first.focus();
            } else if (e.key === 'Escape') {
                hide();
            }
        });
        document.addEventListener('click', (e) => {
            if (!suggest.contains(e.target) && e.target !== input) hide();
        });
    }

    function escapeHtml(s) {
        return (s || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    ready(function () {
        const inputs = document.querySelectorAll('[data-live-search]');
        if (!inputs.length) return;
        // Only load the index when the user actually engages a search box.
        inputs.forEach((input) => {
            attach(input);
            input.addEventListener('focus', getIndex, { once: true });
        });
    });
})();
