/**
 * Overrides: pick people or a group, set what differs, apply.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-ov]');
        if (!root) {
            return;
        }
        var $ = function(s) {
            return root.querySelector(s);
        };
        var all = function(s) {
            return Array.prototype.slice.call(root.querySelectorAll(s));
        };
        var esc = function(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        var post = function(action, params) {
            var b = new URLSearchParams(params || {});
            b.set('action', action);
            b.set('cmid', root.getAttribute('data-cmid'));
            b.set('sesskey', root.getAttribute('data-sesskey'));
            return fetch(root.getAttribute('data-ajax'), {method: 'POST', body: b, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                }).then(function(r) {
                    if (r.error || r.exception) {
                        throw new Error(r.error || r.message || 'Error');
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
        var sel = $('[data-ov-activity]');
        if (sel) {
            sel.addEventListener('change', function() {
                if (sel.value) {
                    window.location.href = sel.value;
                }
            });
        }
        if (!root.getAttribute('data-cmid')) {
            return;
        }

        var people = [];
        var picked = {};
        var mode = 'people';
        var field = function(n) {
            return $('[data-ov-field="' + n + '"]');
        };
        var update = function() {
            var n = Object.keys(picked).length;
            var btn = $('[data-ov="save"]');
            var label = $('[data-ov-applylabel]');
            var ok = mode === 'group' ? !!$('[data-ov-groups]').value : n > 0;
            btn.disabled = !ok;
            label.textContent = mode === 'group' ? label.getAttribute('data-group')
                : (n === 0 ? label.textContent : (n === 1 ? label.getAttribute('data-one') : label.getAttribute('data-many').replace('{n}', n)));
            if (mode === 'people' && n === 0) {
                label.textContent = root.getAttribute('data-none') || label.textContent;
            }
        };
        var noneText = $('[data-ov-applylabel]').textContent;
        root.setAttribute('data-none', noneText);
        var draw = function() {
            var q = ($('[data-ov-find]').value || '').trim().toLowerCase();
            $('[data-ov-people]').innerHTML = people.filter(function(p) {
                return !q || (p.name + ' ' + p.email).toLowerCase().indexOf(q) !== -1;
            }).map(function(p) {
                return '<li><button type="button" data-ov-pick="' + p.id + '"' + (picked[p.id] ? ' class="is-picked"' : '') +
                    '><span class="ll-avatar ll-tint-' + p.tint + '">' + esc(p.initials) + '</span><span>' + esc(p.name) +
                    '<small class="ll-muted"> · ' + esc(p.email) + '</small></span></button></li>';
            }).join('');
            update();
        };
        post('people').then(function(r) {
            people = r.people;
            $('[data-ov-groups]').innerHTML = r.groups.length ? r.groups.map(function(g) {
                return '<option value="' + g.id + '">' + esc(g.name) + '</option>';
            }).join('') : '<option value="">-</option>';
            draw();
            // Arriving from a row's edit button or a link with a person pre-picked.
            var pre = new URLSearchParams(window.location.search).get('user');
            if (pre && people.some(function(p) {
                return String(p.id) === pre;
            })) {
                picked[pre] = true;
                draw();
            }
        }).catch(function(err) {
            toast(err.message, true);
        });
        $('[data-ov-find]').addEventListener('input', draw);
        $('[data-ov-groups]').addEventListener('change', update);

        // Quick buttons work from what the activity has now, or what is already typed.
        var shift = function(value, minutes) {
            // Time-zone neutral: the field is already in the Moodle time zone.
            var d = new Date(value + ':00Z');
            d.setUTCMinutes(d.getUTCMinutes() + minutes);
            return d.toISOString().slice(0, 16);
        };
        var current = function(n) {
            var f = field(n);
            return f ? (f.value || f.getAttribute('data-base')) : '';
        };
        var quick = {
            duedate: function(m) {
                ['duedate', 'cutoffdate'].forEach(function(n) {
                    var base = current(n);
                    if (base && field(n)) {
                        field(n).value = shift(base, m);
                    }
                });
            },
            timeclose: function(m) {
                var base = current('timeclose');
                if (base) {
                    field('timeclose').value = shift(base, m);
                }
            },
            timelimit: function(m) {
                var base = parseInt(current('timelimit'), 10) || 0;
                if (base) {
                    field('timelimit').value = base + m;
                }
            },
            timelimitpct: function(p) {
                var base = parseInt(current('timelimit'), 10) || 0;
                if (base) {
                    field('timelimit').value = Math.ceil(base * (100 + p) / 100);
                }
            },
            attempts: function(m) {
                var base = parseInt(current('attempts'), 10) || 0;
                if (base) {
                    field('attempts').value = base + m;
                }
            }
        };
        var showError = function(msg) {
            var e = $('[data-ov-error]');
            e.textContent = msg || '';
            e.hidden = !msg;
        };

        root.addEventListener('click', function(e) {
            var pk = e.target.closest('[data-ov-pick]');
            if (pk) {
                var id = pk.getAttribute('data-ov-pick');
                if (picked[id]) {
                    delete picked[id];
                } else {
                    picked[id] = true;
                }
                draw();
                return;
            }
            var md = e.target.closest('[data-ov-mode]');
            if (md) {
                mode = md.getAttribute('data-ov-mode');
                all('[data-ov-mode]').forEach(function(b) {
                    b.classList.toggle('is-on', b === md);
                });
                $('[data-ov-peoplepane]').hidden = mode !== 'people';
                $('[data-ov-grouppane]').hidden = mode !== 'group';
                update();
                return;
            }
            var q = e.target.closest('[data-ov-quick]');
            if (q) {
                var fn = quick[q.getAttribute('data-ov-quick')];
                if (fn) {
                    fn(parseInt(q.getAttribute('data-delta'), 10));
                }
                return;
            }
            var b = e.target.closest('[data-ov]');
            if (!b) {
                return;
            }
            if (b.getAttribute('data-ov') === 'save') {
                var params = {};
                all('[data-ov-field]').forEach(function(f) {
                    if (f.value !== '') {
                        params[f.getAttribute('data-ov-field')] = f.value;
                    }
                });
                if (!Object.keys(params).length) {
                    showError(root.getAttribute('data-nothing') || '');
                    return;
                }
                if (mode === 'group') {
                    params.groupid = $('[data-ov-groups]').value;
                } else {
                    params.users = Object.keys(picked).join(',');
                }
                showError('');
                b.disabled = true;
                post('save', params).then(function(r) {
                    toast(r.message);
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 600);
                }).catch(function(err) {
                    b.disabled = false;
                    showError(err.message);
                });
            } else if (b.getAttribute('data-ov') === 'remove') {
                post('delete', {ids: b.getAttribute('data-id')}).then(function(r) {
                    toast(r.message);
                    var row = b.closest('[data-ov-row]');
                    if (row) {
                        row.remove();
                    }
                }).catch(function(err) {
                    toast(err.message, true);
                });
            }
        });
        update();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
