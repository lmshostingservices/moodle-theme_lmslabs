/**
 * Question bank: filter by category, type and words.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-qb]');
        if (!root) {
            return;
        }
        var rows = Array.prototype.slice.call(root.querySelectorAll('[data-qb-row]'));
        var cats = Array.prototype.slice.call(root.querySelectorAll('[data-qb-cat]'));
        var q = root.querySelector('[data-qb-q]');
        var type = root.querySelector('[data-qb-type]');
        var none = root.querySelector('[data-qb-none]');
        var cat = '0';
        var apply = function() {
            var words = q ? q.value.trim().toLowerCase() : '';
            var shown = 0;
            rows.forEach(function(r) {
                var on = (cat === '0' || r.getAttribute('data-cat') === cat) &&
                    (!type || !type.value || r.getAttribute('data-type') === type.value) &&
                    (!words || r.getAttribute('data-name').toLowerCase().indexOf(words) !== -1);
                r.hidden = !on;
                shown += on ? 1 : 0;
            });
            if (none) {
                none.hidden = shown > 0 || !rows.length;
            }
        };
        cats.forEach(function(b) {
            b.addEventListener('click', function() {
                cat = b.getAttribute('data-qb-cat');
                cats.forEach(function(x) {
                    x.classList.toggle('is-on', x === b);
                });
                // New questions go into the category that is open.
                root.querySelectorAll('[data-qb-newlink]').forEach(function(a) {
                    var u = new URL(a.getAttribute('data-qb-newlink'), window.location.href);
                    if (cat !== '0') {
                        u.searchParams.set('category', cat);
                    }
                    a.href = u.toString();
                });
                apply();
            });
        });
        if (q) {
            q.addEventListener('input', apply);
        }
        if (type) {
            type.addEventListener('change', apply);
        }
        apply();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
