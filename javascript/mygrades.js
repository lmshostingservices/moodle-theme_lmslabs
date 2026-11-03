/**
 * Grades: filter the course cards.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var root = document.querySelector('[data-ll-grades]');
        var q = root ? root.querySelector('[data-gr-q]') : null;
        if (!q) {
            return;
        }
        q.addEventListener('input', function() {
            var term = q.value.trim().toLowerCase();
            var shown = 0;
            root.querySelectorAll('[data-gr-item]').forEach(function(c) {
                var on = !term || c.getAttribute('data-name').toLowerCase().indexOf(term) !== -1;
                c.hidden = !on;
                shown += on ? 1 : 0;
            });
            root.querySelector('[data-gr-none]').hidden = shown > 0;
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
