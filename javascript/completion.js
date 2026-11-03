/**
 * Course completion: show the options for the chosen rule; select all; two-step unlock.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var form = document.querySelector('[data-ll-cp]');
        var unlock = document.querySelector('[data-cp-unlock]');
        if (unlock) {
            unlock.addEventListener('click', function(e) {
                if (!unlock.classList.contains('is-armed')) {
                    e.preventDefault();
                    unlock.classList.add('is-armed');
                    unlock.textContent = unlock.getAttribute('data-confirm');
                }
            });
        }
        if (!form) {
            return;
        }
        var sync = function() {
            var r = form.querySelector('input[name="rule"]:checked');
            var rule = r ? r.value : 'all';
            form.querySelectorAll('.ll-cp-extra').forEach(function(x) {
                x.hidden = x.getAttribute('data-for') !== rule;
            });
            form.querySelectorAll('.ll-cp-rule').forEach(function(l) {
                l.classList.toggle('is-on', l.querySelector('input').checked);
            });
        };
        form.addEventListener('change', sync);
        var all = form.querySelector('[data-cp-all]');
        if (all) {
            all.addEventListener('click', function() {
                var boxes = form.querySelectorAll('input[name="cms[]"]');
                var any = Array.prototype.some.call(boxes, function(b) {
                    return !b.checked;
                });
                boxes.forEach(function(b) {
                    b.checked = any;
                });
            });
        }
        sync();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
