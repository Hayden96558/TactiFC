/* =========================================================================
   TactiFC — tactic.js
   Tactic detail enhancements: shape switching (base / with ball / without
   ball), movement overlay toggle, and shape meta updates.
   ========================================================================= */
(function () {
    'use strict';

    const T = window.TACTIFC_TACTIC || null;
    if (!T) return;

    const MOVEMENT = {
        // Rough role-driven movement vectors in % of the pitch (x right, y down).
        GK:        { with: { dx: 0, dy: 3 },  without: { dx: 0, dy: 2 } },
        CB:        { with: { dx: 0, dy: -2 }, without: { dx: 0, dy: 3 } },
        LB:        { with: { dx: -4, dy: -9 }, without: { dx: 4, dy: 6 } },
        RB:        { with: { dx: 4, dy: -9 },  without: { dx: -4, dy: 6 } },
        LWB:       { with: { dx: -4, dy: -10 }, without: { dx: 6, dy: 7 } },
        RWB:       { with: { dx: 4, dy: -10 },  without: { dx: -6, dy: 7 } },
        CDM:       { with: { dx: 0, dy: 3 },   without: { dx: 0, dy: 4 } },
        CM:        { with: { dx: 0, dy: -6 },  without: { dx: 0, dy: 5 } },
        CAM:       { with: { dx: 0, dy: -6 },  without: { dx: 0, dy: 6 } },
        LM:        { with: { dx: -3, dy: -7 }, without: { dx: 5, dy: 6 } },
        RM:        { with: { dx: 3, dy: -7 },  without: { dx: -5, dy: 6 } },
        LW:        { with: { dx: -3, dy: -9 }, without: { dx: 5, dy: 8 } },
        RW:        { with: { dx: 3, dy: -9 },  without: { dx: -5, dy: 8 } },
        ST:        { with: { dx: 0, dy: -7 },  without: { dx: 0, dy: 6 } }
    };

    const ALIASES = {
        RCB: 'CB', LCB: 'CB', LCM: 'CM', RCM: 'CM', LDM: 'CDM', RDM: 'CDM',
        LAM: 'CAM', RAM: 'CAM', LST: 'ST', RST: 'ST'
    };
    function base(pos) { return ALIASES[pos] || pos; }

    let currentShape = 'base';
    let movementOn = false;

    function qs(s, r) { return (r || document).querySelector(s); }
    function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

    function positionGroup(p) {
        p = base((p || '').toUpperCase());
        if (p === 'GK') return 'gk';
        if (p === 'DEF' || ['CB', 'LB', 'RB', 'LCB', 'RCB', 'LWB', 'RWB'].indexOf(p) !== -1) return 'def';
        if (p === 'MID' || ['CDM', 'CM', 'CAM', 'LM', 'RM', 'LCM', 'RCM', 'LDM', 'RDM', 'LAM', 'RAM'].indexOf(p) !== -1) return 'mid';
        return 'att';
    }
    function bandOfGroup(g) { const m = { gk: 0, def: 0, mid: 1, att: 2 }; return m[g] != null ? m[g] : 1; }
    function bandOfSlot(pos) {
        const p = (pos || '').toUpperCase();
        // Generic procedural labels.
        if (p === 'GK') return 0;
        if (p === 'DEF' || p === 'CB' || p === 'LB' || p === 'RB' ||
            p === 'LCB' || p === 'RCB' || p === 'LWB' || p === 'RWB') return 0;
        if (p === 'MID' || p === 'CDM' || p === 'CM' || p === 'CAM' ||
            p === 'LM' || p === 'RM') return 1;
        if (p === 'ATT' || p === 'ST' || p === 'LW' || p === 'RW' ||
            p === 'LST' || p === 'RST') return 2;
        const g = positionGroup(p);
        if (g === 'def' || g === 'gk') return 0;
        if (g === 'mid') return 1;
        return 2;
    }

    /* Formation coordinate tables, copied from the PHP formation database. */
    const FORMS = {
        '4-3-3':        [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['LCM',28,52],['CM',50,56],['RCM',72,52],['LW',16,24],['ST',50,16],['RW',84,24]],
        '4-3-3 holding':[['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['CDM',50,62],['LCM',28,46],['RCM',72,46],['LW',16,24],['ST',50,16],['RW',84,24]],
        '4-2-3-1':      [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['RDM',36,60],['LDM',64,60],['LW',16,36],['CAM',50,38],['RW',84,36],['ST',50,14]],
        '4-4-2':        [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['LM',16,50],['LCM',38,54],['RCM',62,54],['RM',84,50],['LST',38,18],['RST',62,18]],
        '4-1-4-1':      [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['CDM',50,63],['LM',16,48],['LCM',38,50],['RCM',62,50],['RM',84,48],['ST',50,14]],
        '4-3-2-1':      [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['LCM',28,56],['CDM',50,62],['RCM',72,56],['LAM',35,36],['RAM',65,36],['ST',50,14]],
        '4-1-2-1-2':    [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['CDM',50,62],['LCM',26,50],['RCM',74,50],['CAM',50,38],['LST',38,16],['RST',62,16]],
        '4-2-2-2':      [['GK',50,92],['LB',16,74],['LCB',36,78],['RCB',64,78],['RB',84,74],['LDM',38,58],['RDM',62,58],['LAM',30,38],['RAM',70,38],['LST',38,16],['RST',62,16]],
        '3-5-2':        [['GK',50,92],['LCB',30,78],['CB',50,80],['RCB',70,78],['LWB',12,56],['LCM',34,54],['CM',50,56],['RCM',66,54],['RWB',88,56],['LST',38,18],['RST',62,18]],
        '3-4-2-1':      [['GK',50,92],['LCB',30,78],['CB',50,80],['RCB',70,78],['LWB',12,54],['LCM',38,56],['RCM',62,56],['RWB',88,54],['LAM',32,34],['RAM',68,34],['ST',50,14]],
        '3-4-3':        [['GK',50,92],['LCB',30,78],['CB',50,80],['RCB',70,78],['LWB',12,54],['LCM',38,56],['RCM',62,56],['RWB',88,54],['LW',18,22],['ST',50,14],['RW',82,22]],
        '5-3-2':        [['GK',50,92],['LWB',12,72],['LCB',32,80],['CB',50,82],['RCB',68,80],['RWB',88,72],['LCM',32,54],['CM',50,56],['RCM',68,54],['LST',40,18],['RST',60,18]],
        '5-2-3':        [['GK',50,92],['LWB',12,72],['LCB',32,80],['CB',50,82],['RCB',68,80],['RWB',88,72],['LCM',38,56],['RCM',62,56],['LW',18,22],['ST',50,16],['RW',82,22]]
    };

    /** Build a rest-defence shape procedurally (3-2-5, 4-2-4, 5-4-1, 4-5-1…). */
    function proceduralShape(label) {
        const parts = label.split('-').map(Number).filter((n) => n > 0);
        const total = parts.reduce((a, b) => a + b, 0);
        if (!parts.length || total < 8 || total > 10) return null;
        const lineCount = parts.length;
        const yStart = 82, yEnd = 16;
        const step = lineCount > 1 ? (yStart - yEnd) / (lineCount - 1) : 0;
        const slots = [{ pos: 'GK', x: 50, y: 93 }];
        parts.forEach((count, li) => {
            const y = yStart - step * li;
            const margin = count >= 5 ? 9 : (count >= 4 ? 12 : 18);
            const span = 100 - margin * 2;
            const xs = [];
            for (let i = 0; i < count; i++) xs.push(count === 1 ? 50 : margin + span * (i / (count - 1)));
            const isLast = li === lineCount - 1;
            const isFirst = li === 0;
            xs.forEach((x) => {
                let pos = 'MID';
                if (isLast) pos = count === 1 ? 'ST' : 'ATT';
                else if (isFirst) pos = count <= 3 ? 'CB' : 'DEF';
                slots.push({ pos: pos, x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10 });
            });
        });
        return slots;
    }

    /** Resolve the target slots for a shape label (formation table or procedural). */
    function targetSlots(label) {
        if (!label) return null;
        const key = label.toLowerCase().replace(/\s+/g, ' ');
        if (FORMS[key]) return FORMS[key].map(([pos, x, y]) => ({ pos, x, y }));
        const dashed = label.toLowerCase().replace(/ /g, '-');
        if (FORMS[dashed]) return FORMS[dashed].map(([pos, x, y]) => ({ pos, x, y }));
        // Real formations not in the table -> procedural fallback.
        return proceduralShape(label);
    }

    /**
     * Assign players to target slots line by line (deepest line first),
     * matching each line to the players whose band fits best.
     */
    function assignToShape(players, target) {
        // Group target slots into lines by y.
        const byY = {};
        target.forEach((s) => {
            const k = String(Math.round(s.y));
            (byY[k] = byY[k] || []).push(s);
        });
        const lines = Object.keys(byY)
            .sort((a, b) => Number(b) - Number(a)) // deepest first
            .map((k) => byY[k].sort((a, b) => a.x - b.x));

        const gkIdx = players.findIndex((p) => (p.pos || '').toUpperCase() === 'GK');
        const pool = players
            .map((p, i) => ({ i, pos: (p.pos || '').toUpperCase(), x: p.baseX, y: p.baseY }))
            .filter((e) => e.i !== gkIdx)
            .sort((a, b) => b.y - a.y);

        const assigned = {};
        // GK to the deepest GK slot.
        let gkPlaced = false;
        for (let li = 0; li < lines.length && !gkPlaced; li++) {
            const gkSlot = lines[li].find((s) => (s.pos || '').toUpperCase() === 'GK');
            if (gkSlot && gkIdx !== -1) {
                assigned[gkIdx] = gkSlot;
                lines[li] = lines[li].filter((s) => s !== gkSlot);
                gkPlaced = true;
            }
        }
        if (!gkPlaced && gkIdx !== -1) assigned[gkIdx] = { x: 50, y: 93 };

        let remaining = pool.slice();
        lines.forEach((line) => {
            if (!line.length || !remaining.length) return;
            const need = line.length;
            const lineBand = bandOfSlot(line[0].pos);
            remaining.sort((a, b) => {
                const da = Math.abs(bandOfGroup(positionGroup(a.pos)) - lineBand);
                const db = Math.abs(bandOfGroup(positionGroup(b.pos)) - lineBand);
                if (da !== db) return da - db;
                return b.y - a.y;
            });
            const chosen = remaining.splice(0, need);
            chosen.sort((a, b) => a.x - b.x);
            line.forEach((slot, k) => {
                if (chosen[k]) assigned[chosen[k].i] = slot;
            });
        });

        return players.map((p, i) => {
            const c = assigned[i];
            return c ? { x: c.x, y: c.y } : { x: p.baseX, y: p.baseY };
        });
    }

    /* Compute the coordinates for a player given the current shape. */
    function shapeCoords(playersInWrap, shape) {
        const entries = playersInWrap.map((el) => ({
            el,
            pos: (el.getAttribute('data-player-pos') || '').toUpperCase(),
            baseX: parseFloat(el.getAttribute('data-base-x')),
            baseY: parseFloat(el.getAttribute('data-base-y'))
        }));

        if (shape === 'base') {
            entries.forEach((e) => { e.x = e.baseX; e.y = e.baseY; });
            return entries;
        }

        const label = shape === 'with'
            ? (T.shapes && T.shapes.withBall)
            : (T.shapes && T.shapes.withoutBall);
        const target = targetSlots(label || '');
        if (!target) {
            entries.forEach((e) => { e.x = e.baseX; e.y = e.baseY; });
            return entries;
        }
        const coords = assignToShape(entries, target);
        entries.forEach((e, i) => { e.x = coords[i].x; e.y = coords[i].y; });
        return entries;
    }

    function renderMovement(pitch) {
        const old = pitch.querySelector('.pitch-movement');
        if (old) old.remove();
        if (!movementOn) return;

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'pitch-movement');
        svg.setAttribute('viewBox', '0 0 100 100');
        svg.setAttribute('preserveAspectRatio', 'none');

        qsa('.player', pitch).forEach((player) => {
            const cur = { x: parseFloat(player.style.left) || 50, y: parseFloat(player.style.top) || 50 };
            const pos = base((player.getAttribute('data-player-pos') || '').toUpperCase());
            const vec = MOVEMENT[pos];
            if (!vec) return;
            const v = currentShape === 'without' ? vec.without : vec.with;
            const x2 = cur.x + v.dx;
            const y2 = cur.y + v.dy;
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', cur.x); line.setAttribute('y1', cur.y);
            line.setAttribute('x2', x2); line.setAttribute('y2', y2);
            line.setAttribute('stroke', 'rgba(46,230,166,0.85)');
            line.setAttribute('stroke-width', '0.7');
            line.setAttribute('stroke-dasharray', '2 1.5');
            line.setAttribute('marker-end', 'url(#mvArrow)');
            svg.appendChild(line);
        });

        const defsNS = 'http://www.w3.org/2000/svg';
        const defs = document.createElementNS(defsNS, 'defs');
        defs.innerHTML = '<marker id="mvArrow" markerWidth="6" markerHeight="6" refX="4" refY="3" orient="auto"><path d="M0,0 L6,3 L0,6 Z" fill="rgba(46,230,166,0.9)"/></marker>';
        svg.insertBefore(defs, svg.firstChild);

        pitch.appendChild(svg);
    }

    function applyShape(shape) {
        currentShape = shape;
        qsa('.pitch-version').forEach((wrap) => {
            if (wrap.hidden) return;
            const playersInWrap = qsa('.player', wrap);
            const coords = shapeCoords(playersInWrap, shape);
            coords.forEach((c) => {
                c.el.style.left = c.x + '%';
                c.el.style.top = c.y + '%';
            });
            const pitch = wrap.querySelector('.pitch');
            if (pitch) renderMovement(pitch);
        });
    }

    function init() {
        // Record base coordinates once.
        qsa('.player').forEach((el) => {
            if (el.hasAttribute('data-base-x')) return;
            el.setAttribute('data-base-x', parseFloat(el.style.left) || 50);
            el.setAttribute('data-base-y', parseFloat(el.style.top) || 50);
        });

        qsa('.shape-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                qsa('.shape-btn').forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('is-active', active);
                    b.classList.toggle('btn-primary', active);
                    b.classList.toggle('btn-ghost', !active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                applyShape(btn.getAttribute('data-shape'));
            });
        });

        const toggle = qs('#showMovement');
        if (toggle) {
            toggle.addEventListener('change', () => {
                movementOn = toggle.checked;
                qsa('.pitch-version').forEach((wrap) => {
                    if (wrap.hidden) return;
                    const pitch = wrap.querySelector('.pitch');
                    if (pitch) renderMovement(pitch);
                });
            });
        }

        // Re-apply shape when the FC version changes.
        if (window.TactiFC && document.body.getAttribute('data-page') === 'tactic.php') {
            document.addEventListener('tactifc:version', () => {
                // Give roles.js a tick to toggle the correct pitch panel.
                setTimeout(() => applyShape(currentShape), 0);
            });
        }
    }

    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
