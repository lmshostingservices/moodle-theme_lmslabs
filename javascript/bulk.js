/**
 * Bulk user actions: selection count, "all matching", action options and the apply button.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var form = document.querySelector('[data-ll-bulk]');
        if (!form) {
            return;
        }
        var total = parseInt(form.getAttribute('data-total'), 10) || 0;
        var count = form.querySelector('[data-bk-count]');
        var none = form.querySelector('[data-bk-none-text]').textContent;
        var all = form.querySelector('[data-bk-all]');
        var go = form.querySelector('[data-bk-go]');
        var golabel = form.querySelector('[data-bk-golabel]');
        var pick = golabel.textContent;
        var apply = form.getAttribute('data-apply');
        var confirm = form.querySelector('[data-bk-confirm]');
        var selected = 0;

        var action = function() {
            var r = form.querySelector('input[name="action"]:checked');
            return r ? r.value : '';
        };
        var sync = function() {
            var n = all && all.checked ? total : selected;
            count.textContent = n ? form.getAttribute(n === 1 ? 'data-one' : 'data-many').replace('{n}', n) : none;
            form.querySelectorAll('[data-select-row]').forEach(function(cb) {
                cb.disabled = !!(all && all.checked);
            });
            var a = action();
            form.querySelectorAll('.ll-bact-opts').forEach(function(opts) {
                opts.hidden = opts.getAttribute('data-for').split(' ').indexOf(a) === -1;
            });
            form.querySelectorAll('.ll-bact').forEach(function(l) {
                l.classList.toggle('is-on', l.querySelector('input').checked);
            });
            var ok = n > 0 && a !== '' && (a !== 'delete' || (confirm && confirm.value.trim() === 'DELETE'));
            go.disabled = !ok;
            go.classList.toggle('btn-danger', a === 'delete');
            go.classList.toggle('btn-primary', a !== 'delete');
            golabel.textContent = n ? apply.replace('{n}', n) : pick;
        };
        form.addEventListener('ll:selection', function(e) {
            selected = e.detail.n;
            sync();
        });
        form.addEventListener('change', sync);
        if (confirm) {
            confirm.addEventListener('input', sync);
        }
        sync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
