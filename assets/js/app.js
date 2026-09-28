/* =========================================================================
   TactiFC — app.js
   Global UI: mobile nav, tabs, filters, toasts, shape toggles, helpers.
   ========================================================================= */
(function () {
    'use strict';

    const TACTIFC = window.TACTIFC || { base: '' };

    /* ----------------------------------------------------------- Helpers */
    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function showToast(message) {
        let toast = qs('.toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.classList.remove('is-visible'), 2400);
    }
    window.showToast = showToast;

    /* ------------------------------------------------------ Mobile nav */
    function initNav() {
        const toggle = qs('#navToggle');
        const nav = qs('#primaryNav');
        if (!toggle || !nav) return;

        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // Close on outside click / escape.
        document.addEventListener('click', (e) => {
            if (!nav.classList.contains('is-open')) return;
            if (nav.contains(e.target) || toggle.contains(e.target)) return;
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ------------------------------------------------------------ Tabs */
    function initTabs() {
        qsa('.tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                const group = tab.closest('.tabs');
                const target = tab.getAttribute('data-fav-tab') || tab.getAttribute('data-tab');
                if (!group || !target) return;
                qsa('.tab', group).forEach((t) => {
                    const active = t === tab;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                const scope = group.parentElement || document;
                const panels = qsa('[data-fav-panel], [data-tab-panel]', scope);
                panels.forEach((p) => {
                    const key = p.getAttribute('data-fav-panel') || p.getAttribute('data-tab-panel');
                    p.classList.toggle('is-active', key === target);
                });
            });
        });
    }

    /* --------------------------------------------- Collapsible filters */
    function initFilterCollapse() {
        qsa('[data-toggle-filters]').forEach((btn) => {
            const grid = qs('#filterGrid') || qs('.filter-grid');
            btn.addEventListener('click', () => {
                if (!grid) return;
                const collapsed = grid.classList.toggle('filter-collapse');
                btn.textContent = collapsed ? 'Show filters' : 'Hide filters';
            });
        });
    }

    /* ----------------------------------------------- Simple card filter */
    function initCardFilters() {
        const inputs = qsa('[data-filter-target]');
        if (!inputs.length) return;

        function apply() {
            const targetSel = inputs[0].getAttribute('data-filter-target');
            const cards = qsa(targetSel);
            const queryInput = inputs.find((i) => i.type === 'search');
            const query = queryInput ? queryInput.value.toLowerCase().trim() : '';
            const selects = inputs.filter((i) => i.tagName === 'SELECT');
            const activeSelects = selects
                .map((s) => ({ attr: s.getAttribute('data-filter-attr'), value: s.value.toLowerCase() }))
                .filter((s) => s.attr && s.value);

            let visible = 0;
            cards.forEach((card) => {
                let ok = true;
                const haystack = (card.getAttribute('data-search') || card.textContent || '').toLowerCase();
                if (query && haystack.indexOf(query) === -1) ok = false;
                if (ok) {
                    activeSelects.forEach((s) => {
                        const val = (card.getAttribute(s.attr) || '').toLowerCase();
                        if (val.indexOf(s.value) === -1) ok = false;
                    });
                }
                card.hidden = !ok;
                if (ok) visible++;
            });

            const empty = qs('#managerNoResults') || qs('#roleNoResults') || qs('#noResults');
            if (empty) empty.hidden = visible !== 0;
        }

        inputs.forEach((i) => {
            i.addEventListener('input', apply);
            i.addEventListener('change', apply);
        });
    }

    /* ---------------------------------- Remember player base coordinates */
    function initPlayerCoords() {
        qsa('.player').forEach((player) => {
            if (player.hasAttribute('data-x')) return;
            const left = parseFloat(player.style.left) || 50;
            const top = parseFloat(player.style.top) || 50;
            player.setAttribute('data-x', left);
            player.setAttribute('data-y', top);
        });
    }

    /* --------------------------------------------------- Role tooltip JS */
    function initRoleTooltips() {
        // Pure CSS handles hover; nothing required here. Keyboard focus works
        // because markers have tabindex + :focus-within styling.
    }

    /* ---------------------------------------------------- Version tabs */
    function initVersionTabs() {
        const tabs = qsa('.version-tab[data-version]');
        if (!tabs.length) return;

        function activate(version) {
            tabs.forEach((t) => {
                const active = t.getAttribute('data-version') === version;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            qsa('.version-panel').forEach((p) => {
                p.classList.toggle('is-active', p.getAttribute('data-version') === version);
            });
            qsa('.pitch-version').forEach((p) => {
                p.hidden = p.getAttribute('data-version') !== version;
            });
            // Keep the side + main version tabs in sync.
            document.dispatchEvent(new CustomEvent('tactifc:version', { detail: { version } }));
        }

        tabs.forEach((t) => t.addEventListener('click', () => activate(t.getAttribute('data-version'))));
        // Sync all tab groups when one changes (main <-> side).
        document.addEventListener('tactifc:version', (e) => {
            tabs.forEach((t) => {
                const active = t.getAttribute('data-version') === e.detail.version;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        });
    }

    /* ------------------------------------------------------------ Public */
    window.TactiFC = {
        base: TACTIFC.base,
        qs: qs, qsa: qsa,
        showToast: showToast,
        ready: ready
    };

    ready(function () {
        initNav();
        initTabs();
        initFilterCollapse();
        initCardFilters();
        initPlayerCoords();
        initVersionTabs();
        initRoleTooltips();
    });
})();
