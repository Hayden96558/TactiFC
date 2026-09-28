/* =========================================================================
   TactiFC — share.js
   Share / copy / print / import / export helpers used across pages.
   ========================================================================= */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    function toast(msg) {
        if (window.showToast) { window.showToast(msg); return; }
        console.log(msg);
    }

    /* Robust clipboard copy with a fallback for insecure contexts / older
       browsers (e.g. plain http on some setups). */
    function copyText(text) {
        return new Promise((resolve, reject) => {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(resolve).catch(() => fallback());
            } else {
                fallback();
            }
            function fallback() {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.top = '-1000px';
                    document.body.appendChild(ta);
                    ta.select();
                    const ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                    ok ? resolve() : reject(new Error('copy failed'));
                } catch (e) { reject(e); }
            }
        });
    }
    window.tactifcCopy = copyText;

    function buildTacticSummary(data) {
        const lines = [];
        lines.push('TactiFC');
        lines.push('');
        lines.push((data.manager || '') + ' \u2014 ' + (data.club || '') + ' ' + (data.season || ''));
        lines.push('');
        lines.push('Formation: ' + (data.formation || ''));
        if (data.style && data.style.length) {
            lines.push('Style: ' + data.style.join(', '));
        }
        if (data.withBall || data.withoutBall) {
            lines.push('With Ball: ' + (data.withBall || '\u2014'));
            lines.push('Without Ball: ' + (data.withoutBall || '\u2014'));
        }
        if (data.keyRoles && data.keyRoles.length) {
            lines.push('');
            lines.push('Key Roles:');
            data.keyRoles.forEach((r) => lines.push('\u2022 ' + r));
        }
        lines.push('');
        lines.push('Source: ' + (data.url || window.location.href));
        return lines.join('\n');
    }

    function bindButtons(root) {
        (root || document).querySelectorAll('[data-copy-link]').forEach((btn) => {
            if (btn._copyBound) return;
            btn._copyBound = true;
            btn.addEventListener('click', () => {
                const url = btn.getAttribute('data-copy-link') || window.location.href;
                copyText(url).then(() => toast('\u2713 Tactic link copied')).catch(() => toast('Could not copy link'));
            });
        });

        (root || document).querySelectorAll('[data-copy-tactic]').forEach((btn) => {
            if (btn._copyTacticBound) return;
            btn._copyTacticBound = true;
            btn.addEventListener('click', () => {
                let data = null;
                const raw = btn.getAttribute('data-copy-tactic');
                if (raw) {
                    try { data = JSON.parse(raw); } catch (e) { data = null; }
                }
                if (!data && window.TACTIFC_TACTIC) {
                    const T = window.TACTIFC_TACTIC;
                    const version = Object.keys(T.versions || {})[0] || 'fc25';
                    const players = (T.versions && T.versions[version]) || [];
                    data = {
                        manager: T.manager, club: T.club, season: T.season,
                        formation: (T.settings && T.settings[version] && T.settings[version].formation) || '',
                        style: [],
                        keyRoles: players.slice(0, 5).map((p) => p.role + ' ' + p.position),
                        url: window.location.href
                    };
                }
                if (!data) { toast('Nothing to copy'); return; }
                copyText(buildTacticSummary(data)).then(() => toast('\u2713 Tactic copied')).catch(() => toast('Could not copy'));
            });
        });

        (root || document).querySelectorAll('[data-print]').forEach((btn) => {
            if (btn._printBound) return;
            btn._printBound = true;
            btn.addEventListener('click', () => window.print());
        });
    }

    window.TactiFCShare = { bind: bindButtons, buildTacticSummary: buildTacticSummary, copyText: copyText };

    ready(() => bindButtons(document));
})();
