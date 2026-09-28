/* =========================================================================
   TactiFC — player-fit.js
   Squad editor + TactiFC Tactical Fit estimator. Mirrors the server-side
   scoring rules in includes/functions.php (tacticFitScore).
   ========================================================================= */
(function () {
    'use strict';

    const app = document.getElementById('playerFitApp');
    if (!app) return;

    const POSITIONS = safeParse(app.getAttribute('data-position-list'), []);
    const TACTICS = safeParse(app.getAttribute('data-tactics'), {});
    const ROLE_DB = safeParse(app.getAttribute('data-role-db'), {});
    const STORE_KEY = 'tactifc:squad';

    const ALIASES = {
        RWB: 'RB', LWB: 'LB', LCB: 'CB', RCB: 'CB',
        RDM: 'CDM', LDM: 'CDM', LST: 'ST', RST: 'ST',
        LAM: 'CAM', RAM: 'CAM', LCM: 'CM', RCM: 'CM'
    };

    function safeParse(str, fallback) { try { const v = JSON.parse(str); return v == null ? fallback : v; } catch (e) { return fallback; } }
    function basePos(p) { return ALIASES[p] || p; }
    function qs(s, r) { return (r || document).querySelector(s); }
    function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function esc(s) { return (s || '').toString().replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    function positionGroup(p) {
        p = basePos(p);
        if (p === 'GK') return 'gk';
        if (['CB', 'LB', 'RB'].indexOf(p) !== -1) return 'def';
        if (['CDM', 'CM', 'CAM', 'LM', 'RM'].indexOf(p) !== -1) return 'mid';
        return 'att';
    }

    /* --- Mirror of PHP tacticFitScore --------------------------------- */
    function fitScore(player, position, role) {
        const stats = {
            pace: num(player.pace), shooting: num(player.shooting), passing: num(player.passing),
            dribbling: num(player.dribbling), defending: num(player.defending),
            physical: num(player.physical), overall: num(player.overall)
        };
        const r = (role || '').toLowerCase();
        let weights = { pace: .1, shooting: .1, passing: .15, dribbling: .15, defending: .2, physical: .1, overall: .2 };
        const reasons = [];

        if (r.indexOf('goalkeeper') !== -1 || r.indexOf('keeper') !== -1) {
            weights = { pace: .05, shooting: .02, passing: .13, dribbling: .05, defending: .4, physical: .15, overall: .2 };
            reasons.push('Goalkeeper role weighted heavily toward defensive and physical attributes.');
        } else if (r.indexOf('poacher') !== -1 || r.indexOf('advanced forward') !== -1 || r.indexOf('complete forward') !== -1) {
            weights = { pace: .2, shooting: .3, passing: .08, dribbling: .17, defending: .02, physical: .08, overall: .15 };
            reasons.push('Forward role weighted toward pace, shooting and dribbling.');
        } else if (r.indexOf('target') !== -1 || r.indexOf('false nine') !== -1) {
            weights = { pace: .1, shooting: .2, passing: .18, dribbling: .14, defending: .03, physical: .2, overall: .15 };
            reasons.push('Hold-up role values physicality, passing and shooting.');
        } else if (r.indexOf('playmaker') !== -1 || r.indexOf('mezzala') !== -1 || r.indexOf('box to box') !== -1 || r.indexOf('box crasher') !== -1) {
            weights = { pace: .1, shooting: .1, passing: .28, dribbling: .22, defending: .1, physical: .07, overall: .13 };
            reasons.push('Creative midfield role weighted toward passing and dribbling.');
        } else if (r.indexOf('holding') !== -1 || r.indexOf('ball winner') !== -1 || r.indexOf('anchor') !== -1) {
            weights = { pace: .07, shooting: .04, passing: .18, dribbling: .1, defending: .32, physical: .17, overall: .12 };
            reasons.push('Defensive midfield role weighted toward defending and physicality.');
        } else if (r.indexOf('wing') !== -1 || r.indexOf('winger') !== -1 || r.indexOf('inside forward') !== -1 || r.indexOf('wide playmaker') !== -1) {
            weights = { pace: .26, shooting: .13, passing: .16, dribbling: .25, defending: .05, physical: .05, overall: .1 };
            reasons.push('Wide role weighted toward pace, dribbling and passing.');
        } else if (r.indexOf('defender') !== -1 || r.indexOf('stopper') !== -1 || r.indexOf('back') !== -1) {
            weights = { pace: .12, shooting: .02, passing: .13, dribbling: .08, defending: .35, physical: .2, overall: .1 };
            reasons.push('Defensive role weighted toward defending and physicality.');
        } else {
            reasons.push('Balanced weighting applied for this role.');
        }

        let total = 0;
        Object.keys(weights).forEach((k) => { total += weights[k]; });
        let score = 0;
        Object.keys(weights).forEach((k) => { score += (stats[k] / 100) * (weights[k] / total) * 100; });
        score = Math.round(score);

        const pref = (player.preferredPosition || '').toUpperCase();
        const base = basePos(position);
        if (pref) {
            if (pref === base) { score = Math.min(100, score + 10); reasons.push('Player prefers this exact position (+10).'); }
            else if (positionGroup(pref) === positionGroup(base)) { score = Math.min(100, score + 4); reasons.push('Player prefers a related position (+4).'); }
            else { score = Math.max(0, score - 6); reasons.push('Player prefers a different position (-6).'); }
        }
        const label = score >= 85 ? 'Excellent' : (score >= 72 ? 'Good' : (score >= 58 ? 'Fair' : 'Poor'));
        return { score: score, label: label, reasons: reasons };
    }

    function num(v) { const n = parseInt(v, 10); return isNaN(n) ? 70 : Math.max(1, Math.min(99, n)); }

    /* --- State ------------------------------------------------------- */
    let squad = load();
    let tacticId = '';
    let version = 'fc26';

    function load() {
        try {
            const raw = localStorage.getItem(STORE_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function save() {
        try { localStorage.setItem(STORE_KEY, JSON.stringify(squad)); } catch (e) { /* ignore */ }
    }

    /* --- Squad editor ------------------------------------------------- */
    function addPlayer(p) {
        squad.push(p || {
            name: '', preferredPosition: 'CM', overall: 75,
            pace: 70, shooting: 70, passing: 70, dribbling: 70, defending: 70, physical: 70
        });
        save();
        renderSquad();
        renderResults();
    }

    function renderSquad() {
        const list = qs('#pfSquadList');
        if (!list) return;
        if (!squad.length) {
            list.innerHTML = '<div class="hint">No players yet. Add one or load the sample squad.</div>';
            return;
        }
        list.innerHTML = squad.map((p, i) => `
            <div class="squad-row" data-index="${i}">
                <div class="squad-row-top">
                    <input class="sr-name" type="text" placeholder="Player name" value="${esc(p.name)}" data-field="name" aria-label="Player name">
                    <select class="sr-pos" data-field="preferredPosition" aria-label="Preferred position">
                        ${POSITIONS.map((pos) => `<option value="${pos}"${pos === p.preferredPosition ? ' selected' : ''}>${pos}</option>`).join('')}
                    </select>
                    <input class="sr-ovr" type="number" min="1" max="99" value="${num(p.overall)}" data-field="overall" aria-label="Overall">
                    <button type="button" class="btn btn-danger btn-sm sr-del" data-del="${i}" aria-label="Remove player">✕</button>
                </div>
                <div class="squad-stats">
                    ${['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physical'].map((s) =>
                        `<label class="squad-stat"><span>${s.slice(0, 3).toUpperCase()}</span>
                        <input type="number" min="1" max="99" value="${num(p[s])}" data-field="${s}"></label>`).join('')}
                </div>
            </div>
        `).join('');

        qsa('[data-field]', list).forEach((input) => {
            input.addEventListener('input', () => {
                const row = input.closest('.squad-row');
                const idx = parseInt(row.getAttribute('data-index'), 10);
                const field = input.getAttribute('data-field');
                squad[idx][field] = input.type === 'number' ? num(input.value) : input.value;
                save();
                renderResults();
            });
        });
        qsa('[data-del]', list).forEach((btn) => {
            btn.addEventListener('click', () => {
                squad.splice(parseInt(btn.getAttribute('data-del'), 10), 1);
                save();
                renderSquad();
                renderResults();
            });
        });
    }

    /* --- Fit results -------------------------------------------------- */
    function tacticPlayers() {
        const t = TACTICS[tacticId];
        if (!t) return [];
        const vd = t.versions[version] || t.versions[Object.keys(t.versions)[0]] || {};
        return vd.players || [];
    }

    function renderResults() {
        const host = qs('#pfResults');
        if (!host) return;
        if (!tacticId) {
            host.innerHTML = '<div class="empty-state"><div class="big">🎯</div><p class="muted">Choose a tactic to see fit suggestions.</p></div>';
            return;
        }
        if (!squad.length) {
            host.innerHTML = '<div class="empty-state"><div class="big">👥</div><p class="muted">Add players to see fit suggestions.</p></div>';
            return;
        }
        const slots = tacticPlayers();
        if (!slots.length) {
            host.innerHTML = '<p class="muted">No role data for this tactic/version.</p>';
            return;
        }

        const rows = squad.map((player) => {
            const pref = (player.preferredPosition || '').toUpperCase();
            let best = null, bestMatch = -1;
            slots.forEach((slot) => {
                const base = basePos(slot.position);
                let match = 0;
                if (pref && base === pref) match = 3;
                else if (pref && positionGroup(pref) === positionGroup(slot.position)) match = 2;
                if (match > bestMatch) { bestMatch = match; best = slot; }
            });
            if (!best) return '';
            const fit = fitScore(player, best.position, best.role);
            return { player: player.name || 'Player', slot: best, fit: fit };
        }).filter(Boolean);

        host.innerHTML = `
            <div class="table-scroll">
                <table class="data-table fit-table">
                    <thead><tr><th>Player</th><th>Position</th><th>Suggested Role</th><th>Fit</th><th></th></tr></thead>
                    <tbody>
                        ${rows.map((r) => `
                            <tr>
                                <td><strong>${esc(r.player)}</strong></td>
                                <td><span class="badge badge-pos">${esc(r.slot.position)}</span></td>
                                <td><span class="badge badge-role">${esc(r.slot.role)}</span> ${r.slot.focus ? `<span class="badge badge-focus">${esc(r.slot.focus)}</span>` : ''}</td>
                                <td><span class="fit-badge fit-${r.fit.label.toLowerCase()}">${r.fit.label} <span class="fit-score">${r.fit.score}</span></span></td>
                                <td><button type="button" class="btn btn-ghost btn-sm fit-why" data-why='${esc(JSON.stringify(r.fit.reasons))}'>Why?</button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            <p class="hint mt-2"><strong>TactiFC Tactical Fit</strong> — a transparent estimate, not an official EA rating.</p>
        `;

        qsa('.fit-why', host).forEach((btn) => {
            btn.addEventListener('click', () => {
                let reasons = [];
                try { reasons = JSON.parse(btn.getAttribute('data-why')); } catch (e) { reasons = []; }
                alert('Fit reasoning:\n\n• ' + reasons.join('\n• '));
            });
        });
    }

    /* --- Wiring ------------------------------------------------------- */
    function init() {
        const addBtn = qs('#pf-add');
        if (addBtn) addBtn.addEventListener('click', () => addPlayer());

        const clearBtn = qs('#pf-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (!squad.length || confirm('Clear all players from your squad?')) {
                    squad = [];
                    save();
                    renderSquad();
                    renderResults();
                }
            });
        }

        const sampleBtn = qs('#pf-sample');
        if (sampleBtn) {
            sampleBtn.addEventListener('click', () => {
                squad = [
                    { name: 'Keeper', preferredPosition: 'GK', overall: 82, pace: 55, shooting: 20, passing: 62, dribbling: 45, defending: 84, physical: 78 },
                    { name: 'Right Back', preferredPosition: 'RB', overall: 80, pace: 88, shooting: 55, passing: 74, dribbling: 76, defending: 78, physical: 76 },
                    { name: 'Centre Back A', preferredPosition: 'CB', overall: 84, pace: 72, shooting: 38, passing: 70, dribbling: 58, defending: 86, physical: 88 },
                    { name: 'Centre Back B', preferredPosition: 'CB', overall: 81, pace: 68, shooting: 35, passing: 66, dribbling: 55, defending: 83, physical: 86 },
                    { name: 'Left Back', preferredPosition: 'LB', overall: 79, pace: 85, shooting: 52, passing: 73, dribbling: 74, defending: 77, physical: 74 },
                    { name: 'Defensive Mid', preferredPosition: 'CDM', overall: 83, pace: 70, shooting: 58, passing: 80, dribbling: 72, defending: 82, physical: 80 },
                    { name: 'Box to Box', preferredPosition: 'CM', overall: 82, pace: 76, shooting: 70, passing: 78, dribbling: 76, defending: 74, physical: 78 },
                    { name: 'Creator', preferredPosition: 'CAM', overall: 85, pace: 74, shooting: 78, passing: 88, dribbling: 86, defending: 45, physical: 60 },
                    { name: 'Right Winger', preferredPosition: 'RW', overall: 84, pace: 92, shooting: 78, passing: 76, dribbling: 88, defending: 38, physical: 62 },
                    { name: 'Left Winger', preferredPosition: 'LW', overall: 83, pace: 90, shooting: 76, passing: 75, dribbling: 87, defending: 36, physical: 60 },
                    { name: 'Striker', preferredPosition: 'ST', overall: 86, pace: 86, shooting: 88, passing: 68, dribbling: 80, defending: 32, physical: 80 }
                ];
                save();
                renderSquad();
                renderResults();
                if (window.showToast) window.showToast('Sample squad loaded');
            });
        }

        const tacticSel = qs('#pf-tactic');
        if (tacticSel) {
            tacticSel.addEventListener('change', () => { tacticId = tacticSel.value; renderResults(); });
        }
        const verSel = qs('#pf-version');
        if (verSel) {
            verSel.addEventListener('change', () => { version = verSel.value; renderResults(); });
        }

        renderSquad();
        renderResults();
    }

    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
