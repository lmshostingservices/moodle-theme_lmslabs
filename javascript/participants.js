/**
 * Participants: filter, select, bulk actions and the enrol dialog.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-pt]');
        if (!root) {
            return;
        }
        var $ = function(sel) {
            return root.querySelector(sel);
        };
        var all = function(sel) {
            return Array.prototype.slice.call(root.querySelectorAll(sel));
        };
        var esc = function(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        var post = function(action, params) {
            var body = new URLSearchParams(params || {});
            body.set('action', action);
            body.set('courseid', root.getAttribute('data-course'));
            body.set('sesskey', root.getAttribute('data-sesskey'));
            return fetch(root.getAttribute('data-ajax'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                }).then(function(r) {
                    if (r.error) {
                        throw new Error(r.error);
                    }
                    return r;
                });
        };
        var toast = function(msg, error) {
            if (window.require) {
                window.require(['core/toast'], function(Toast) {
                    Toast.add(msg, {type: error ? 'danger' : 'success'});
                });
            }
        };

        // ---------- Filters ----------
        var filters = {status: '', role: '', group: '', q: ''};
        var apply = function() {
            var shown = 0;
            all('[data-pt-row]').forEach(function(tr) {
                var on = (!filters.status || tr.getAttribute('data-status') === filters.status) &&
                    (!filters.role || tr.getAttribute('data-roles').indexOf(' ' + filters.role + ' ') !== -1) &&
                    (!filters.group || tr.getAttribute('data-groups').indexOf(' ' + filters.group + ' ') !== -1) &&
                    (!filters.q || tr.getAttribute('data-name').toLowerCase().indexOf(filters.q) !== -1);
                tr.hidden = !on;
                shown += on ? 1 : 0;
            });
            var none = $('[data-pt-none]');
            if (none) {
                none.hidden = shown > 0 || !all('[data-pt-row]').length;
            }
        };
        all('[data-pt-status]').forEach(function(b) {
            b.addEventListener('click', function() {
                all('[data-pt-status]').forEach(function(x) {
                    x.classList.toggle('is-on', x === b);
                });
                filters.status = b.getAttribute('data-pt-status');
                apply();
            });
        });
        $('[data-pt-q]').addEventListener('input', function(e) {
            filters.q = e.target.value.trim().toLowerCase();
            apply();
        });
        $('[data-pt-role]').addEventListener('change', function(e) {
            filters.role = e.target.value;
            apply();
        });
        if ($('[data-pt-group]')) {
            $('[data-pt-group]').addEventListener('change', function(e) {
                filters.group = e.target.value;
                apply();
            });
        }

        // ---------- Selection ----------
        var bar = $('[data-pt-bulkbar]');
        var selected = function() {
            return all('[data-pt-check]:checked').filter(function(cb) {
                return !cb.closest('tr').hidden;
            }).map(function(cb) {
                return cb.value;
            });
        };
        var sync = function() {
            if (!bar) {
                return;
            }
            var n = selected().length;
            bar.hidden = n === 0;
            $('[data-pt-count]').textContent = (n === 1 ? root.getAttribute('data-one') : root.getAttribute('data-many'))
                .replace('{n}', n);
            all('[data-pt-row]').forEach(function(tr) {
                var cb = tr.querySelector('[data-pt-check]');
                tr.classList.toggle('is-selected', !!(cb && cb.checked));
            });
        };
        root.addEventListener('change', function(e) {
            if (e.target.matches('[data-pt-all]')) {
                all('[data-pt-row]').forEach(function(tr) {
                    if (!tr.hidden) {
                        tr.querySelector('[data-pt-check]').checked = e.target.checked;
                    }
                });
            }
            if (e.target.matches('[data-pt-check], [data-pt-all]')) {
                sync();
            }
        });

        // ---------- Modals ----------
        var openModal = function(name) {
            var m = root.querySelector('[data-pt-modal="' + name + '"]');
            if (m) {
                m.hidden = false;
                var f = m.querySelector('input[type="search"], textarea');
                if (f) {
                    f.focus();
                }
            }
        };
        var closeModals = function() {
            all('[data-pt-modal]').forEach(function(m) {
                m.hidden = true;
            });
        };
        all('[data-pt-modal]').forEach(function(m) {
            m.addEventListener('click', function(e) {
                if (e.target === m) {
                    closeModals();
                }
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModals();
            }
        });

        // ---------- Enrol ----------
        var picked = {};
        var chips = $('[data-pt-chips]');
        var renderChips = function() {
            if (!chips) {
                return;
            }
            var ids = Object.keys(picked);
            chips.hidden = !ids.length;
            chips.innerHTML = ids.map(function(id) {
                return '<button type="button" class="ll-chip-tag" data-unpick="' + id + '">' + esc(picked[id]) + '</button>';
            }).join('');
        };
        var timer = null;
        var search = $('[data-pt-search]');
        if (search) {
            search.addEventListener('input', function() {
                window.clearTimeout(timer);
                timer = window.setTimeout(function() {
                    post('search', {q: search.value}).then(function(r) {
                        $('[data-pt-results]').innerHTML = r.people.map(function(p) {
                            return '<li><button type="button" data-pick="' + p.id + '" data-name="' + esc(p.name) + '">' +
                                '<span class="ll-avatar ll-tint-' + p.tint + '">' + esc(p.initials) + '</span><span>' + esc(p.name) +
                                '<small class="ll-muted"> · ' + esc(p.email) + '</small></span></button></li>';
                        }).join('');
                    });
                }, 220);
            });
            root.addEventListener('click', function(e) {
                var p = e.target.closest('[data-pick]');
                if (p) {
                    picked[p.getAttribute('data-pick')] = p.getAttribute('data-name');
                    p.closest('li').remove();
                    renderChips();
                    search.focus();
                }
                var u = e.target.closest('[data-unpick]');
                if (u) {
                    delete picked[u.getAttribute('data-unpick')];
                    renderChips();
                }
            });
        }

        // ---------- Actions ----------
        root.addEventListener('click', function(e) {
            var b = e.target.closest('[data-pt]');
            if (!b) {
                return;
            }
            var act = b.getAttribute('data-pt');
            if (act === 'close') {
                closeModals();
            } else if (act === 'openenrol') {
                openModal('enrol');
            } else if (act === 'openmsg') {
                openModal('msg');
            } else if (act === 'clear') {
                all('[data-pt-check], [data-pt-all]').forEach(function(cb) {
                    cb.checked = false;
                });
                sync();
            } else if (act === 'enrol') {
                post('enrol', {users: Object.keys(picked).join(','), roleid: $('[data-pt-roleid]').value,
                    paste: $('[data-pt-paste]').value}).then(function(r) {
                    toast(r.message);
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 600);
                }).catch(function(err) {
                    toast(err.message, true);
                });
            } else if (act === 'send') {
                var text = $('[data-pt-msg]').value.trim();
                if (!text) {
                    return;
                }
                post('message', {users: selected().join(','), text: text}).then(function(r) {
                    closeModals();
                    $('[data-pt-msg]').value = '';
                    toast(r.message);
                }).catch(function(err) {
                    toast(err.message, true);
                });
            } else if (act === 'group') {
                post('group', {users: selected().join(','), groupid: $('[data-pt-groupsel]').value}).then(function(r) {
                    toast(r.message);
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 600);
                });
            } else if (act === 'unenrol') {
                if (!b.classList.contains('is-armed')) {
                    b.classList.add('is-armed');
                    var label = b.querySelector('span');
                    var original = label.textContent;
                    label.textContent = b.getAttribute('data-confirm');
                    window.setTimeout(function() {
                        b.classList.remove('is-armed');
                        label.textContent = original;
                    }, 4000);
                    return;
                }
                post('unenrol', {users: selected().join(',')}).then(function(r) {
                    toast(r.message);
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 600);
                });
            } else if (act === 'csv') {
                var ids = selected();
                var lines = [['Name', 'Email', 'Roles', 'Groups', 'Progress', 'Last access']];
                all('[data-pt-row]').forEach(function(tr) {
                    if (ids.indexOf(tr.getAttribute('data-id')) === -1) {
                        return;
                    }
                    var cells = tr.querySelectorAll('td');
                    var text = function(sel) {
                        var el = tr.querySelector(sel);
                        return el ? el.textContent.trim().replace(/\s+/g, ' ') : '';
                    };
                    lines.push([text('.ll-person-text strong'), text('.ll-person-text span'),
                        cells[2] ? cells[2].textContent.trim().replace(/\s+/g, ', ') : '', '', text('.ll-ib-prog b'),
                        text('.ll-status')]);
                });
                var csv = lines.map(function(l) {
                    return l.map(function(v) {
                        return '"' + String(v).replace(/"/g, '""') + '"';
                    }).join(',');
                }).join('\n');
                var a = document.createElement('a');
                a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
                a.download = 'participants.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
            }
        });
        apply();
        sync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
