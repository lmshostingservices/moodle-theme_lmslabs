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
        var ICON = cfg.icons || {};
        var classify = function(n) {
            var c = (n.component || '') + ' ' + (n.eventtype || '');
            var t = (n.subject || '') + ' ' + (n.smallmessage || '');
            if (/recertif|refresher|expir|due soon|overdue/i.test(c + ' ' + t)) {
                return {icon: 'rotate', tone: 'amber', label: S.k_recert};
            }
            if (/groupmanager|group access/i.test(c + ' ' + t)) {
                return {icon: 'users', tone: '', label: S.k_group};
            }
            if (/badge/i.test(c)) {
                return {icon: 'award', tone: 'green', label: S.k_badge};
            }
            if (/completed|congratulations|passed/i.test(t)) {
                return {icon: 'done', tone: 'green', label: S.k_done};
            }
            if (/assign/i.test(c)) {
                return {icon: 'file', tone: '', label: S.k_assign};
            }
            if (/quiz/i.test(c)) {
                return {icon: 'done', tone: '', label: S.k_quiz};
            }
            if (/forum/i.test(c)) {
                return {icon: 'chat', tone: '', label: S.k_forum};
            }
            if (/grade/i.test(c)) {
                return {icon: 'chart', tone: '', label: S.k_grade};
            }
            if (/enrol/i.test(c) || /welcome to/i.test(t)) {
                return {icon: 'cap', tone: '', label: S.k_enrol};
            }
            if (/mail|message/i.test(c)) {
                return {icon: 'mail', tone: '', label: S.k_message};
            }
            return {icon: 'bell', tone: '', label: S.k_other};
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
                q('list').innerHTML = '<div class="ll-nt-empty"><span class="ll-nt-ico" data-tone="green">' + (ICON.done || '') +
                    '</span><p>' + esc(state.tab === 'all' ? S.none : S.nounread) + '</p><span>' + esc(S.emptyhint) +
                    '</span></div>';
                return;
            }
            var counts = {};
            list.forEach(function(n) {
                var k = new Date(n.timecreated * 1000).toDateString();
                counts[k] = (counts[k] || 0) + 1;
            });
            var html = '';
            var last = '';
            list.forEach(function(n) {
                var day = new Date(n.timecreated * 1000).toDateString();
                if (day !== last) {
                    html += '<h3 class="ll-nt-day">' + esc(dayLabel(n.timecreated)) + ' <span>' + counts[day] + '</span></h3>';
                    last = day;
                }
                var k = classify(n);
                var tag = n.contexturl ? 'a' : 'div';
                var body = (n.smallmessage || '').replace(/\s+/g, ' ').trim();
                if (body === (n.subject || '').trim()) {
                    body = '';
                }
                html += '<' + tag + ' class="ll-nt-row' + (n.read ? '' : ' is-unread') + '"' +
                    (n.contexturl ? ' href="' + esc(n.contexturl) + '"' : '') + ' data-id="' + n.id + '">' +
                    '<span class="ll-nt-ico" data-tone="' + k.tone + '">' + (ICON[k.icon] || ICON.bell || '') + '</span>' +
                    '<span class="ll-nt-text"><strong>' + esc(n.subject) + '</strong>' +
                    (body ? '<span class="ll-nt-body">' + esc(body.slice(0, 200)) + '</span>' : '') +
                    '<span class="ll-nt-meta">' + esc(k.label) + '</span></span>' +
                    '<span class="ll-nt-side"><time>' + esc(new Date(n.timecreated * 1000).toLocaleTimeString([],
                        {hour: 'numeric', minute: '2-digit'})) + '</time>' +
                    (n.read ? '' : '<button type="button" class="ll-nt-read" data-nt-read title="' + esc(S.markread) +
                        '" aria-label="' + esc(S.markread) + '">' + (ICON.check || '') + '</button>') +
                    '</span></' + tag + '>';
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
            if (n && !n.read && e.target.closest('[data-nt-read]')) {
                e.preventDefault();
                e.stopPropagation();
                call('core_message_mark_notification_read', {notificationid: n.id, timeread: Math.floor(Date.now() / 1000)})
                    .then(function() {
                        n.read = true;
                        render();
                    });
                return;
            }
            if (n && !n.read) {
                e.preventDefault();
                call('core_message_mark_notification_read', {notificationid: n.id, timeread: Math.floor(Date.now() / 1000)})
                    .finally(function() {
                        if (row.getAttribute('href')) {
                            window.location.href = row.getAttribute('href');
                        } else {
                            n.read = true;
                            render();
                        }
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
