/* ============================================================================
 * INSOPHINIA POS v7 — theme.js (dark/light mode toggle)
 * ----------------------------------------------------------------------------
 * Persists user preference in localStorage; respects system preference on
 * first visit. Adds/removes .dark class on <html>.
 * ==========================================================================*/

window.InsophiniaTheme = (function () {
    'use strict';

    const STORAGE_KEY = 'insophinia-theme';
    const html = document.documentElement;

    function getStored() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
    }
    function store(val) {
        try { localStorage.setItem(STORAGE_KEY, val); } catch (e) { /* ignore */ }
    }

    function apply(theme) {
        if (theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        // Update toggle icon
        document.querySelectorAll('.theme-toggle').forEach(function (el) {
            const icon = el.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
        });
    }

    function current() {
        return html.classList.contains('dark') ? 'dark' : 'light';
    }

    function toggle() {
        const next = current() === 'dark' ? 'light' : 'dark';
        apply(next);
        store(next);
    }

    function init() {
        const stored = getStored();
        if (stored === 'dark' || stored === 'light') {
            apply(stored);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            apply('dark');
        } else {
            apply('light');
        }
        // Wire up toggle buttons (delegation)
        document.addEventListener('click', function (e) {
            const t = e.target.closest('.theme-toggle');
            if (t) {
                e.preventDefault();
                toggle();
            }
        });
    }

    // Run on DOMContentLoaded (or immediately if already loaded)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { toggle, current, apply, init };
})();
