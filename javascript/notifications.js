/**
 * Notifications page on Moodle's popup notification web services.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var ready = function(fn) {
        if (typeof window.require === 'function' && window.M && window.M.cfg) {
            fn();
        } else {
            window.setTimeout(function() {
                ready(fn);
            }, 50);
        }
    };

    var start = function() {
        var root = document.querySelector('[data-ll-notif]');
        if (!root) {
            return;
        }
        var cfg = JSON.parse(root.querySelector('[data-nt-config]').textContent);
        var S = cfg.str;
        var q = function(n) {
            return root.querySelector('[data-nt="' + n + '"]');
        };
        var state = {items: [], tab: 'all', offset: 0, done: false};
        var esc = function(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        var call = function(method, args) {
            return new Promise(function(resolve, reject) {
                window.require(['core/ajax'], function(Ajax) {
                    Ajax.call([{methodname: method, args: args}])[0].then(resolve).catch(reject);
                });
            });
        };
        var dayLabel = function(ts) {
            var d = new Date(ts * 1000);
            var now = new Date();
            var y = new Date(now);
            y.setDate(now.getDate() - 1);
            if (d.toDateString() === now.toDateString()) {
                return S.today;
            }
            if (d.toDateString() === y.toDateString()) {
                return S.yesterday;
            }
            return d.toLocaleDateString([], {weekday: 'long', day: 'numeric', month: 'long'});
        };
        var render = function() {
            var list = state.items.filter(function(n) {
                return state.tab === 'all' || !n.read;
            });
            var unread = state.items.filter(function(n) {
                return !n.read;
            }).length;
            q('count').textContent = unread || '';
            if (!list.length) {
                q('list').innerHTML = '<div class="ll-cal-emptyview"><b>✓</b><p>' + esc(state.tab === 'all' ? S.none : S.nounread) +
                    '</p></div>';
                return;
            }
            var html = '';
            var last = '';
            list.forEach(function(n) {
                var day = new Date(n.timecreated * 1000).toDateString();
                if (day !== last) {
                    html += '<h3 class="ll-nt-day">' + esc(dayLabel(n.timecreated)) + '</h3>';
                    last = day;
                }
                html += '<a class="ll-nt-row' + (n.read ? '' : ' is-unread') + '" href="' + esc(n.contexturl || '#') +
                    '" data-id="' + n.id + '"><span class="ll-nt-ico">' + (n.iconurl ? '<img src="' + esc(n.iconurl) +
                    '" alt="">' : '') + '</span><span class="ll-nt-text"><strong>' + esc(n.subject) + '</strong>' +
                    '<span>' + esc((n.smallmessage || '').replace(/\s+/g, ' ').slice(0, 180)) + '</span></span>' +
                    '<time>' + esc(new Date(n.timecreated * 1000).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})) +
                    '</time></a>';
            });
            if (!state.done) {
                html += '<button type="button" class="btn btn-secondary ll-nt-more" data-nt="more">' + esc(S.more) + '</button>';
            }
            q('list').innerHTML = html;
        };
        var load = function() {
            return call('message_popup_get_popup_notifications', {useridto: cfg.userid, newestfirst: true, limit: 40,
                offset: state.offset}).then(function(r) {
                state.items = state.items.concat(r.notifications || []);
                state.offset += 40;
                state.done = (r.notifications || []).length < 40;
                render();
            });
        };
        q('list').addEventListener('click', function(e) {
            if (e.target.closest('[data-nt="more"]')) {
                load();
                return;
            }
            var row = e.target.closest('[data-id]');
            if (!row) {
                return;
            }
            var n = state.items.filter(function(x) {
                return String(x.id) === row.getAttribute('data-id');
            })[0];
            if (n && !n.read) {
                e.preventDefault();
                call('core_message_mark_notification_read', {notificationid: n.id, timeread: Math.floor(Date.now() / 1000)})
                    .finally(function() {
                        window.location.href = row.getAttribute('href');
                    });
            }
        });
        root.querySelectorAll('[data-nt-tab]').forEach(function(t) {
            t.addEventListener('click', function() {
                root.querySelectorAll('[data-nt-tab]').forEach(function(x) {
                    x.classList.toggle('is-on', x === t);
                    x.setAttribute('aria-selected', x === t ? 'true' : 'false');
                });
                state.tab = t.getAttribute('data-nt-tab');
                render();
            });
        });
        q('markall').addEventListener('click', function() {
            call('core_message_mark_all_notifications_as_read', {useridto: cfg.userid, useridfrom: 0}).then(function() {
                state.items.forEach(function(n) {
                    n.read = true;
                });
                render();
                window.require(['core/toast'], function(Toast) {
                    Toast.add(S.marked);
                });
            });
        });
        load();
    };

    var boot = function() {
        ready(start);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
