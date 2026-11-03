/**
 * Members manager for groups and cohorts.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var root = document.querySelector('[data-ll-members]');
    if (!root) {
        return;
    }
    var str = function(id, a) {
        var s = (window.M && M.util) ? M.util.get_string(id, 'theme_lmslabs', a) : id;
        return s;
    };
    var esc = function(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };
    var sides = {};
    ['members', 'candidates'].forEach(function(name) {
        var el = root.querySelector('[data-side="' + name + '"]');
        sides[name] = {
            el: el, list: el.querySelector('[data-list]'), count: el.querySelector('[data-count]'),
            search: el.querySelector('[data-search]'), all: el.querySelector('[data-all]'),
            selected: el.querySelector('[data-selected]'),
            button: el.querySelector('[data-act]'), timer: null
        };
    });

    var call = function(fields) {
        var body = new FormData();
        body.append('sesskey', root.getAttribute('data-sesskey'));
        body.append('type', root.getAttribute('data-type'));
        body.append('id', root.getAttribute('data-id'));
        Object.keys(fields).forEach(function(k) {
            body.append(k, fields[k]);
        });
        return fetch(root.getAttribute('data-url'), {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function(r) {
                return r.json();
            }).then(function(res) {
                if (!res || !res.ok) {
                    throw new Error(res && res.error ? res.error : 'fail');
                }
                return res;
            });
    };

    var render = function(name, data) {
        var side = sides[name];
        var q = side.search.value.trim();
        side.count.textContent = data.total;
        if (!data.users.length) {
            side.list.innerHTML = '<li class="ll-mempty">' + esc(q ? str('members_nomatch') : str('members_none')) + '</li>';
        } else {
            side.list.innerHTML = data.users.map(function(u) {
                return '<li><label class="ll-mrow">' +
                    '<input type="checkbox" value="' + u.id + '">' +
                    '<span class="ll-avatar ll-tint-' + u.tint + '">' + esc(u.initials) + '</span>' +
                    '<span class="ll-mrow-text"><strong>' + esc(u.name) + '</strong><span>' + esc(u.email) + '</span></span>' +
                    '</label></li>';
            }).join('') + (data.total > data.users.length ?
                '<li class="ll-mempty">' + esc(str('members_more', data.total - data.users.length)) + '</li>' : '');
        }
        side.all.checked = false;
        sync(name);
    };
    var sync = function(name) {
        var side = sides[name];
        var boxes = side.list.querySelectorAll('input[type=checkbox]');
        var n = side.list.querySelectorAll('input:checked').length;
        side.button.disabled = n === 0;
        side.selected.textContent = n ? str('members_selected', n) : '';
        side.all.checked = boxes.length > 0 && n === boxes.length;
        side.list.querySelectorAll('.ll-mrow').forEach(function(row) {
            row.classList.toggle('is-checked', row.querySelector('input').checked);
        });
    };
    var load = function(name) {
        return call({action: 'list', which: name, q: sides[name].search.value}).then(function(res) {
            render(name, res[name]);
        });
    };
    var loadBoth = function() {
        return Promise.all([load('members'), load('candidates')]);
    };

    Object.keys(sides).forEach(function(name) {
        var side = sides[name];
        side.search.addEventListener('input', function() {
            clearTimeout(side.timer);
            side.timer = setTimeout(function() {
                load(name);
            }, 220);
        });
        side.list.addEventListener('change', function() {
            sync(name);
        });
        side.all.addEventListener('change', function() {
            side.list.querySelectorAll('input[type=checkbox]').forEach(function(b) {
                b.checked = side.all.checked;
            });
            sync(name);
        });
        side.button.addEventListener('click', function() {
            var ids = Array.prototype.map.call(side.list.querySelectorAll('input:checked'), function(b) {
                return parseInt(b.value, 10);
            });
            if (!ids.length) {
                return;
            }
            var action = side.button.getAttribute('data-act');
            side.button.disabled = true;
            call({action: action, users: JSON.stringify(ids)}).then(function(res) {
                toast(str(action === 'add' ? 'members_added' : 'members_removed', res.count));
                return loadBoth();
            }).catch(function() {
                toast(str('savefailed'), true);
                sync(name);
            });
        });
    });

    var toast = function(message, error) {
        if (window.require) {
            window.require(['core/toast'], function(Toast) {
                Toast.add(message, {type: error ? 'danger' : 'success'});
            });
        }
    };
    loadBoth();
})();
