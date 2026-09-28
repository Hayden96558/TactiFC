/* =========================================================================
   TactiFC — roles.js
   Tactic detail page: version switching, player role table, role detail modal.
   Role data is version-specific and comes from the embedded PHP payload.
   ========================================================================= */
(function () {
    'use strict';

    const T = window.TACTIFC_TACTIC || null;
    if (!T) return;

    const ALIASES = {
        RWB: 'RB', LWB: 'LB', LCB: 'CB', RCB: 'CB',
        RDM: 'CDM', LDM: 'CDM', LST: 'ST', RST: 'ST',
        LAM: 'CAM', RAM: 'CAM', LCM: 'CM', RCM: 'CM'
    };

    function basePos(pos) { return ALIASES[pos] || pos; }
    function qs(s, r) { return (r || document).querySelector(s); }
    function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function esc(s) { return (s || '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function stars(n) { return '★'.repeat(n) + '☆'.repeat(3 - n); }
    function famStars(level) { return level === 'Role++' ? 3 : level === 'Role+' ? 2 : level === 'Role' ? 1 : 0; }

    /* Access the version-specific role database. */
    function roleDbFor(version) {
        return (T.roleDb && T.roleDb[version]) ? T.roleDb[version] : {};
    }
    function roleEntry(version, pos, role) {
        const db = roleDbFor(version);
        const list = db[basePos(pos)] || [];
        return list.find((e) => e.role.toLowerCase() === (role || '').toLowerCase()) || null;
    }

    const current = { version: Object.keys(T.versions || {})[0] || 'fc25' };

    /* ---------------------------------------------------- Roles table */
    function renderRolesTable(version) {
        const tbody = qs('#rolesTable tbody');
        if (!tbody) return;
        const players = (T.versions && T.versions[version]) || [];
        const settings = (T.settings && T.settings[version]) || {};
        const instr = settings.playerInstructions || '';

        tbody.innerHTML = players.map((p, i) => {
            const entry = roleEntry(version, p.position, p.role);
            const fam = famStars(p.familiarity);
            return '<tr data-role-index="' + i + '">' +
                '<td><span class="badge badge-pos">' + esc(p.position) + '</span></td>' +
                '<td><button type="button" class="role-badge" data-open-role="' + i + '">' +
                    '<span>' + esc(p.role) + '</span></button></td>' +
                '<td><span class="badge badge-focus">' + esc(p.focus || '—') + '</span></td>' +
                '<td>' + (p.familiarity
                    ? '<span class="familiarity">' + esc(p.familiarity) + ' <span class="fam-stars">' + stars(fam) + '</span></span>'
                    : '<span class="dim">—</span>') + '</td>' +
                '<td class="dim">' + esc(instr || (entry ? 'See role details' : '—')) + '</td>' +
                '</tr>';
        }).join('');

        // Bind role buttons.
        qsa('[data-open-role]', tbody).forEach((btn) => {
            btn.addEventListener('click', () => openRoleModal(version, parseInt(btn.getAttribute('data-open-role'), 10)));
        });
    }

    /* ------------------------------------------------------ Role modal */
    function openRoleModal(version, index) {
        const players = (T.versions && T.versions[version]) || [];
        const p = players[index];
        if (!p) return;
        const entry = roleEntry(version, p.position, p.role);
        const modal = qs('#roleModal');
        const body = qs('#roleModalBody');
        if (!modal || !body) return;

        qs('#roleModalPos').textContent = p.position + ' · ' + version.toUpperCase();
        qs('#roleModalTitle').textContent = p.role;

        let html = '';
        html += '<div class="role-item-head">' +
            '<span class="badge badge-pos">' + esc(p.position) + '</span>' +
            '<span class="badge badge-role">' + esc(p.role) + '</span>' +
            (p.focus ? '<span class="badge badge-focus">' + esc(p.focus) + '</span>' : '') +
            (p.familiarity ? '<span class="badge badge-fam">' + esc(p.familiarity) + ' ' + stars(famStars(p.familiarity)) + '</span>' : '') +
            '</div>';

        if (entry) {
            html += '<p class="muted">' + esc(entry.description || '') + '</p>';
            html += '<div class="role-behaviours">';
            html += '<div class="behaviour"><h4>Attacking Behaviour</h4><ul>' +
                (entry.attacking || []).map((b) => '<li>' + esc(b) + '</li>').join('') + '</ul></div>';
            html += '<div class="behaviour"><h4>Defensive Behaviour</h4><ul>' +
                (entry.defending || []).map((b) => '<li>' + esc(b) + '</li>').join('') + '</ul></div>';
            html += '</div>';
            if (entry.focuses && entry.focuses.length) {
                html += '<p class="hint mt-2"><strong>Available focuses:</strong> ' + esc(entry.focuses.join(', ')) + '</p>';
            }
        } else {
            html += '<p class="muted">No additional description is stored for this role in ' + version.toUpperCase() + '.</p>';
        }

        html += '<p class="hint mt-2">This role is available in ' + version.toUpperCase() + ' for position ' + esc(basePos(p.position)) + '.</p>';
        body.innerHTML = html;
        modal.hidden = false;
        modal.classList.add('is-open');
    }

    function closeRoleModal() {
        const modal = qs('#roleModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.hidden = true;
    }

    /* ---------------------------------------------------- Version sync */
    function setVersion(version) {
        current.version = version;
        renderRolesTable(version);
        // Version tabs handled by app.js; keep pitch panels in sync here too.
        qsa('.pitch-version').forEach((el) => { el.hidden = el.getAttribute('data-version') !== version; });
        qsa('.version-panel').forEach((el) => {
            el.classList.toggle('is-active', el.getAttribute('data-version') === version);
        });
        qsa('.version-tab').forEach((el) => {
            const active = el.getAttribute('data-version') === version;
            el.classList.toggle('is-active', active);
            el.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function () {
        // Initial render.
        setVersion(current.version);

        // React to version tab clicks anywhere on the page.
        qsa('.version-tab[data-version]').forEach((tab) => {
            tab.addEventListener('click', () => setVersion(tab.getAttribute('data-version')));
        });

        // Also listen for the global event dispatched by app.js.
        document.addEventListener('tactifc:version', (e) => setVersion(e.detail.version));

        // Modal close handlers.
        const close = qs('#roleModalClose');
        const modal = qs('#roleModal');
        if (close) close.addEventListener('click', closeRoleModal);
        if (modal) {
            modal.addEventListener('click', (e) => { if (e.target === modal) closeRoleModal(); });
        }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeRoleModal(); });

        // Clicking a pitch player opens the modal too.
        qsa('.player[data-player-pos]').forEach((player) => {
            player.addEventListener('click', () => {
                const version = current.version;
                const pos = player.getAttribute('data-player-pos');
                const role = player.getAttribute('data-player-role');
                const players = (T.versions && T.versions[version]) || [];
                const idx = players.findIndex((p) => p.position === pos && p.role === role);
                if (idx !== -1) {
                    openRoleModal(version, idx);
                } else {
                    // Fall back to a synthetic detail view.
                    const entry = roleEntry(version, pos, role);
                    const body = qs('#roleModalBody');
                    qs('#roleModalPos').textContent = pos + ' · ' + version.toUpperCase();
                    qs('#roleModalTitle').textContent = role || pos;
                    body.innerHTML = entry
                        ? '<p class="muted">' + esc(entry.description) + '</p>'
                        : '<p class="muted">No details available.</p>';
                    qs('#roleModal').hidden = false;
                    qs('#roleModal').classList.add('is-open');
                }
            });
        });
    });
})();
