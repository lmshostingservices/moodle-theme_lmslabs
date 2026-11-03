/**
 * Course badges: create, switch on or off, award by hand.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-cb]');
        if (!root) {
            return;
        }
        var $ = function(s) {
            return root.querySelector(s);
        };
        var esc = function(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        var send = function(body) {
            body.set('courseid', root.getAttribute('data-course'));
            body.set('sesskey', root.getAttribute('data-sesskey'));
            return fetch(root.getAttribute('data-ajax'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                }).then(function(r) {
                    if (r.error || r.exception) {
                        throw new Error(r.error || r.message || 'Error');
                    }
                    return r;
                });
        };
        var post = function(action, params) {
            var b = new URLSearchParams(params || {});
            b.set('action', action);
            return send(b);
        };
        var toast = function(msg, error) {
            if (window.require) {
                window.require(['core/toast'], function(Toast) {
                    Toast.add(msg, {type: error ? 'danger' : 'success'});
                });
            }
        };
        var reload = function() {
            window.setTimeout(function() {
                window.location.reload();
            }, 700);
        };
        var modal = function(name, show) {
            var m = $('[data-cb-modal="' + name + '"]');
            if (m) {
                m.hidden = !show;
            }
        };
        var closeAll = function() {
            Array.prototype.forEach.call(root.querySelectorAll('[data-cb-modal]'), function(m) {
                m.hidden = true;
            });
        };
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAll();
            }
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-cb-modal]'), function(m) {
            m.addEventListener('click', function(e) {
                if (e.target === m) {
                    closeAll();
                }
            });
        });

        // Switch a badge on or off.
        root.addEventListener('change', function(e) {
            var sw = e.target.closest('[data-cb-switch]');
            if (!sw) {
                return;
            }
            post('status', {badgeid: sw.getAttribute('data-cb-switch'), on: sw.checked ? 1 : 0}).then(function(r) {
                toast(r.message);
                reload();
            }).catch(function(err) {
                sw.checked = !sw.checked;
                toast(err.message, true);
            });
        });

        // Create.
        var form = $('[data-cb-form]');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var err = $('[data-cb-error]');
                err.hidden = true;
                var body = new FormData(form);
                body.set('action', 'create');
                if (!form.querySelector('[name="live"]').checked) {
                    body.set('live', 0);
                }
                var btn = form.querySelector('[type="submit"]');
                btn.disabled = true;
                send(body).then(function(r) {
                    toast(r.message);
                    reload();
                }).catch(function(ex) {
                    btn.disabled = false;
                    err.textContent = ex.message;
                    err.hidden = false;
                });
            });
        }

        // Award by hand.
        var current = 0;
        var picked = {};
        var people = [];
        var draw = function() {
            var q = ($('[data-cb-find]').value || '').trim().toLowerCase();
            $('[data-cb-people]').innerHTML = people.filter(function(p) {
                return !q || (p.name + ' ' + p.email).toLowerCase().indexOf(q) !== -1;
            }).map(function(p) {
                return '<li><button type="button" data-cb-pick="' + p.id + '"' + (p.has ? ' disabled' : '') +
                    (picked[p.id] ? ' class="is-picked"' : '') + '><span class="ll-avatar ll-tint-' + p.tint + '">' +
                    esc(p.initials) + '</span><span>' + esc(p.name) + '<small class="ll-muted"> · ' + esc(p.email) +
                    (p.has ? ' · ' + esc(root.getAttribute('data-has') || '✓') : '') + '</small></span></button></li>';
            }).join('');
        };
        root.addEventListener('click', function(e) {
            var p = e.target.closest('[data-cb-pick]');
            if (p) {
                var id = p.getAttribute('data-cb-pick');
                if (picked[id]) {
                    delete picked[id];
                } else {
                    picked[id] = true;
                }
                draw();
                return;
            }
            var b = e.target.closest('[data-cb]');
            if (!b) {
                return;
            }
            var act = b.getAttribute('data-cb');
            if (act === 'opennew') {
                modal('new', true);
                form.querySelector('[name="name"]').focus();
            } else if (act === 'close') {
                closeAll();
            } else if (act === 'openaward') {
                current = b.getAttribute('data-badge');
                picked = {};
                $('[data-cb-awardtitle]').textContent = b.getAttribute('data-name');
                $('[data-cb-find]').value = '';
                modal('award', true);
                post('people', {badgeid: current}).then(function(r) {
                    people = r.people;
                    draw();
                }).catch(function(err) {
                    toast(err.message, true);
                });
            } else if (act === 'doaward') {
                var ids = Object.keys(picked);
                if (!ids.length) {
                    return;
                }
                post('award', {badgeid: current, users: ids.join(',')}).then(function(r) {
                    toast(r.message);
                    closeAll();
                    reload();
                }).catch(function(err) {
                    toast(err.message, true);
                });
            }
        });
        var find = $('[data-cb-find]');
        if (find) {
            find.addEventListener('input', draw);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
