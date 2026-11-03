/**
 * Cohorts: search the list and a two-step delete (no browser dialog).
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-cohorts]');
        if (!root) {
            return;
        }
        var search = root.querySelector('[data-ch-search]');
        var none = root.querySelector('[data-ch-none]');
        if (search) {
            search.addEventListener('input', function() {
                var term = search.value.trim().toLowerCase();
                var shown = 0;
                root.querySelectorAll('.ll-ch').forEach(function(c) {
                    var on = term === '' || c.getAttribute('data-name').toLowerCase().indexOf(term) !== -1;
                    c.hidden = !on;
                    shown += on ? 1 : 0;
                });
                none.hidden = shown > 0;
            });
        }
        root.querySelectorAll('[data-ch-delete]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (btn.classList.contains('is-armed')) {
                    return;
                }
                e.preventDefault();
                btn.classList.add('is-armed');
                var label = btn.querySelector('span');
                var original = label.textContent;
                label.textContent = btn.getAttribute('data-confirm');
                window.setTimeout(function() {
                    btn.classList.remove('is-armed');
                    label.textContent = original;
                }, 4000);
            });
        });
        var open = root.querySelector('.ll-ch[open]');
        if (open) {
            open.scrollIntoView({block: 'center'});
            var ta = open.querySelector('textarea');
            if (ta) {
                ta.focus();
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
