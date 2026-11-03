/**
 * Tenants: open the form, fill it to edit, copy a sign-in link.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-tn]');
        if (!root) {
            return;
        }
        var modal = root.querySelector('[data-tn-modal]');
        var form = root.querySelector('[data-tn-form]');
        var colour = form.querySelector('[data-tn-color]');
        var useDefault = form.querySelector('[data-tn-usedefault]');
        var field = function(n) {
            return form.querySelector('[name="' + n + '"]');
        };
        var open = function(d) {
            d = d || {};
            form.reset();
            field('id').value = d.id || 0;
            field('name').value = d.name || '';
            field('cohortid').value = d.cohort || '';
            field('sitename').value = d.sitename || '';
            field('idnumber').value = d.idnumber || '';
            field('enabled').checked = d.id ? d.enabled === '1' : true;
            useDefault.checked = !d.color;
            if (d.color) {
                colour.value = d.color;
            }
            colour.disabled = useDefault.checked;
            form.querySelector('[data-tn-removewrap]').hidden = d.haslogo !== '1';
            root.querySelector('[data-tn-title]').textContent = d.id ? d.name : root.querySelector('[data-tn="new"]').textContent;
            modal.hidden = false;
            field('name').focus();
        };
        useDefault.addEventListener('change', function() {
            colour.disabled = useDefault.checked;
        });
        // A disabled colour field is not sent, so the tenant keeps the site colour.
        var close = function() {
            modal.hidden = true;
        };
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                close();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                close();
            }
        });
        root.addEventListener('click', function(e) {
            var b = e.target.closest('[data-tn]');
            if (!b) {
                return;
            }
            var act = b.getAttribute('data-tn');
            if (act === 'new') {
                open();
            } else if (act === 'edit') {
                open({id: b.getAttribute('data-id'), name: b.getAttribute('data-name'), idnumber: b.getAttribute('data-idnumber'),
                    cohort: b.getAttribute('data-cohort'), color: b.getAttribute('data-color'),
                    sitename: b.getAttribute('data-sitename'), enabled: b.getAttribute('data-enabled'),
                    haslogo: b.getAttribute('data-haslogo')});
            } else if (act === 'close') {
                close();
            } else if (act === 'copy') {
                var url = b.getAttribute('data-url');
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url);
                }
                b.classList.add('is-done');
                window.setTimeout(function() {
                    b.classList.remove('is-done');
                }, 1200);
            }
        });
        root.querySelectorAll('[data-tn-del]').forEach(function(f) {
            f.addEventListener('submit', function(e) {
                if (!window.confirm(f.querySelector('button').getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
