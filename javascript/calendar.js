/**
 * Premium SaaS calendar: agenda, week and month views, course filters, event details with
 * "add to Google Calendar" and .ics, personal events, subscribe and reminders.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-cal]');
        if (!root) {
            return;
        }
        var cfg = JSON.parse(root.querySelector('[data-cal-config]').textContent);
        var S = cfg.str;
        var q = function(n) {
            return root.querySelector('[data-cal="' + n + '"]');
        };
        var store = {
            get: function(k, d) {
                try {
                    return window.localStorage.getItem(k) || d;
                } catch (e) {
                    return d;
                }
            },
            set: function(k, v) {
                try {
                    window.localStorage.setItem(k, v);
                } catch (e) {
                    // Not remembered.
                }
            }
        };
        var state = {
            view: store.get('ll-cal-view', window.innerWidth < 768 ? 'agenda' : 'month'),
            date: new Date(),
            events: [],
            hidden: JSON.parse(store.get('ll-cal-hidden', '[]'))
        };
        state.date.setHours(0, 0, 0, 0);

        var esc = function(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        var post = function(action, params) {
            var body = new URLSearchParams(params || {});
            body.set('action', action);
            body.set('sesskey', cfg.sesskey);
            return fetch(cfg.ajax, {method: 'POST', body: body, credentials: 'same-origin'}).then(function(r) {
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
        var day0 = function(d) {
            var x = new Date(d);
            x.setHours(0, 0, 0, 0);
            return x;
        };
        var addDays = function(d, n) {
            var x = new Date(d);
            x.setDate(x.getDate() + n);
            return x;
        };
        var sameDay = function(a, b) {
            return a.toDateString() === b.toDateString();
        };
        var weekStart = function(d) {
            var x = day0(d);
            var diff = (x.getDay() - cfg.weekstart + 7) % 7;
            return addDays(x, -diff);
        };
        var time = function(ts) {
            return new Date(ts * 1000).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
        };
        var visible = function(e) {
            var key = e.kind === 'personal' ? 0 : e.courseid;
            return state.hidden.indexOf(key) === -1;
        };

        // ---------- Range and loading ----------
        var range = function() {
            var from;
            var to;
            if (state.view === 'month') {
                var first = new Date(state.date.getFullYear(), state.date.getMonth(), 1);
                from = weekStart(first);
                to = addDays(from, 42);
            } else if (state.view === 'week') {
                from = weekStart(state.date);
                to = addDays(from, 7);
            } else {
                from = day0(state.date);
                to = addDays(from, 42);
            }
            return [from, to];
        };
        var load = function() {
            var r = range();
            q('body').classList.add('is-loading');
            return post('events', {from: Math.floor(r[0] / 1000), to: Math.floor(r[1] / 1000)}).then(function(res) {
                state.events = res.events;
                q('body').classList.remove('is-loading');
                render();
            });
        };

        // ---------- Rendering ----------
        var chip = function(e, compact) {
            return '<button type="button" class="ll-cal-ev ll-cal-c' + e.colour + (e.overdue ? ' is-overdue' : '') +
                (e.done ? ' is-done' : '') + (e.kind === 'due' ? ' is-due' : '') + '" data-ev="' + e.id + '">' +
                (compact ? '' : '<time>' + esc(e.duration >= 86400 ? S.allday : time(e.start)) + '</time>') +
                '<span>' + esc(e.name) + '</span></button>';
        };
        var renderMonth = function() {
            var r = range();
            var month = state.date.getMonth();
            var today = day0(new Date());
            var html = '<div class="ll-cal-month"><div class="ll-cal-dow">';
            for (var i = 0; i < 7; i++) {
                html += '<span>' + esc(addDays(r[0], i).toLocaleDateString([], {weekday: 'short'})) + '</span>';
            }
            html += '</div><div class="ll-cal-grid">';
            for (var d = 0; d < 42; d++) {
                var day = addDays(r[0], d);
                var evs = state.events.filter(function(e) {
                    return visible(e) && sameDay(new Date(e.start * 1000), day);
                });
                var shown = evs.slice(0, 3);
                html += '<div class="ll-cal-cell' + (day.getMonth() !== month ? ' is-out' : '') +
                    (sameDay(day, today) ? ' is-today' : '') + '" data-day="' + day.getTime() + '">' +
                    '<span class="ll-cal-num">' + day.getDate() + '</span>' +
                    shown.map(function(e) {
                        return chip(e, false);
                    }).join('') +
                    (evs.length > 3 ? '<button type="button" class="ll-cal-more" data-goday="' + day.getTime() + '">' +
                        esc(S.more.replace('{n}', evs.length - 3)) + '</button>' : '') + '</div>';
            }
            return html + '</div></div>';
        };
        var renderWeek = function() {
            var from = weekStart(state.date);
            var today = day0(new Date());
            var html = '<div class="ll-cal-week">';
            for (var i = 0; i < 7; i++) {
                var day = addDays(from, i);
                var evs = state.events.filter(function(e) {
                    return visible(e) && sameDay(new Date(e.start * 1000), day);
                });
                html += '<div class="ll-cal-wday' + (sameDay(day, today) ? ' is-today' : '') + '">' +
                    '<header><span>' + esc(day.toLocaleDateString([], {weekday: 'short'})) + '</span><b>' + day.getDate() +
                    '</b></header><div class="ll-cal-wlist">' +
                    (evs.length ? evs.map(function(e) {
                        return chip(e, false);
                    }).join('') : '<p class="ll-cal-free">' + esc(S.nothingday) + '</p>') + '</div></div>';
            }
            return html + '</div>';
        };
        var renderAgenda = function() {
            var evs = state.events.filter(visible);
            if (!evs.length) {
                return '<div class="ll-cal-emptyview"><b>✓</b><p>' + esc(S.nothing) + '</p></div>';
            }
            var html = '<div class="ll-cal-agenda">';
            var last = '';
            var today = day0(new Date());
            evs.forEach(function(e) {
                var d = new Date(e.start * 1000);
                var key = d.toDateString();
                if (key !== last) {
                    if (last) {
                        html += '</div></section>';
                    }
                    var label = sameDay(d, today) ? S.today : (sameDay(d, addDays(today, 1)) ? S.tomorrow : '');
                    html += '<section class="ll-cal-aday' + (sameDay(d, today) ? ' is-today' : '') + '"><header><b>' +
                        d.getDate() + '</b><span>' + esc(d.toLocaleDateString([], {weekday: 'long'})) + '<small>' +
                        esc(label || d.toLocaleDateString([], {month: 'long', year: 'numeric'})) + '</small></span></header><div>';
                    last = key;
                }
                html += '<button type="button" class="ll-cal-arow" data-ev="' + e.id + '"><i class="ll-cal-c' + e.colour + '"></i>' +
                    '<time>' + esc(e.duration >= 86400 ? S.allday : time(e.start)) + '</time><span class="ll-cal-arowtext"><strong>' +
                    esc(e.name) + '</strong><span>' + esc(e.course || S[e.kind]) + '</span></span>' +
                    (e.done ? '<em class="ll-cal-tag is-done">' + esc(S.done) + '</em>' : '') +
                    (e.overdue ? '<em class="ll-cal-tag is-overdue">' + esc(S.overdue) + '</em>' : '') +
                    (!e.done && !e.overdue ? '<em class="ll-cal-tag">' + esc(S[e.kind]) + '</em>' : '') + '</button>';
            });
            return html + '</div></section></div>';
        };
        var renderUpcoming = function() {
            // Next 14 days of due dates plus anything overdue, grouped.
            var now = Date.now() / 1000;
            var limit = now + 14 * 86400;
            post('events', {from: Math.floor(now - 14 * 86400), to: Math.floor(limit)}).then(function(res) {
                var today = day0(new Date());
                var groups = {overdue: [], today: [], tomorrow: [], thisweek: [], later: []};
                res.events.filter(function(e) {
                    return e.kind === 'due' || e.start >= now;
                }).forEach(function(e) {
                    var d = day0(new Date(e.start * 1000));
                    if (e.overdue) {
                        groups.overdue.push(e);
                    } else if (e.start < now) {
                        return;
                    } else if (sameDay(d, today)) {
                        groups.today.push(e);
                    } else if (sameDay(d, addDays(today, 1))) {
                        groups.tomorrow.push(e);
                    } else if (d < addDays(today, 7)) {
                        groups.thisweek.push(e);
                    } else {
                        groups.later.push(e);
                    }
                });
                var html = '';
                ['overdue', 'today', 'tomorrow', 'thisweek', 'later'].forEach(function(k) {
                    if (!groups[k].length) {
                        return;
                    }
                    html += '<div class="ll-cal-ugroup is-' + k + '"><h4>' + esc(S[k]) + '<b>' + groups[k].length + '</b></h4>' +
                        groups[k].slice(0, 6).map(function(e) {
                            return '<button type="button" class="ll-cal-urow" data-ev="' + e.id + '" data-evjson=\'' +
                                esc(JSON.stringify(e)) + '\'><i class="ll-cal-c' + e.colour + '"></i><span><strong>' +
                                esc(e.name) + '</strong><small>' + esc(new Date(e.start * 1000).toLocaleDateString([],
                                {weekday: 'short', day: 'numeric', month: 'short'}) + ' · ' + time(e.start)) + '</small></span></button>';
                        }).join('') + '</div>';
                });
                q('upcoming').innerHTML = html || '<p class="ll-cal-allclear">' + esc(S.allclear) + '</p>';
            });
        };
        var render = function() {
            var title;
            if (state.view === 'month') {
                title = state.date.toLocaleDateString([], {month: 'long', year: 'numeric'});
            } else if (state.view === 'week') {
                var f = weekStart(state.date);
                var l = addDays(f, 6);
                title = f.toLocaleDateString([], {day: 'numeric', month: 'short'}) + ' – ' +
                    l.toLocaleDateString([], {day: 'numeric', month: 'short', year: 'numeric'});
            } else {
                title = state.date.toLocaleDateString([], {day: 'numeric', month: 'long', year: 'numeric'});
            }
            q('title').textContent = title;
            root.querySelectorAll('[data-cal-view]').forEach(function(b) {
                var on = b.getAttribute('data-cal-view') === state.view;
                b.classList.toggle('is-on', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            q('body').innerHTML = state.view === 'month' ? renderMonth() : (state.view === 'week' ? renderWeek() : renderAgenda());
        };

        // ---------- Event detail ----------
        var gcal = function(e) {
            var fmt = function(ts) {
                return new Date(ts * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
            };
            var end = e.start + (e.duration || 1800);
            return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(e.name) +
                '&dates=' + fmt(e.start) + '/' + fmt(end) + '&details=' + encodeURIComponent((e.course ? e.course + '\n' : '') +
                (e.url || '') + (e.description ? '\n\n' + e.description : ''));
        };
        var ics = function(e) {
            var fmt = function(ts) {
                return new Date(ts * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
            };
            var clean = function(s) {
                return String(s || '').replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n');
            };
            var body = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LMS Labs//Premium SaaS//EN', 'BEGIN:VEVENT',
                'UID:moodle-' + e.id + '@' + window.location.host, 'DTSTAMP:' + fmt(Date.now() / 1000),
                'DTSTART:' + fmt(e.start), 'DTEND:' + fmt(e.start + (e.duration || 1800)), 'SUMMARY:' + clean(e.name),
                'DESCRIPTION:' + clean((e.course ? e.course + '\n' : '') + (e.description || '')),
                e.url ? 'URL:' + e.url : '', 'BEGIN:VALARM', 'TRIGGER:-PT24H', 'ACTION:DISPLAY', 'DESCRIPTION:' + clean(e.name),
                'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'].filter(Boolean).join('\r\n');
            return 'data:text/calendar;charset=utf-8,' + encodeURIComponent(body);
        };
        var openEvent = function(e) {
            if (!e) {
                return;
            }
            var d = new Date(e.start * 1000);
            q('detail').innerHTML = '<header class="ll-cal-dhead ll-cal-c' + e.colour + '"><span>' + esc(S[e.kind]) + '</span>' +
                '<button type="button" class="ll-iconbtn" data-cal="dclose" aria-label="Close">×</button></header>' +
                '<div class="ll-cal-dbody"><h3>' + esc(e.name) + '</h3>' +
                '<p class="ll-cal-dwhen">' + esc(d.toLocaleDateString([], {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'})) +
                ' · ' + esc(e.duration >= 86400 ? S.allday : time(e.start) + (e.duration ? ' – ' + time(e.start + e.duration) : '')) + '</p>' +
                (e.course ? '<p class="ll-cal-dcourse"><i class="ll-cal-c' + e.colour + '"></i>' + esc(e.course) + '</p>' : '') +
                (e.overdue ? '<p class="ll-cal-tag is-overdue">' + esc(S.overdue) + '</p>' : '') +
                (e.done ? '<p class="ll-cal-tag is-done">' + esc(S.done) + '</p>' : '') +
                (e.description ? '<p class="ll-cal-ddesc">' + esc(e.description) + '</p>' : '') +
                '<div class="ll-cal-dactions">' +
                (e.url ? '<a class="btn btn-primary" href="' + esc(e.url) + '">' + esc(S.open) + '</a>' : '') +
                '<a class="btn btn-secondary" target="_blank" rel="noopener" href="' + esc(gcal(e)) + '">' + esc(S.google) + '</a>' +
                '<a class="btn btn-secondary" download="' + esc(e.name.replace(/[^\w ]+/g, '').slice(0, 40) || 'event') + '.ics" href="' +
                ics(e) + '">' + esc(S.ics) + '</a>' +
                (e.mine ? '<button type="button" class="ll-linkbtn ll-danger" data-cal="ddelete" data-id="' + e.id + '">' +
                    esc(S.delete) + '</button>' : '') + '</div></div>';
            q('drawer').hidden = false;
            q('detail').querySelector('[data-cal="dclose"]').focus();
        };
        var findEvent = function(el) {
            if (el.hasAttribute('data-evjson')) {
                return JSON.parse(el.getAttribute('data-evjson'));
            }
            var id = parseInt(el.getAttribute('data-ev'), 10);
            return state.events.filter(function(e) {
                return e.id === id;
            })[0];
        };
        root.addEventListener('click', function(ev) {
            var t = ev.target;
            var e = t.closest('[data-ev]');
            if (e) {
                openEvent(findEvent(e));
                return;
            }
            var g = t.closest('[data-goday]');
            if (g) {
                state.date = new Date(parseInt(g.getAttribute('data-goday'), 10));
                setView('week');
                return;
            }
            if (t.closest('[data-cal="dclose"]') || t === q('drawer')) {
                q('drawer').hidden = true;
                return;
            }
            var del = t.closest('[data-cal="ddelete"]');
            if (del) {
                post('delete', {id: del.getAttribute('data-id')}).then(function(r) {
                    q('drawer').hidden = true;
                    toast(r.message);
                    load();
                    renderUpcoming();
                });
            }
            var cell = t.closest('.ll-cal-cell');
            if (cell && !t.closest('button') && cfg.canpersonal) {
                openNew(new Date(parseInt(cell.getAttribute('data-day'), 10)));
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                q('drawer').hidden = true;
                closeModal();
                q('subpop').hidden = true;
            }
            if (e.target.matches('input, textarea, select')) {
                return;
            }
            if (e.key === 't') {
                q('today').click();
            } else if (e.key === 'ArrowLeft' || e.key === 'j') {
                q('prev').click();
            } else if (e.key === 'ArrowRight' || e.key === 'k') {
                q('next').click();
            } else if (e.key === 'm') {
                setView('month');
            } else if (e.key === 'w') {
                setView('week');
            } else if (e.key === 'a') {
                setView('agenda');
            }
        });

        // ---------- Navigation ----------
        var setView = function(v) {
            state.view = v;
            store.set('ll-cal-view', v);
            load();
        };
        root.querySelectorAll('[data-cal-view]').forEach(function(b) {
            b.addEventListener('click', function() {
                setView(b.getAttribute('data-cal-view'));
            });
        });
        q('today').addEventListener('click', function() {
            state.date = day0(new Date());
            load();
        });
        var step = function(dir) {
            if (state.view === 'month') {
                state.date = new Date(state.date.getFullYear(), state.date.getMonth() + dir, 1);
            } else if (state.view === 'week') {
                state.date = addDays(state.date, 7 * dir);
            } else {
                state.date = addDays(state.date, 28 * dir);
            }
            load();
        };
        q('prev').addEventListener('click', function() {
            step(-1);
        });
        q('next').addEventListener('click', function() {
            step(1);
        });

        // ---------- Course filter ----------
        var legend = q('legend');
        if (legend) {
            legend.querySelectorAll('input').forEach(function(cb) {
                cb.checked = state.hidden.indexOf(parseInt(cb.value, 10)) === -1;
            });
            legend.addEventListener('change', function() {
                state.hidden = [];
                legend.querySelectorAll('input').forEach(function(cb) {
                    if (!cb.checked) {
                        state.hidden.push(parseInt(cb.value, 10));
                    }
                });
                store.set('ll-cal-hidden', JSON.stringify(state.hidden));
                render();
            });
        }

        // ---------- Reminders ----------
        q('reminders').addEventListener('change', function(e) {
            post('reminders', {on: e.target.checked ? 1 : 0}).then(function(r) {
                toast(r.message);
            });
        });

        // ---------- Subscribe ----------
        q('subscribe').addEventListener('click', function(e) {
            e.stopPropagation();
            var pop = q('subpop');
            pop.hidden = !pop.hidden;
            q('subscribe').setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function(e) {
            if (!q('subpop').hidden && !e.target.closest('.ll-cal-sub')) {
                q('subpop').hidden = true;
            }
        });
        q('copy').addEventListener('click', function() {
            var input = q('feed');
            input.select();
            (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.resolve(document.execCommand('copy')))
                .then(function() {
                    toast(S.copied);
                });
        });

        // ---------- New personal event ----------
        var modal = q('newmodal');
        var openNew = function(day) {
            if (!modal) {
                return;
            }
            var d = day || day0(new Date());
            var pad = function(x) {
                return (x < 10 ? '0' : '') + x;
            };
            q('ndate').value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            modal.hidden = false;
            q('nname').focus();
        };
        var closeModal = function() {
            if (modal) {
                modal.hidden = true;
            }
        };
        if (modal) {
            q('new').addEventListener('click', function() {
                openNew(state.view === 'month' ? null : state.date);
            });
            modal.querySelector('[data-cal="close"]').addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            q('newform').addEventListener('submit', function(e) {
                e.preventDefault();
                var dur = parseInt(modal.querySelector('input[name="cal-dur"]:checked').value, 10);
                var when = new Date(q('ndate').value + 'T' + (dur === 86400 ? '00:00' : (q('ntime').value || '09:00')) + ':00');
                post('create', {name: q('nname').value, start: Math.floor(when / 1000), duration: dur,
                    description: q('nnotes').value}).then(function(r) {
                    closeModal();
                    q('nname').value = '';
                    q('nnotes').value = '';
                    toast(r.message);
                    state.date = day0(when);
                    load();
                    renderUpcoming();
                }).catch(function(err) {
                    toast(err.message, true);
                });
            });
        }

        load();
        renderUpcoming();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
