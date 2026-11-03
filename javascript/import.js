/**
 * Import: search course cards, pick one, import.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var form = document.querySelector('[data-ll-im]');
        if (!form) {
            return;
        }
        var cards = Array.prototype.slice.call(form.querySelectorAll('[data-im-card]'));
        var bar = form.querySelector('[data-im-bar]');
        form.querySelector('[data-im-q]').addEventListener('input', function(e) {
            var q = e.target.value.trim().toLowerCase();
            var shown = 0;
            cards.forEach(function(c) {
                c.hidden = q && c.getAttribute('data-search').indexOf(q) === -1;
                shown += c.hidden ? 0 : 1;
            });
            form.querySelector('[data-im-none]').hidden = shown > 0;
        });
        form.addEventListener('change', function(e) {
            if (e.target.matches('[data-im-pick]')) {
                bar.hidden = false;
                form.querySelector('[data-im-name]').textContent = e.target.getAttribute('data-name');
            }
            if (e.target.matches('[data-im-replace]')) {
                form.querySelector('[data-im-go]').classList.toggle('btn-danger', e.target.checked);
            }
        });
        form.addEventListener('submit', function() {
            form.querySelector('[data-im-go]').disabled = true;
            form.querySelector('[data-im-busy]').hidden = false;
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
