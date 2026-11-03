/**
 * Premium SaaS inbox on Moodle's message web services.
 *
 * Conversations on the left (search, unread, starred, groups), the thread in the middle (day dividers,
 * grouped bubbles, Enter to send), and who you're talking to on the right (shared courses and, for teachers,
 * the learner's progress). Polls every 8 seconds while open.
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
        var root = document.querySelector('[data-ll-inbox]');
        if (!root) {
            return;
        }
        var cfg = JSON.parse(root.querySelector('[data-inbox-config]').textContent);
        var S = cfg.str;
        var q = function(n, all) {
            return all ? root.querySelectorAll('[data-ib="' + n + '"]') : root.querySelector('[data-ib="' + n + '"]');
        };
        var state = {convs: [], tab: 'all', term: '', current: null, other: null, last: 0, timer: null};

        var esc = function(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        };
        var plain = function(html) {
            var d = document.createElement('div');
            d.innerHTML = html || '';
            return (d.textContent || '').trim();
        };
        var initials = function(name) {
            return (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function(w) {
                return w[0];
            }).join('').toUpperCase();
        };
        var tint = function(id) {
            return 'll-tint-' + (Math.abs(id || 0) % 6);
        };
        var avatar = function(m, cls) {
            var img = m && m.profileimageurl && !/\/u\/f[12]/.test(m.profileimageurl) ? m.profileimageurl : '';
            return '<span class="ll-avatar ' + (cls || '') + ' ' + tint(m && m.id) + '">' +
                (img ? '<img src="' + esc(img) + '" alt="">' : esc(initials(m && m.fullname))) +
                (m && m.isonline ? '<i class="ll-online" aria-label="' + esc(S.online) + '"></i>' : '') + '</span>';
        };
        var timeLabel = function(ts) {
            var d = new Date(ts * 1000);
            var now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return d.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
            }
            var diff = (now - d) / 86400000;
            if (diff < 6) {
                return d.toLocaleDateString([], {weekday: 'short'});
            }
            return d.toLocaleDateString([], {day: 'numeric', month: 'short'});
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

        var call = function(method, args) {
            return new Promise(function(resolve, reject) {
                window.require(['core/ajax'], function(Ajax) {
                    Ajax.call([{methodname: method, args: args}])[0].then(resolve).catch(reject);
                });
            });
        };
        var post = function(action, params) {
            var body = new URLSearchParams(params);
            body.set('action', action);
            body.set('sesskey', cfg.sesskey);
            return fetch(cfg.ajax, {method: 'POST', body: body, credentials: 'same-origin'}).then(function(r) {
                return r.json();
            });
        };
        var toast = function(msg, error) {
            window.require(['core/toast'], function(Toast) {
                Toast.add(msg, {type: error ? 'danger' : 'success'});
            });
        };

        // ---------- Conversation list ----------
        var otherOf = function(c) {
            return (c.members || []).filter(function(m) {
                return m.id !== cfg.userid;
            })[0] || (c.members || [])[0] || {};
        };
        var titleOf = function(c) {
            if (c.type === 2) {
                return c.name || S.group;
            }
            if (c.type === 3) {
                return S.selfnote;
            }
            return otherOf(c).fullname || '';
        };
        var renderList = function() {
            var list = q('convs');
            var term = state.term.toLowerCase();
            var rows = state.convs.filter(function(c) {
                if (state.tab === 'unread' && !c.unreadcount) {
                    return false;
                }
                if (state.tab === 'starred' && !c.isfavourite) {
                    return false;
                }
                if (state.tab === 'groups' && c.type !== 2) {
                    return false;
                }
                return !term || titleOf(c).toLowerCase().indexOf(term) !== -1 ||
                    plain((c.messages[0] || {}).text).toLowerCase().indexOf(term) !== -1;
            });
            var unread = state.convs.filter(function(c) {
                return c.unreadcount;
            }).length;
            q('unreadcount').textContent = unread ? unread : '';
            if (!rows.length) {
                list.innerHTML = '<li class="ll-ib-none">' + esc(term ? S.nomatch : S.noconvs) + '</li>';
                return;
            }
            list.innerHTML = rows.map(function(c) {
                var m = c.messages[0] || {};
                var other = otherOf(c);
                var mine = m.useridfrom === cfg.userid;
                var av = c.type === 2
                    ? '<span class="ll-avatar ll-ib-groupav ' + tint(c.id) + '">' + esc(initials(c.name)) + '</span>'
                    : avatar(other);
                return '<li><button type="button" class="ll-ib-conv' + (state.current && state.current.id === c.id ? ' is-on' : '') +
                    (c.unreadcount ? ' is-unread' : '') + '" data-conv="' + c.id + '">' + av +
                    '<span class="ll-ib-convtext"><span class="ll-ib-convtop"><strong>' + esc(titleOf(c)) + '</strong>' +
                    (c.isfavourite ? '<i class="ll-ib-staricon" aria-label="' + esc(S.starred) + '">★</i>' : '') +
                    '<time>' + (m.timecreated ? esc(timeLabel(m.timecreated)) : '') + '</time></span>' +
                    (c.type === 2 && c.subname ? '<span class="ll-ib-convsub">' + esc(c.subname) + '</span>' : '') +
                    '<span class="ll-ib-convlast">' + (mine ? '<b>' + esc(S.you) + ':</b> ' : '') + esc(plain(m.text)) + '</span>' +
                    '</span>' + (c.unreadcount ? '<span class="ll-ib-badge">' + c.unreadcount + '</span>' : '') + '</button></li>';
            }).join('');
        };
        var loadConvs = function() {
            return call('core_message_get_conversations', {userid: cfg.userid, limitfrom: 0, limitnum: 100, type: null,
                favourites: null, mergeself: true}).then(function(r) {
                state.convs = (r.conversations || []).sort(function(a, b) {
                    return ((b.messages[0] || {}).timecreated || 0) - ((a.messages[0] || {}).timecreated || 0);
                });
                // A message arriving in the open conversation is read straight away.
                var open = state.current && state.current.id ? state.convs.filter(function(x) {
                    return x.id === state.current.id;
                })[0] : null;
                if (open && open.unreadcount && !q('chat').hidden && !document.hidden) {
                    open.unreadcount = 0;
                    call('core_message_mark_all_conversation_messages_as_read', {userid: cfg.userid, conversationid: open.id})
                        .catch(function() {
                            return null;
                        });
                }
                renderList();
                return state.convs;
            });
        };

        // ---------- Thread ----------
        var renderMessages = function(messages, members) {
            var box = q('msgs');
            var byId = {};
            (members || []).forEach(function(m) {
                byId[m.id] = m;
            });
            if (!messages.length) {
                box.innerHTML = '<div class="ll-ib-emptythread">' + esc(S.empty) + '</div>';
                return;
            }
            var html = '';
            var lastDay = '';
            var lastFrom = null;
            var lastTime = 0;
            messages.forEach(function(m) {
                var day = new Date(m.timecreated * 1000).toDateString();
                if (day !== lastDay) {
                    html += '<div class="ll-ib-day"><span>' + esc(dayLabel(m.timecreated)) + '</span></div>';
                    lastDay = day;
                    lastFrom = null;
                }
                var mine = m.useridfrom === cfg.userid;
                var grouped = lastFrom === m.useridfrom && m.timecreated - lastTime < 300;
                var who = byId[m.useridfrom] || {id: m.useridfrom, fullname: ''};
                html += '<div class="ll-ib-msg' + (mine ? ' is-mine' : '') + (grouped ? ' is-grouped' : '') + '">' +
                    (!mine && !grouped && state.current && state.current.type === 2 ? '<span class="ll-ib-from">' +
                        esc(who.fullname) + '</span>' : '') +
                    (!mine ? (grouped ? '<span class="ll-ib-avspace"></span>' : avatar(who, 'll-ib-msgav')) : '') +
                    '<div class="ll-ib-bubble">' + m.text + '<time>' +
                    esc(new Date(m.timecreated * 1000).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})) +
                    '</time></div></div>';
                lastFrom = m.useridfrom;
                lastTime = m.timecreated;
            });
            box.innerHTML = html;
            box.scrollTop = box.scrollHeight;
        };
        var setHeader = function(c) {
            var other = otherOf(c);
            q('hav').outerHTML = c.type === 2
                ? '<span class="ll-avatar ll-ib-av ll-ib-groupav ' + tint(c.id) + '" data-ib="hav">' + esc(initials(c.name)) + '</span>'
                : avatar(other, 'll-ib-av').replace('<span class="ll-avatar', '<span data-ib="hav" class="ll-avatar');
            q('hname').textContent = titleOf(c);
            q('hsub').textContent = c.type === 2 ? (c.subname || '') : (other.isonline ? S.online : '');
            var star = q('star');
            star.setAttribute('aria-pressed', c.isfavourite ? 'true' : 'false');
        };
        var openConv = function(c, focus) {
            state.current = c;
            state.other = c.type === 1 ? otherOf(c) : null;
            q('blank').hidden = true;
            q('chat').hidden = false;
            root.classList.add('has-chat');
            setHeader(c);
            renderList();
            loadThread(true);
            loadPerson();
            if (c.unreadcount) {
                call('core_message_mark_all_conversation_messages_as_read', {userid: cfg.userid, conversationid: c.id})
                    .then(function() {
                        c.unreadcount = 0;
                        renderList();
                    }).catch(function() {
                        return null;
                    });
            }
            if (focus !== false) {
                q('text').focus();
            }
            if (window.history.replaceState) {
                window.history.replaceState(null, '', '?convid=' + c.id);
            }
        };
        var loadThread = function(scroll) {
            var c = state.current;
            if (!c || !c.id) {
                return Promise.resolve();
            }
            return call('core_message_get_conversation_messages', {currentuserid: cfg.userid, convid: c.id, limitfrom: 0,
                limitnum: 200, newest: true}).then(function(r) {
                var msgs = (r.messages || []).slice().reverse();
                var newest = msgs.length ? msgs[msgs.length - 1].id : 0;
                if (scroll || newest !== state.last) {
                    state.last = newest;
                    renderMessages(msgs, r.members);
                }
            });
        };
        var newChatWith = function(user) {
            // An existing conversation, or a fresh empty thread that is created by the first message.
            var existing = state.convs.filter(function(c) {
                return c.type === 1 && otherOf(c).id === user.id;
            })[0];
            if (existing) {
                openConv(existing);
                return;
            }
            var draft = {id: 0, type: 1, members: [user], messages: [], isfavourite: false, unreadcount: 0, draft: true};
            state.current = draft;
            state.other = user;
            q('blank').hidden = true;
            q('chat').hidden = false;
            root.classList.add('has-chat');
            setHeader(draft);
            q('msgs').innerHTML = '<div class="ll-ib-emptythread">' + esc(S.empty) + '</div>';
            loadPerson();
            q('text').focus();
        };

        // ---------- Person panel ----------
        var loadPerson = function() {
            var side = q('side');
            var box = q('person');
            if (!state.other) {
                side.hidden = true;
                return;
            }
            side.hidden = q('info').getAttribute('aria-pressed') !== 'true';
            var u = state.other;
            box.innerHTML = '<div class="ll-ib-personhead">' + avatar(u, 'll-ib-bigav') + '<strong>' + esc(u.fullname) +
                '</strong></div><div class="ll-ib-loading"></div>';
            post('person', {userid: u.id}).then(function(r) {
                var p = r.person;
                var courses = p.courses.length ? p.courses.map(function(c) {
                    return '<li><a href="' + esc(c.url) + '"><strong>' + c.name + '</strong><span>' + esc(c.role) + '</span></a>' +
                        (c.progress !== null ? '<div class="ll-ib-prog"><div class="ll-bar"><div class="ll-bar-fill" style="width:' +
                        c.progress + '%"></div></div><b>' + c.progress + '%</b></div>' : '') +
                        (c.gradesurl ? '<a class="ll-ib-gradeslink" href="' + esc(c.gradesurl) + '">' + esc(S.grades) + '</a>' : '') +
                        '</li>';
                }).join('') : '<li class="ll-ib-none">' + esc(S.noshared) + '</li>';
                box.innerHTML = '<div class="ll-ib-personhead">' + avatar(u, 'll-ib-bigav') + '<strong>' + esc(p.name) + '</strong>' +
                    (p.email ? '<a href="mailto:' + esc(p.email) + '">' + esc(p.email) + '</a>' : '') +
                    '<span>' + esc(u.isonline ? S.online : S.lastseen.replace('{t}', p.lastaccess)) + '</span>' +
                    '<a class="btn btn-secondary btn-sm" href="' + esc(p.profileurl) + '">' + esc(S.profile) + '</a></div>' +
                    '<h4>' + esc(S.together) + '</h4><ul class="ll-ib-courses">' + courses + '</ul>';
            }).catch(function() {
                box.querySelector('.ll-ib-loading').remove();
            });
        };

        // ---------- Sending ----------
        var text = q('text');
        var sendBtn = q('send');
        var autosize = function() {
            text.style.height = 'auto';
            text.style.height = Math.min(text.scrollHeight, 180) + 'px';
            sendBtn.disabled = text.value.trim() === '';
        };
        text.addEventListener('input', autosize);
        text.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                q('compose').requestSubmit ? q('compose').requestSubmit() : sendBtn.click();
            }
        });
        q('compose').addEventListener('submit', function(e) {
            e.preventDefault();
            var body = text.value.trim();
            var c = state.current;
            if (!body || !c) {
                return;
            }
            var box = q('msgs');
            var empty = box.querySelector('.ll-ib-emptythread');
            if (empty) {
                empty.remove();
            }
            var pending = document.createElement('div');
            pending.className = 'll-ib-msg is-mine is-pending';
            pending.innerHTML = '<div class="ll-ib-bubble">' + esc(body).replace(/\n/g, '<br>') + '<time>' + esc(S.sending) + '</time></div>';
            box.appendChild(pending);
            box.scrollTop = box.scrollHeight;
            text.value = '';
            autosize();
            var done = function() {
                return loadConvs().then(function() {
                    var match = state.convs.filter(function(x) {
                        return c.id ? x.id === c.id : (x.type === 1 && otherOf(x).id === state.other.id);
                    })[0];
                    if (match) {
                        state.current = match;
                        renderList();
                    }
                    return loadThread(true);
                });
            };
            var fail = function() {
                pending.classList.add('is-failed');
                pending.querySelector('time').textContent = S.failed;
                text.value = body;
                autosize();
            };
            if (c.id) {
                call('core_message_send_messages_to_conversation', {conversationid: c.id, messages: [{text: body, textformat: 2}]})
                    .then(done).catch(fail);
            } else {
                call('core_message_send_instant_messages', {messages: [{touserid: state.other.id, text: body, textformat: 2}]})
                    .then(function(r) {
                        if (r && r[0] && r[0].errormessage) {
                            throw new Error(r[0].errormessage);
                        }
                        return done();
                    }).catch(fail);
            }
        });

        // ---------- List interactions ----------
        q('convs').addEventListener('click', function(e) {
            var b = e.target.closest('[data-conv]');
            if (!b) {
                return;
            }
            var id = parseInt(b.getAttribute('data-conv'), 10);
            var c = state.convs.filter(function(x) {
                return x.id === id;
            })[0];
            if (c) {
                openConv(c);
            }
        });
        root.querySelectorAll('[data-ib-tab]').forEach(function(t) {
            t.addEventListener('click', function() {
                root.querySelectorAll('[data-ib-tab]').forEach(function(x) {
                    x.classList.toggle('is-on', x === t);
                    x.setAttribute('aria-selected', x === t ? 'true' : 'false');
                });
                state.tab = t.getAttribute('data-ib-tab');
                renderList();
            });
        });
        q('search').addEventListener('input', function(e) {
            state.term = e.target.value.trim();
            renderList();
        });
        q('back').addEventListener('click', function() {
            root.classList.remove('has-chat');
        });
        q('star').addEventListener('click', function() {
            var c = state.current;
            if (!c || !c.id) {
                return;
            }
            var on = !c.isfavourite;
            call(on ? 'core_message_set_favourite_conversations' : 'core_message_unset_favourite_conversations',
                {userid: cfg.userid, conversations: [c.id]}).then(function() {
                c.isfavourite = on;
                setHeader(c);
                renderList();
            });
        });
        q('info').addEventListener('click', function() {
            var b = q('info');
            var on = b.getAttribute('aria-pressed') !== 'true';
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            q('side').hidden = !on || !state.other;
        });

        // ---------- New message ----------
        var modal = q('newmodal');
        var openModal = function(m) {
            m.hidden = false;
            var f = m.querySelector('input, select, textarea');
            if (f) {
                f.focus();
            }
        };
        var closeModals = function() {
            root.querySelectorAll('.ll-ib-modal').forEach(function(m) {
                m.hidden = true;
            });
        };
        root.querySelectorAll('[data-ib="close"]').forEach(function(b) {
            b.addEventListener('click', closeModals);
        });
        root.querySelectorAll('.ll-ib-modal').forEach(function(m) {
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
        q('new', true).forEach(function(b) {
            b.addEventListener('click', function() {
                openModal(modal);
                searchPeople('');
            });
        });
        var people = q('people');
        var peopleTimer = null;
        var searchPeople = function(term) {
            if (term.length < 2) {
                // Recent people from your conversations.
                var seen = {};
                var recent = state.convs.filter(function(c) {
                    return c.type === 1;
                }).map(otherOf).filter(function(u) {
                    if (!u.id || seen[u.id]) {
                        return false;
                    }
                    seen[u.id] = true;
                    return true;
                }).slice(0, 8);
                people.innerHTML = recent.map(function(u) {
                    return '<li><button type="button" data-person=\'' + esc(JSON.stringify(u)) + '\'>' + avatar(u) +
                        '<span>' + esc(u.fullname) + '</span></button></li>';
                }).join('');
                return;
            }
            call('core_message_message_search_users', {userid: cfg.userid, search: term, limitfrom: 0, limitnum: 30})
                .then(function(r) {
                    var all = (r.contacts || []).concat(r.noncontacts || []);
                    people.innerHTML = all.length ? all.map(function(u) {
                        return '<li><button type="button" data-person=\'' + esc(JSON.stringify({id: u.id, fullname: u.fullname,
                            profileimageurl: u.profileimageurl, isonline: u.isonline})) + '\'>' + avatar(u) +
                            '<span>' + esc(u.fullname) + '</span></button></li>';
                    }).join('') : '<li class="ll-ib-none">' + esc(S.nomatch) + '</li>';
                });
        };
        q('peoplesearch').addEventListener('input', function(e) {
            window.clearTimeout(peopleTimer);
            var term = e.target.value.trim();
            peopleTimer = window.setTimeout(function() {
                searchPeople(term);
            }, 220);
        });
        people.addEventListener('click', function(e) {
            var b = e.target.closest('[data-person]');
            if (!b) {
                return;
            }
            closeModals();
            newChatWith(JSON.parse(b.getAttribute('data-person')));
        });

        // ---------- Message a whole course ----------
        var bcmodal = q('bcmodal');
        if (bcmodal) {
            q('broadcast', true).forEach(function(b) {
                b.addEventListener('click', function() {
                    openModal(bcmodal);
                    var sel = q('bccourse');
                    if (!sel.options.length) {
                        post('courses', {}).then(function(r) {
                            sel.innerHTML = r.courses.map(function(c) {
                                return '<option value="' + c.id + '">' + c.name + ' · ' +
                                    esc(S.learners.replace('{n}', c.learners)) + '</option>';
                            }).join('');
                        });
                    }
                });
            });
            q('bcform').addEventListener('submit', function(e) {
                e.preventDefault();
                var body = q('bctext').value.trim();
                if (!body) {
                    return;
                }
                post('broadcast', {courseid: q('bccourse').value, text: body}).then(function(r) {
                    closeModals();
                    q('bctext').value = '';
                    toast(r.message);
                    loadConvs();
                }).catch(function() {
                    toast(S.failed, true);
                });
            });
        }

        // ---------- Start ----------
        loadConvs().then(function() {
            var c = null;
            if (cfg.convid) {
                c = state.convs.filter(function(x) {
                    return x.id === cfg.convid;
                })[0];
            }
            if (!c && cfg.withuser && cfg.withuser !== cfg.userid) {
                newChatWith({id: cfg.withuser, fullname: cfg.withname});
                return;
            }
            if (!c && window.innerWidth >= 992 && state.convs.length) {
                c = state.convs[0];
            }
            if (c) {
                openConv(c, false);
            }
        });
        state.timer = window.setInterval(function() {
            if (document.hidden) {
                return;
            }
            loadConvs();
            loadThread(false);
        }, 8000);
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
