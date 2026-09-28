/* =========================================================================
   TactiFC — formation-shapes.js
   Base / With Ball / Without Ball shape switching on the formation page.
   ========================================================================= */
(function () {
    'use strict';

    function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

    function init() {
        const buttons = qsa('.fshape-btn');
        if (!buttons.length) return;

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                buttons.forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('is-active', active);
                    b.classList.toggle('btn-primary', active);
                    b.classList.toggle('btn-ghost', !active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                const shape = btn.getAttribute('data-fshape');
                qsa('[data-fshape-panel]').forEach((panel) => {
                    panel.hidden = panel.getAttribute('data-fshape-panel') !== shape;
                });
            });
        });
    }

    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
