/* =========================================================================
   TactiFC — builder.js
   Interactive Tactic Builder: formation selection, draggable players,
   version-specific role/focus selection, save to localStorage.
   ========================================================================= */
(function () {
    'use strict';

    const app = document.getElementById('builderApp');
    if (!app) return;

    const ROLE_DB = safeParse(app.getAttribute('data-role-db'), {});
    const FORMATIONS = safeParse(app.getAttribute('data-formations'), []);
    const PREFILL = safeParse(app.getAttribute('data-prefill'), {}) || {};
    const FAV = window.TactiFCFavorites || null;
    const BASE = (window.TACTIFC && window.TACTIFC.base) || '';

    const state = {
        formation: FORMATIONS[0] ? FORMATIONS[0].id : '4-3-3',
        version: (window.TACTIFC && window.TACTIFC.versions && window.TACTIFC.versions[0]) || 'fc25',
        style: '',
        name: '',
        players: [], // { position, role, focus, familiarity, instructions, x, y }
        selected: -1,
        editingId: null,
        shape: 'base'
    };

    const ALIASES = {
        RWB: 'RB', LWB: 'LB', LCB: 'CB', RCB: 'CB',
        RDM: 'CDM', LDM: 'CDM', LST: 'ST', RST: 'ST',
        LAM: 'CAM', RAM: 'CAM', LCM: 'CM', RCM: 'CM'
    };

    /* Movement vectors used to preview with/without-ball shapes. */
    const MOVEMENT = {
        GK:   { with: { dx: 0, dy: 3 },  without: { dx: 0, dy: 2 } },
        CB:   { with: { dx: 0, dy: -2 }, without: { dx: 0, dy: 3 } },
        LB:   { with: { dx: -4, dy: -9 }, without: { dx: 4, dy: 6 } },
        RB:   { with: { dx: 4, dy: -9 },  without: { dx: -4, dy: 6 } },
        LWB:  { with: { dx: -4, dy: -10 }, without: { dx: 6, dy: 7 } },
        RWB:  { with: { dx: 4, dy: -10 },  without: { dx: -6, dy: 7 } },
        CDM:  { with: { dx: 0, dy: 3 },   without: { dx: 0, dy: 4 } },
        CM:   { with: { dx: 0, dy: -6 },  without: { dx: 0, dy: 5 } },
        CAM:  { with: { dx: 0, dy: -6 },  without: { dx: 0, dy: 6 } },
        LM:   { with: { dx: -3, dy: -7 }, without: { dx: 5, dy: 6 } },
        RM:   { with: { dx: 3, dy: -7 },  without: { dx: -5, dy: 6 } },
        LW:   { with: { dx: -3, dy: -9 }, without: { dx: 5, dy: 8 } },
        RW:   { with: { dx: 3, dy: -9 },  without: { dx: -5, dy: 8 } },
        ST:   { with: { dx: 0, dy: -7 },  without: { dx: 0, dy: 6 } }
    };

    function safeParse(str, fallback) {
        try { const v = JSON.parse(str); return v == null ? fallback : v; } catch (e) { return fallback; }
    }
    function qs(s, r) { return (r || document).querySelector(s); }
    function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function esc(s) { return (s || '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    function basePos(pos) { return ALIASES[pos] || pos; }

    function roleDbFor(version) { return ROLE_DB[version] || {}; }
    function positionsForVersion(version) { return Object.keys(roleDbFor(version)); }

    /* Roles valid for a slot's base position in the version. */
    function rolesFor(version, position) {
        const db = roleDbFor(version);
        return (db[basePos(position)] || []).map((e) => e.role);
    }
    function focusesFor(version, position, role) {
        const db = roleDbFor(version);
        const list = db[basePos(position)] || [];
        const entry = list.find((e) => e.role.toLowerCase() === (role || '').toLowerCase());
        return entry ? (entry.focuses || []) : [];
    }
    function roleEntry(version, position, role) {
        const db = roleDbFor(version);
        const list = db[basePos(position)] || [];
        return list.find((e) => e.role.toLowerCase() === (role || '').toLowerCase()) || null;
    }

    function formationById(id) {
        return FORMATIONS.find((f) => f.id === id) || null;
    }

    /* ------------------------------------------------------ Initialise */
    function buildPlayersFromFormation() {
        const formation = formationById(state.formation);
        if (!formation) { state.players = []; return; }
        state.players = (formation.slots || []).map((slot) => {
            const roles = rolesFor(state.version, slot.pos);
            const role = roles[0] || '';
            const focuses = focusesFor(state.version, slot.pos, role);
            return {
                position: slot.pos,
                role: role,
                focus: focuses[0] || '',
                familiarity: 'Role',
                instructions: '',
                x: slot.x,
                y: slot.y
            };
        });
        state.selected = -1;
    }

    /* Compute the display coordinates for a player in the current shape. */
    function displayCoord(p) {
        if (state.shape === 'base') return { x: p.x, y: p.y };
        const pos = basePos((p.position || '').toUpperCase());
        const vec = MOVEMENT[pos];
        if (!vec) return { x: p.x, y: p.y };
        const v = state.shape === 'without' ? vec.without : vec.with;
        let x = p.x + v.dx;
        let y = p.y + v.dy;
        if (state.shape === 'with') {
            const role = (p.role || '').toLowerCase();
            if (/^(LB|RB)$/.test(pos) && role.indexOf('inverted') !== -1) {
                x = p.x < 50 ? Math.min(44, p.x + 14) : Math.max(56, p.x - 14);
            } else if (pos === 'CB') {
                x = 50 + (p.x - 50) * 1.12;
            }
        } else if (state.shape === 'without') {
            x = 50 + (p.x - 50) * 0.78;
        }
        return { x: Math.max(3, Math.min(97, x)), y: Math.max(3, Math.min(97, y)) };
    }

    /* ---------------------------------------------------- Pitch render */
    function renderPitch() {
        const host = qs('#builderPitchHost');
        if (!host) return;

        let html = '<div class="pitch-wrap"><div class="pitch" id="builderPitch">';
        html += pitchMarkings();
        state.players.forEach((p, i) => {
            const tone = toneFor(p.position);
            const c = displayCoord(p);
            html += '<div class="player' + (i === state.selected ? ' is-selected' : '') + '"' +
                ' data-index="' + i + '"' +
                ' style="left:' + c.x + '%;top:' + c.y + '%;"' +
                ' tabindex="0" role="button" aria-label="' + esc(p.position + ' ' + p.role) + '">' +
                '<span class="player-dot ' + tone + '">' + esc(p.position) + '</span>' +
                (p.role ? '<span class="player-role-label" title="' + esc(p.role + (p.focus ? ' · ' + p.focus : '')) + '">' + esc(p.role) + '</span>' : '') +
                '</div>';
        });
        html += '</div></div>';
        host.innerHTML = html;

        bindPitchEvents();
    }

    function pitchMarkings() {
        return '<div class="pitch-markings" aria-hidden="true"><svg viewBox="0 0 68 105" preserveAspectRatio="none">' +
            '<rect x="1" y="1" width="66" height="103" fill="none" stroke="currentColor" stroke-width="0.5"/>' +
            '<line x1="1" y1="52.5" x2="67" y2="52.5" stroke="currentColor" stroke-width="0.5"/>' +
            '<circle cx="34" cy="52.5" r="9.15" fill="none" stroke="currentColor" stroke-width="0.5"/>' +
            '<circle cx="34" cy="52.5" r="0.9" fill="currentColor"/>' +
            '<rect x="13.84" y="1" width="40.32" height="16.5" fill="none" stroke="currentColor" stroke-width="0.5"/>' +
            '<rect x="24.84" y="1" width="18.32" height="5.5" fill="none" stroke="currentColor" stroke-width="0.5"/>' +
            '<rect x="13.84" y="87.5" width="40.32" height="16.5" fill="none" stroke="currentColor" stroke-width="0.5"/>' +
            '<rect x="24.84" y="98.5" width="18.32" height="5.5" fill="none" stroke="currentColor" stroke-width="0.5"/>' +
            '</svg></div>';
    }

    function toneFor(pos) {
        pos = (pos || '').toUpperCase();
        if (pos === 'GK') return 'is-gk';
        if (/^(LB|RB|CB|LCB|RCB|LWB|RWB)$/.test(pos)) return 'is-def';
        if (/^(CDM|CM|CAM|LM|RM|LCM|RCM|LDM|RDM|LAM|RAM)$/.test(pos)) return 'is-mid';
        return 'is-att';
    }

    /* ----------------------------------------------------- Drag events */
    function bindPitchEvents() {
        const pitch = qs('#builderPitch');
        if (!pitch) return;

        qsa('.player', pitch).forEach((el) => {
            const index = parseInt(el.getAttribute('data-index'), 10);

            el.addEventListener('click', (e) => {
                if (el._dragged) { el._dragged = false; return; }
                selectPlayer(index);
            });

            /* Pointer drag (base shape only). */
            let dragging = false, moved = false;
            el.addEventListener('pointerdown', (e) => {
                if (state.shape !== 'base') return;
                dragging = true; moved = false;
                el.setPointerCapture(e.pointerId);
                e.preventDefault();
            });
            el.addEventListener('pointermove', (e) => {
                if (!dragging) return;
                moved = true;
                const rect = pitch.getBoundingClientRect();
                let x = ((e.clientX - rect.left) / rect.width) * 100;
                let y = ((e.clientY - rect.top) / rect.height) * 100;
                x = Math.max(4, Math.min(96, x));
                y = Math.max(4, Math.min(96, y));
                state.players[index].x = Math.round(x * 10) / 10;
                state.players[index].y = Math.round(y * 10) / 10;
                el.style.left = state.players[index].x + '%';
                el.style.top = state.players[index].y + '%';
            });
            const end = (e) => {
                if (dragging && moved) { el._dragged = true; }
                dragging = false;
            };
            el.addEventListener('pointerup', end);
            el.addEventListener('pointercancel', end);
        });
    }

    /* ------------------------------------------------- Player selection */
    function selectPlayer(index) {
        state.selected = index;
        renderPitch();
        renderSidebar();
    }

    function renderSidebar() {
        const p = state.players[state.selected];
        const noSel = qs('#noSelection');
        const form = qs('#selectionForm');

        if (!p) {
            if (noSel) noSel.hidden = false;
            if (form) form.hidden = true;
        } else {
            if (noSel) noSel.hidden = true;
            if (form) form.hidden = false;

            /* Position select */
            const posSel = qs('#p-position');
            posSel.innerHTML = positionsForVersion(state.version)
                .map((pos) => '<option value="' + pos + '"' + (pos === basePos(p.position) ? ' selected' : '') + '>' + pos + '</option>')
                .join('');

            /* Role select (filtered by position) */
            const roleSel = qs('#p-role');
            const roles = rolesFor(state.version, p.position);
            roleSel.innerHTML = roles
                .map((r) => '<option value="' + esc(r) + '"' + (r === p.role ? ' selected' : '') + '>' + esc(r) + '</option>')
                .join('');

            /* Focus select (filtered by role) */
            const focusSel = qs('#p-focus');
            const focuses = focusesFor(state.version, p.position, p.role);
            focusSel.innerHTML = focuses
                .map((f) => '<option value="' + esc(f) + '"' + (f === p.focus ? ' selected' : '') + '>' + esc(f) + '</option>')
                .join('');

            qs('#p-fam').value = p.familiarity || '';
            const instrEl = qs('#p-instructions');
            if (instrEl) instrEl.value = p.instructions || '';
            renderRoleInfo();
        }

        renderPlayerList();
    }

    function renderRoleInfo() {
        const p = state.players[state.selected];
        const box = qs('#roleInfo');
        if (!box) return;
        if (!p) { box.innerHTML = 'Select a player and role to see a description.'; return; }
        const entry = roleEntry(state.version, p.position, p.role);
        if (!entry) { box.innerHTML = '<span class="dim">No description stored for this role.</span>'; return; }
        box.innerHTML = '<p class="muted mb-0"><strong>' + esc(p.role) + '</strong> — ' + esc(entry.description) + '</p>' +
            '<p class="hint mb-0"><strong>Focuses:</strong> ' + esc((entry.focuses || []).join(', ')) + '</p>';
    }

    function renderPlayerList() {
        const list = qs('#builderPlayerList');
        if (!list) return;
        list.innerHTML = state.players.map((p, i) =>
            '<div class="builder-player-row' + (i === state.selected ? ' is-selected' : '') + '" data-select="' + i + '">' +
            '<span class="bpr-pos">' + esc(p.position) + '</span>' +
            '<span class="bpr-role">' + esc(p.role || 'No role') + (p.focus ? ' · ' + esc(p.focus) : '') + '</span>' +
            '</div>'
        ).join('');
        qsa('[data-select]', list).forEach((row) => {
            row.addEventListener('click', () => selectPlayer(parseInt(row.getAttribute('data-select'), 10)));
        });
    }

    /* Keep roles/focuses valid when position or version changes. */
    function normalisePlayer(p) {
        const roles = rolesFor(state.version, p.position);
        if (!roles.length) { p.role = ''; p.focus = ''; return; }
        if (roles.indexOf(p.role) === -1) { p.role = roles[0]; }
        const focuses = focusesFor(state.version, p.position, p.role);
        if (!focuses.length) { p.focus = ''; }
        else if (focuses.indexOf(p.focus) === -1) { p.focus = focuses[0]; }
    }

    /* -------------------------------------------------------- Controls */
    function bindControls() {
        const formationSel = qs('#b-formation');
        if (formationSel) {
            formationSel.addEventListener('change', () => {
                state.formation = formationSel.value;
                buildPlayersFromFormation();
                renderPitch();
                renderSidebar();
            });
        }

        const versionSel = qs('#b-version');
        if (versionSel) {
            versionSel.addEventListener('change', () => {
                state.version = versionSel.value;
                state.players.forEach(normalisePlayer);
                renderPitch();
                renderSidebar();
            });
        }

        const styleSel = qs('#b-style');
        if (styleSel) styleSel.addEventListener('change', () => { state.style = styleSel.value; });

        const nameInput = qs('#b-name');
        if (nameInput) nameInput.addEventListener('input', () => { state.name = nameInput.value; });

        /* Position change -> rebuild role/focus options. */
        const posSel = qs('#p-position');
        if (posSel) {
            posSel.addEventListener('change', () => {
                const p = state.players[state.selected];
                if (!p) return;
                // Keep the slot label (e.g. RCB) but update the base position.
                p.position = posSel.value;
                normalisePlayer(p);
                renderPitch();
                renderSidebar();
            });
        }

        /* Role change -> update focuses. */
        const roleSel = qs('#p-role');
        if (roleSel) {
            roleSel.addEventListener('change', () => {
                const p = state.players[state.selected];
                if (!p) return;
                p.role = roleSel.value;
                const focuses = focusesFor(state.version, p.position, p.role);
                p.focus = focuses[0] || '';
                renderPitch();
                renderSidebar();
            });
        }

        const focusSel = qs('#p-focus');
        if (focusSel) {
            focusSel.addEventListener('change', () => {
                const p = state.players[state.selected];
                if (!p) return;
                p.focus = focusSel.value;
                renderPitch();
                renderPlayerList();
                renderRoleInfo();
            });
        }

        const famSel = qs('#p-fam');
        if (famSel) {
            famSel.addEventListener('change', () => {
                const p = state.players[state.selected];
                if (!p) return;
                p.familiarity = famSel.value;
                renderPlayerList();
            });
        }

        const instrInput = qs('#p-instructions');
        if (instrInput) {
            instrInput.addEventListener('input', () => {
                const p = state.players[state.selected];
                if (!p) return;
                p.instructions = instrInput.value;
                renderPlayerList();
            });
        }

        /* Shape view toggle. */
        qsa('.bshape-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                qsa('.bshape-btn').forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('is-active', active);
                    b.classList.toggle('btn-primary', active);
                    b.classList.toggle('btn-ghost', !active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                state.shape = btn.getAttribute('data-bshape');
                renderPitch();
            });
        });

        const clearBtn = qs('#p-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                const p = state.players[state.selected];
                if (!p) return;
                p.role = ''; p.focus = ''; p.familiarity = '';
                renderPitch();
                renderSidebar();
            });
        }

        const resetBtn = qs('#b-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                buildPlayersFromFormation();
                renderPitch();
                renderSidebar();
                if (window.showToast) window.showToast('Formation reset');
            });
        }

        const clearPitchBtn = qs('#b-clear');
        if (clearPitchBtn) {
            clearPitchBtn.addEventListener('click', () => {
                if (!confirm('Clear all roles and instructions from the pitch?')) return;
                state.players.forEach((p) => { p.role = ''; p.focus = ''; p.familiarity = ''; p.instructions = ''; });
                renderPitch();
                renderSidebar();
                if (window.showToast) window.showToast('Pitch cleared');
            });
        }

        const saveBtn = qs('#b-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveTactic);
        }

        const exportBtn = qs('#b-export');
        if (exportBtn) {
            exportBtn.addEventListener('click', exportJson);
        }

        const importInput = qs('#b-import');
        if (importInput) {
            importInput.addEventListener('change', (e) => {
                const file = e.target.files && e.target.files[0];
                if (file) importJson(file);
                importInput.value = '';
            });
        }

        /* Saved tactic list actions (delegated). */
        const savedList = qs('#savedList');
        if (savedList) {
            savedList.addEventListener('click', (e) => {
                const load = e.target.closest('[data-load]');
                const del = e.target.closest('[data-delete]');
                const dup = e.target.closest('[data-duplicate]');
                const rename = e.target.closest('[data-rename]');
                if (load) loadTactic(load.getAttribute('data-load'));
                if (dup) duplicateTactic(dup.getAttribute('data-duplicate'));
                if (rename) renameTactic(rename.getAttribute('data-rename'));
                if (del) {
                    if (confirm('Delete this saved tactic?')) {
                        if (FAV) FAV.deleteCustom(del.getAttribute('data-delete'));
                        renderSavedList();
                        if (window.showToast) window.showToast('Tactic deleted');
                    }
                }
            });
        }
    }

    function duplicateTactic(id) {
        if (!FAV) return;
        const custom = FAV.getCustom().find((c) => c.id === id);
        if (!custom) return;
        const copy = JSON.parse(JSON.stringify(custom));
        delete copy.id;
        copy.name = (custom.name || 'Untitled') + ' (copy)';
        const newId = FAV.saveCustom(copy);
        renderSavedList();
        if (window.showToast) window.showToast('Tactic duplicated');
        return newId;
    }

    function renameTactic(id) {
        if (!FAV) return;
        const custom = FAV.getCustom().find((c) => c.id === id);
        if (!custom) return;
        const name = prompt('Rename tactic:', custom.name || '');
        if (name === null) return;
        custom.name = name.trim() || custom.name;
        FAV.saveCustom(custom);
        if (state.editingId === id) {
            state.name = custom.name;
            const nameInput = qs('#b-name');
            if (nameInput) nameInput.value = custom.name;
        }
        renderSavedList();
        if (window.showToast) window.showToast('Tactic renamed');
    }

    function saveTactic() {
        const name = (state.name || '').trim() || ('Custom ' + state.formation + ' (' + state.version.toUpperCase() + ')');
        const payload = {
            id: state.editingId || undefined,
            name: name,
            formation: (formationById(state.formation) || {}).name || state.formation,
            formationId: state.formation,
            version: state.version,
            style: state.style,
            players: state.players.map((p) => ({
                position: p.position, role: p.role, focus: p.focus, familiarity: p.familiarity,
                instructions: p.instructions || '', x: p.x, y: p.y
            }))
        };
        if (!FAV) {
            if (window.showToast) window.showToast('Storage unavailable');
            return;
        }
        const id = FAV.saveCustom(payload);
        state.editingId = id;
        renderSavedList();
        if (window.showToast) window.showToast('Tactic saved');
    }

    function loadTactic(id) {
        if (!FAV) return;
        const custom = FAV.getCustom().find((c) => c.id === id);
        if (!custom) return;
        state.editingId = id;
        state.name = custom.name || '';
        state.style = custom.style || '';
        state.version = custom.version || state.version;
        state.formation = custom.formationId || state.formation;

        const nameInput = qs('#b-name');
        if (nameInput) nameInput.value = state.name;
        const versionSel = qs('#b-version');
        if (versionSel) versionSel.value = state.version;
        const styleSel = qs('#b-style');
        if (styleSel) styleSel.value = state.style;
        const formationSel = qs('#b-formation');
        if (formationSel) formationSel.value = state.formation;

        state.players = (custom.players || []).map((p) => ({
            position: p.position, role: p.role, focus: p.focus,
            familiarity: p.familiarity || '', instructions: p.instructions || '',
            x: p.x != null ? p.x : 50, y: p.y != null ? p.y : 50
        }));
        state.selected = -1;
        renderPitch();
        renderSidebar();
        if (window.showToast) window.showToast('Loaded “' + state.name + '”');
    }

    /* Import a previously exported tactic JSON. */
    function importJson(file) {
        const reader = new FileReader();
        reader.onload = () => {
            let data = null;
            try { data = JSON.parse(reader.result); } catch (e) { data = null; }
            if (!data || !Array.isArray(data.players)) {
                if (window.showToast) window.showToast('Invalid tactic file');
                return;
            }
            // Resolve formation id from name if needed.
            let formationId = data.formationId;
            if (!formationId && data.formation) {
                const match = FORMATIONS.find((f) => f.name === data.formation);
                formationId = match ? match.id : state.formation;
            }
            state.editingId = null;
            state.name = (data.name || 'Imported tactic').toString().slice(0, 80);
            state.style = data.style || '';
            state.version = (data.version && ROLE_DB[data.version]) ? data.version : state.version;
            state.formation = formationId || state.formation;
            state.shape = 'base';

            const nameInput = qs('#b-name'); if (nameInput) nameInput.value = state.name;
            const versionSel = qs('#b-version'); if (versionSel) versionSel.value = state.version;
            const styleSel = qs('#b-style'); if (styleSel) styleSel.value = state.style;
            const formationSel = qs('#b-formation'); if (formationSel) formationSel.value = state.formation;
            qsa('.bshape-btn').forEach((b) => {
                const active = b.getAttribute('data-bshape') === 'base';
                b.classList.toggle('is-active', active);
                b.classList.toggle('btn-primary', active);
                b.classList.toggle('btn-ghost', !active);
            });

            state.players = data.players.map((p) => ({
                position: p.position || 'CM',
                role: p.role || '',
                focus: p.focus || '',
                familiarity: p.familiarity || '',
                instructions: p.instructions || '',
                x: p.x != null ? p.x : 50,
                y: p.y != null ? p.y : 50
            }));
            state.players.forEach(normalisePlayer);
            state.selected = -1;
            renderPitch();
            renderSidebar();
            if (window.showToast) window.showToast('Tactic imported');
        };
        reader.readAsText(file);
    }

    /* Load a library tactic into the builder (Duplicate action). */
    function prefillFromTactic(tactic) {
        if (!tactic) return false;
        const version = (tactic.versions && Object.keys(tactic.versions)[0]) || state.version;
        state.editingId = null;
        state.name = (tactic.manager || '') + ' ' + (tactic.club || '') + ' copy';
        state.version = version;
        state.style = (tactic.style && tactic.style[0]) || '';
        const formationMatch = FORMATIONS.find((f) => f.name === tactic.formation);
        state.formation = formationMatch ? formationMatch.id : state.formation;

        const nameInput = qs('#b-name'); if (nameInput) nameInput.value = state.name;
        const versionSel = qs('#b-version'); if (versionSel) versionSel.value = state.version;
        const styleSel = qs('#b-style'); if (styleSel) styleSel.value = state.style;
        const formationSel = qs('#b-formation'); if (formationSel) formationSel.value = state.formation;

        const slots = tacticslotsFor(tactic);
        const rawPlayers = (tactic.versions && tactic.versions[version] && tactic.versions[version].players) || [];
        state.players = rawPlayers.map((p, i) => {
            const s = slots[i] || {};
            return {
                position: p.position || 'CM',
                role: p.role || '',
                focus: p.focus || '',
                familiarity: p.familiarity || '',
                instructions: p.instructions || '',
                x: s.x != null ? s.x : 50,
                y: s.y != null ? s.y : 50
            };
        });
        state.players.forEach(normalisePlayer);
        state.selected = -1;
        renderPitch();
        renderSidebar();
        if (window.showToast) window.showToast('Copied tactic into builder');
        return true;
    }

    function tacticslotsFor(tactic) {
        const form = FORMATIONS.find((f) => f.name === tactic.formation);
        return (form && form.slots) ? form.slots : [];
    }

    function exportJson() {
        const payload = {
            name: state.name || 'Custom tactic',
            formation: (formationById(state.formation) || {}).name || state.formation,
            formationId: state.formation,
            version: state.version,
            style: state.style,
            players: state.players.map((p) => ({
                position: p.position, role: p.role, focus: p.focus,
                familiarity: p.familiarity, instructions: p.instructions || '',
                x: p.x, y: p.y
            }))
        };
        const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = (state.name || 'tactifc-tactic').replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        if (window.showToast) window.showToast('Tactic exported');
    }

    function renderSavedList() {
        const list = qs('#savedList');
        if (!list || !FAV) return;
        const customs = FAV.getCustom();
        if (!customs.length) {
            list.innerHTML = '<div class="hint">No saved tactics yet.</div>';
            return;
        }
        list.innerHTML = customs.map((c) =>
            '<div class="builder-player-row">' +
            '<span class="bpr-pos">' + esc((c.version || '').toUpperCase()) + '</span>' +
            '<span class="bpr-role" style="flex:1;">' + esc(c.name || 'Untitled') + '</span>' +
            '<button type="button" class="btn btn-ghost btn-sm" data-load="' + esc(c.id) + '" title="Load">Load</button>' +
            '<button type="button" class="btn btn-ghost btn-sm" data-duplicate="' + esc(c.id) + '" title="Duplicate">⎘</button>' +
            '<button type="button" class="btn btn-ghost btn-sm" data-rename="' + esc(c.id) + '" title="Rename">✎</button>' +
            '<button type="button" class="btn btn-danger btn-sm" data-delete="' + esc(c.id) + '" title="Delete">✕</button>' +
            '</div>'
        ).join('');
    }

    /* ------------------------------------------------------------ Boot */
    function init() {
        const formationSel = qs('#b-formation');
        if (formationSel) formationSel.value = state.formation;
        const versionSel = qs('#b-version');
        if (versionSel) versionSel.value = state.version;

        // Support ?load=<id> to jump straight into a saved tactic.
        const params = new URLSearchParams(window.location.search);
        const loadId = params.get('load');

        let handled = false;

        // Prefill from a library tactic (Duplicate & Edit).
        if (PREFILL && PREFILL.tactic) {
            handled = prefillFromTactic(PREFILL.tactic);
        }

        // Prefill formation only (e.g. from a variation link).
        if (!handled && PREFILL && PREFILL.formation) {
            const match = FORMATIONS.find((f) => f.name === PREFILL.formation || f.id === PREFILL.formation);
            if (match) {
                state.formation = match.id;
                if (formationSel) formationSel.value = state.formation;
                buildPlayersFromFormation();
                renderPitch();
                renderSidebar();
                handled = true;
            }
        }

        if (!handled && loadId && FAV) {
            loadTactic(loadId);
            handled = true;
        }

        if (!handled) {
            buildPlayersFromFormation();
            renderPitch();
            renderSidebar();
        }

        bindControls();
        renderSavedList();
    }

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(init);
})();
