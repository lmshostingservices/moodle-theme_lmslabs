/**
 * Notification settings: save each switch instantly, channel-wide on/off, search.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var root = document.querySelector('[data-ll-np]');
        if (!root) {
            return;
        }
        var save = function(row) {
            var on = [];
            row.querySelectorAll('[data-ch]').forEach(function(cb) {
                if (cb.checked) {
                    on.push(cb.getAttribute('data-ch'));
                }
            });
            row.querySelectorAll('[data-ch-locked][data-on="1"]').forEach(function(l) {
                on.push(l.getAttribute('data-ch-locked'));
            });
            var body = new URLSearchParams({save: 1, key: row.getAttribute('data-key'), value: on.join(','),
                sesskey: root.getAttribute('data-sesskey')});
            row.classList.add('is-saving');
            return fetch(root.getAttribute('data-save'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                }).then(function() {
                    row.classList.remove('is-saving');
                    row.classList.add('is-saved');
                    window.setTimeout(function() {
                        row.classList.remove('is-saved');
                    }, 900);
                });
        };
        root.addEventListener('change', function(e) {
            var row = e.target.closest('[data-np-row]');
            if (row && e.target.matches('[data-ch]')) {
                save(row);
            }
        });
        root.querySelectorAll('[data-np-all]').forEach(function(b) {
            b.addEventListener('click', function() {
                var ch = b.getAttribute('data-np-all');
                var on = b.getAttribute('data-on') === '1';
                var jobs = [];
                root.querySelectorAll('[data-np-row]').forEach(function(row) {
                    var cb = row.querySelector('[data-ch="' + ch + '"]');
                    if (cb && cb.checked !== on) {
                        cb.checked = on;
                        jobs.push(save(row));
                    }
                });
                Promise.all(jobs).then(function() {
                    if (window.require) {
                        window.require(['core/toast'], function(Toast) {
                            Toast.add(b.closest('.ll-np-master').querySelector('span').textContent + ': ' + b.textContent);
                        });
                    }
                });
            });
        });
        var q = root.querySelector('[data-np-q]');
        q.addEventListener('input', function() {
            var term = q.value.trim().toLowerCase();
            root.querySelectorAll('[data-np-group]').forEach(function(g) {
                var shown = 0;
                g.querySelectorAll('[data-np-row]').forEach(function(row) {
                    var hit = !term || row.getAttribute('data-name').toLowerCase().indexOf(term) !== -1 ||
                        g.querySelector('summary').textContent.toLowerCase().indexOf(term) !== -1;
                    row.hidden = !hit;
                    shown += hit ? 1 : 0;
                });
                g.hidden = shown === 0;
                if (term) {
                    g.open = true;
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
