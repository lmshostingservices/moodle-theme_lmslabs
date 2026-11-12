/**
 * LMS Labs theme interactions: sidebar, command search, one-click site colour, blocks panel.
 *
 * Plain JavaScript so it needs no build step and behaves the same on every supported Moodle version.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

// One heading per page: hide a heading in the page body that repeats the page title shown above it.
var dedupeHeadings = function() {
    var norm = function(el) {
        return (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    };
    var top = document.querySelector('.ll-ptitle h1, #page-header h1, #page-header h2, .page-header-headings h1');
    var main = document.getElementById('region-main');
    if (!top || !main) {
        return;
    }
    var title = norm(top);
    if (!title) {
        return;
    }
    var heads = main.querySelectorAll('h1, h2, h3');
    for (var i = 0; i < heads.length && i < 4; i++) {
        var h = heads[i];
        if (h === top || top.contains(h) || h.closest('.ll-ptitle, #page-header, form, fieldset, .card, .modal, details')) {
            continue;
        }
        if (norm(h) === title) {
            h.classList.add('ll-dupe-heading');
            h.setAttribute('hidden', 'hidden');
            break;
        }
    }
};

// Instant feedback on every navigation: the clicked menu item lights up and a thin bar starts straight away,
// so a page change feels immediate while the next page loads.
var switchFeedback = function() {
    var busy = false;
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || busy) {
            return;
        }
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-toggle') ||
                a.hasAttribute('data-bs-toggle') || a.getAttribute('role') === 'button') {
            return;
        }
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) {
            return;
        }
        var url;
        try {
            url = new URL(a.href, window.location.href);
        } catch (err) {
            return;
        }
        if (url.origin !== window.location.origin || (url.pathname === window.location.pathname &&
                url.search === window.location.search)) {
            return;
        }
        busy = true;
        window.setTimeout(function() {
            busy = false;
        }, 3000);
        var link = a.closest('.ll-link');
        if (link) {
            document.querySelectorAll('.ll-nav .ll-link.is-active').forEach(function(el) {
                el.classList.remove('is-active');
            });
            link.classList.add('is-active');
        }
        var bar = document.createElement('div');
        bar.className = 'll-switchbar';
        document.body.appendChild(bar);
        document.body.classList.add('ll-navigating');
    });
    // Coming back with the browser Back button must not keep the bar.
    window.addEventListener('pageshow', function() {
        busy = false;
        document.body.classList.remove('ll-navigating');
        document.querySelectorAll('.ll-switchbar').forEach(function(el) {
            el.remove();
        });
    });
};

var start = function() {
    dedupeHeadings();
    switchFeedback();
    var cfgEl = document.getElementById('ll-config');
    if (!cfgEl) {
        return;
    }
    var cfg = {};
    try {
        cfg = JSON.parse(cfgEl.textContent);
    } catch (e) {
        return;
    }
    var doc = document.documentElement;
    var body = document.body;
    var isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

    var store = {
        get: function(k) {
            try {
                return window.localStorage.getItem(k);
            } catch (e) {
                return null;
            }
        },
        set: function(k, v) {
            try {
                window.localStorage.setItem(k, v);
            } catch (e) {
                // Storage unavailable: preference simply isn't remembered.
            }
        }
    };

    var str = function(key, a) {
        var s = (cfg.str && cfg.str[key]) || key;
        return a === undefined ? s : s.replace('{$a}', a);
    };

    // Keyboard hint.
    document.querySelectorAll('[data-ll-kbd]').forEach(function(k) {
        k.textContent = isMac ? '⌘K' : 'Ctrl K';
    });

    // Sidebar collapse (desktop) and slide-over (mobile).
    var setCollapsed = function(on) {
        doc.classList.toggle('ll-collapsed', on);
        store.set('ll-collapsed', on ? '1' : '0');
    };
    var setNavOpen = function(on) {
        body.classList.toggle('ll-nav-open', on);
    };

    // Collapsible groups, remembered per group; a group holding the current page always opens.
    document.querySelectorAll('.ll-group.is-collapsible').forEach(function(group) {
        var key = 'll-group-' + group.getAttribute('data-group');
        var saved = store.get(key);
        var closeIt = saved === '0' || (saved === null && group.classList.contains('start-closed'));
        if (closeIt && !group.classList.contains('has-active')) {
            group.classList.add('is-closed');
            var btn = group.querySelector('.ll-group-label');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Expandable items remember being open; the one holding the current page is always open.
    document.querySelectorAll('.ll-has-sub').forEach(function(li) {
        if (!li.classList.contains('is-open') && store.get('ll-sub-' + li.getAttribute('data-sub')) === '1') {
            li.classList.add('is-open');
            li.querySelector('[data-ll-action="sub"]').setAttribute('aria-expanded', 'true');
        }
    });

    // Keep the active link in view inside the sidebar.
    var active = document.querySelector('.ll-link.is-active, .ll-sublink.is-active');
    var navScroller = document.querySelector('.ll-nav');
    if (active && navScroller) {
        var top = active.offsetTop - navScroller.clientHeight / 2;
        if (top > 0) {
            navScroller.scrollTop = top;
        }
    }

    // Blocks panel.
    var setBlocks = function(open) {
        var region = document.querySelector('[data-ll-body]');
        if (!region) {
            return;
        }
        region.classList.toggle('blocks-open', open);
        document.querySelectorAll('.ll-blocks-btn').forEach(function(b) {
            b.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        if (window.require) {
            window.require(['core/ajax'], function(Ajax) {
                Ajax.call([{
                    methodname: 'core_user_update_user_preferences',
                    args: {preferences: [{type: 'drawer-open-block', value: open ? 1 : 0}]}
                }])[0].catch(function() {
                    // Preference not saved; the panel still toggles.
                });
            });
        }
    };

    // ---------- Site colour ----------
    var accentWrap = document.querySelector('[data-ll-accent]');
    var accentPop = accentWrap ? accentWrap.querySelector('.ll-accent-pop') : null;
    var accentBtn = accentWrap ? accentWrap.querySelector('.ll-accent-btn') : null;

    var hexToRgb = function(hex) {
        var n = parseInt(hex.slice(1), 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255].join(',');
    };
    var applyAccent = function(hex) {
        // Set the two colour variables only, so the font, size and cohort variables in #ll-accent stay.
        document.documentElement.style.setProperty('--ll-accent', hex);
        document.documentElement.style.setProperty('--ll-accent-rgb', hexToRgb(hex));
        var hexLabel = document.querySelector('[data-ll-hex]');
        if (hexLabel) {
            hexLabel.textContent = hex;
        }
        document.querySelectorAll('.ll-swatch').forEach(function(sw) {
            var on = sw.getAttribute('data-hex') === hex;
            sw.classList.toggle('is-selected', on);
            sw.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    };
    var saveTimer = null;
    var saveAccent = function(hex) {
        applyAccent(hex);
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function() {
            var data = new FormData();
            data.append('sesskey', cfg.sesskey);
            data.append('hex', hex);
            fetch(cfg.accentUrl, {method: 'POST', body: data, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    if (!res || !res.ok) {
                        throw new Error('fail');
                    }
                    toast(str('saved'));
                })
                .catch(function() {
                    toast(str('savefailed'), true);
                });
        }, 250);
    };
    var setAccentOpen = function(open) {
        if (!accentPop) {
            return;
        }
        accentPop.hidden = !open;
        accentBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var sel = accentPop.querySelector('.ll-swatch.is-selected') || accentPop.querySelector('.ll-swatch');
            if (sel) {
                sel.focus();
            }
        }
    };
    if (accentPop) {
        accentPop.addEventListener('click', function(e) {
            var sw = e.target.closest('.ll-swatch');
            if (sw) {
                saveAccent(sw.getAttribute('data-hex'));
                var custom = accentPop.querySelector('[data-ll-custom]');
                if (custom) {
                    custom.value = sw.getAttribute('data-hex');
                }
            }
        });
        var custom = accentPop.querySelector('[data-ll-custom]');
        if (custom) {
            custom.addEventListener('input', function() {
                applyAccent(custom.value.toLowerCase());
            });
            custom.addEventListener('change', function() {
                saveAccent(custom.value.toLowerCase());
            });
        }
    }

    // Toast, using Moodle's own when available.
    var toast = function(message, error) {
        if (window.require) {
            window.require(['core/toast'], function(Toast) {
                Toast.add(message, {type: error ? 'danger' : 'success'});
            });
        }
    };

    // ---------- Command search ----------
    var pal = document.querySelector('[data-ll-palette]');
    var palInput = pal ? pal.querySelector('[data-ll-palette-input]') : null;
    var palList = pal ? pal.querySelector('[data-ll-palette-list]') : null;
    var palItems = [];
    var palIndex = 0;
    var lastFocus = null;

    var escapeHtml = function(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };
    var iconFor = function(key) {
        var link = document.querySelector('.ll-link[data-key="' + key + '"] svg') ||
            document.querySelector('.ll-sublink[data-key="' + key + '"]') &&
            document.querySelector('.ll-sublink[data-key="' + key + '"]').closest('.ll-has-sub').querySelector('.ll-link svg');
        return link ? link.outerHTML : '';
    };
    var searchIcon = (document.querySelector('.ll-search-trigger svg') || {outerHTML: ''}).outerHTML;

    var score = function(text, q) {
        text = text.toLowerCase();
        if (!q) {
            return 1;
        }
        var idx = text.indexOf(q);
        if (idx === 0) {
            return 100;
        }
        if (idx > 0) {
            return 80 - idx;
        }
        // Loose subsequence match.
        var t = 0;
        for (var i = 0; i < q.length; i++) {
            t = text.indexOf(q[i], t);
            if (t < 0) {
                return 0;
            }
            t++;
        }
        return 10;
    };

    // Where the person is: inside a course, search that course first; Tab (or the switch) widens to the whole site.
    var scope = 'site';
    var scopeBox = pal ? pal.querySelector('[data-ll-palette-scope]') : null;
    var live = [];
    var liveTimer = null;
    var liveSeq = 0;
    var setScope = function(s) {
        scope = s;
        try {
            window.sessionStorage.setItem('ll-scope', s);
        } catch (e) {
            // Ignore.
        }
        if (scopeBox) {
            scopeBox.querySelectorAll('[data-pal-scope]').forEach(function(b) {
                b.classList.toggle('is-on', b.getAttribute('data-pal-scope') === s);
            });
        }
        palInput.placeholder = s === 'course' && cfg.course ? str('placeholdercourse', cfg.course.name) : palInput.getAttribute('data-site-ph');
    };
    var fetchLive = function() {
        var raw = palInput.value.trim();
        var seq = ++liveSeq;
        live = [];
        if (raw.length < 2 || !cfg.searchAjax) {
            return;
        }
        var body = new URLSearchParams({q: raw, scope: scope, courseid: cfg.course ? cfg.course.id : 0, sesskey: cfg.sesskey});
        fetch(cfg.searchAjax, {method: 'POST', body: body, credentials: 'same-origin'}).then(function(r) {
            return r.json();
        }).then(function(r) {
            if (seq !== liveSeq || !r.items) {
                return;
            }
            live = r.items;
            renderPalette(true);
        }).catch(function() {
            // Offline or blocked: the page results still work.
        });
    };

    var renderPalette = function(keepLive) {
        var q = palInput.value.trim().toLowerCase();
        var raw = palInput.value.trim();
        if (keepLive !== true) {
            live = [];
            window.clearTimeout(liveTimer);
            liveTimer = window.setTimeout(fetchLive, 180);
        }
        var results = (cfg.palette || []).filter(function(item) {
            return scope !== 'course' || item.s === 'c';
        }).map(function(item) {
            var s = Math.max(score(item.n, q), q ? score(item.g + ' ' + item.n, q) - 5 : 0);
            return {item: item, s: s};
        }).filter(function(r) {
            return r.s > 0;
        }).sort(function(a, b) {
            return b.s - a.s;
        }).slice(0, q ? 9 : 12).map(function(r) {
            return {name: r.item.n, group: r.item.g, url: r.item.u, icon: iconFor(r.item.i)};
        });
        live.forEach(function(it) {
            results.push({name: it.n, group: it.g, url: it.u, icon: it.svg || searchIcon});
        });
        if (raw && scope === 'site') {
            results.push({
                name: str('searchcourses', raw),
                url: cfg.courseSearch + '?search=' + encodeURIComponent(raw),
                icon: searchIcon, group: ''
            });
            if (cfg.settingsSearch) {
                results.push({
                    name: str('searchsettings', raw),
                    url: cfg.settingsSearch + '?query=' + encodeURIComponent(raw),
                    icon: searchIcon, group: ''
                });
            }
        }
        palItems = results;
        palIndex = 0;
        if (!results.length) {
            palList.innerHTML = '<li class="ll-palette-empty">' + escapeHtml(str('noresults')) + '</li>';
            return;
        }
        palList.innerHTML = results.map(function(r, i) {
            return '<li role="option" id="ll-opt-' + i + '" class="ll-palette-item' + (i === 0 ? ' is-selected' : '') +
                '" data-i="' + i + '" aria-selected="' + (i === 0) + '">' +
                '<span class="ll-palette-ico">' + r.icon + '</span>' +
                '<span class="ll-palette-name">' + escapeHtml(r.name) + '</span>' +
                (r.group ? '<span class="ll-palette-group">' + escapeHtml(r.group) + '</span>' : '') + '</li>';
        }).join('');
        palInput.setAttribute('aria-activedescendant', 'll-opt-0');
    };
    var movePalette = function(delta) {
        if (!palItems.length) {
            return;
        }
        palIndex = (palIndex + delta + palItems.length) % palItems.length;
        palList.querySelectorAll('.ll-palette-item').forEach(function(li, i) {
            li.classList.toggle('is-selected', i === palIndex);
            li.setAttribute('aria-selected', i === palIndex ? 'true' : 'false');
            if (i === palIndex) {
                li.scrollIntoView({block: 'nearest'});
            }
        });
        palInput.setAttribute('aria-activedescendant', 'll-opt-' + palIndex);
    };
    var openPalette = function() {
        if (!pal) {
            return;
        }
        lastFocus = document.activeElement;
        pal.hidden = false;
        body.classList.add('ll-palette-open');
        palInput.value = '';
        var saved = 'course';
        try {
            saved = window.sessionStorage.getItem('ll-scope') || 'course';
        } catch (e) {
            saved = 'course';
        }
        if (cfg.course) {
            scopeBox.hidden = false;
            setScope(saved === 'site' ? 'site' : 'course');
        } else {
            setScope('site');
        }
        renderPalette();
        setTimeout(function() {
            palInput.focus();
        }, 10);
    };
    var closePalette = function() {
        if (!pal || pal.hidden) {
            return;
        }
        pal.hidden = true;
        body.classList.remove('ll-palette-open');
        if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
    };
    if (pal) {
        palInput.setAttribute('data-site-ph', palInput.placeholder);
        if (scopeBox) {
            var pbs = scopeBox.querySelectorAll('[data-pal-scope]');
            pbs[0].textContent = cfg.course ? str('scopecourse', cfg.course.name) : '';
            pbs[1].textContent = str('scopesite');
            scopeBox.querySelector('[data-ll-scope-hint]').textContent = str('scopehint');
            scopeBox.addEventListener('click', function(e) {
                var b = e.target.closest('[data-pal-scope]');
                if (b) {
                    setScope(b.getAttribute('data-pal-scope'));
                    renderPalette();
                    palInput.focus();
                }
            });
        }
        palInput.addEventListener('input', function() {
            renderPalette();
        });
        palInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                movePalette(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                movePalette(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (palItems[palIndex]) {
                    window.location.href = palItems[palIndex].url;
                }
            } else if (e.key === 'Tab') {
                e.preventDefault();
                if (cfg.course) {
                    setScope(scope === 'course' ? 'site' : 'course');
                    renderPalette();
                }
            }
        });
        palList.addEventListener('mousemove', function(e) {
            var li = e.target.closest('.ll-palette-item');
            if (li && +li.getAttribute('data-i') !== palIndex) {
                movePalette(+li.getAttribute('data-i') - palIndex);
            }
        });
        palList.addEventListener('click', function(e) {
            var li = e.target.closest('.ll-palette-item');
            if (li && palItems[+li.getAttribute('data-i')]) {
                window.location.href = palItems[+li.getAttribute('data-i')].url;
            }
        });
    }

    // ---------- One-click file uploads ----------
    // Moodle's file manager needs Add > Upload a file > Choose > Upload. This puts a single dropzone on
    // every file manager and file picker: click opens the computer's file dialog, or drop files on it.
    // Files go through Moodle's own drag-and-drop uploader, so limits, types and permissions are unchanged.
    var uploadIcon = '<svg class="ll-ico" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" ' +
        'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M12 3v12"/><path d="m17 8-5-5-5 5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>';
    var sendFiles = function(target, files) {
        if (!files || !files.length || typeof DataTransfer === 'undefined') {
            return;
        }
        var dt = new DataTransfer();
        Array.prototype.forEach.call(files, function(f) {
            dt.items.add(f);
        });
        ['dragenter', 'dragover', 'drop'].forEach(function(type) {
            var ev;
            try {
                ev = new DragEvent(type, {bubbles: true, cancelable: true, dataTransfer: dt});
            } catch (e) {
                ev = new Event(type, {bubbles: true, cancelable: true});
                ev.dataTransfer = dt;
            }
            target.dispatchEvent(ev);
        });
    };
    var enhanceUploader = function(root, target, moreButton, single) {
        if (!root || !target || root.querySelector('.ll-drop')) {
            return;
        }
        var restrictions = root.querySelector('.fp-restrictions');
        var hint = restrictions ? restrictions.textContent.replace(/\s+/g, ' ').replace(/ - .*$/, '').trim() : '';
        var wrap = document.createElement('div');
        wrap.className = 'll-drop-wrap';
        wrap.innerHTML = '<button type="button" class="ll-drop">' +
            '<span class="ll-drop-ico">' + uploadIcon + '</span>' +
            '<span class="ll-drop-text"><strong>' + str(single ? 'drop_choose' : 'drop_click') + '</strong> ' +
            str('drop_or') + '</span>' +
            (hint ? '<span class="ll-drop-hint">' + hint.replace(/[<>&]/g, '') + '</span>' : '') +
            '</button>' +
            (moreButton ? '<button type="button" class="ll-drop-more">' + str('drop_more') + '</button>' : '') +
            '<input type="file" class="ll-drop-input" tabindex="-1" aria-hidden="true"' + (single ? '' : ' multiple') + '>';
        var input = wrap.querySelector('.ll-drop-input');
        var zone = wrap.querySelector('.ll-drop');
        zone.addEventListener('click', function() {
            input.value = '';
            input.click();
        });
        input.addEventListener('change', function() {
            sendFiles(target, input.files);
        });
        ['dragenter', 'dragover'].forEach(function(type) {
            zone.addEventListener(type, function(e) {
                e.preventDefault();
                zone.classList.add('is-over');
            });
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('is-over');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('is-over');
            sendFiles(target, e.dataTransfer && e.dataTransfer.files);
        });
        if (moreButton) {
            wrap.querySelector('.ll-drop-more').addEventListener('click', function() {
                moreButton.click();
            });
        }
        root.classList.add('ll-uploader');
        root.insertBefore(wrap, root.firstChild);
    };
    var enhanceUploads = function(scope) {
        scope.querySelectorAll('.filemanager[id^="filemanager-"]').forEach(function(fm) {
            var target = fm.querySelector('.filemanager-container');
            var add = fm.querySelector('.fp-btn-add a, .fp-btn-add button');
            var single = /:\s*1\b/.test((fm.querySelector('.fp-restrictions') || {}).textContent || '');
            enhanceUploader(fm, target, add, single);
        });
        scope.querySelectorAll('[id^="file_info_"]').forEach(function(info) {
            var root = info.closest('.felement, .form-filepicker, .fitem') || info.parentNode;
            var choose = root.querySelector('.fp-btn-choose, input[type="button"][id^="filepicker-button"]');
            if (root.querySelector('.ll-drop')) {
                return;
            }
            var holder = document.createElement('div');
            holder.className = 'll-filepicker';
            info.parentNode.insertBefore(holder, info);
            enhanceUploader(holder, info, choose, true);
            root.classList.add('ll-uploader-picker');
        });
    };
    enhanceUploads(document);
    if ('MutationObserver' in window) {
        var uploadTimer = null;
        new MutationObserver(function() {
            clearTimeout(uploadTimer);
            uploadTimer = setTimeout(function() {
                enhanceUploads(document);
            }, 150);
        }).observe(document.body, {childList: true, subtree: true});
    }

    // ---------- One course format ----------
    if (cfg.format) {
        var formatSelect = document.getElementById('id_format');
        if (formatSelect) {
            var current = formatSelect.value;
            var isNew = /[?&]category=/.test(window.location.search) && !/[?&]id=/.test(window.location.search);
            Array.prototype.slice.call(formatSelect.options).forEach(function(opt) {
                if (opt.value !== cfg.format && (isNew || opt.value !== current)) {
                    opt.remove();
                }
            });
            if (isNew && formatSelect.value !== cfg.format) {
                formatSelect.value = cfg.format;
                formatSelect.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }
    }

    // ---------- Event wiring ----------
    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-ll-action]');
        if (el) {
            var action = el.getAttribute('data-ll-action');
            if (action === 'collapse') {
                setCollapsed(!doc.classList.contains('ll-collapsed'));
            } else if (action === 'opennav') {
                setNavOpen(true);
            } else if (action === 'closenav') {
                setNavOpen(false);
            } else if (action === 'sub') {
                if (doc.classList.contains('ll-collapsed') && window.innerWidth >= 992) {
                    setCollapsed(false);
                }
                var li = el.closest('.ll-has-sub');
                var open = li.classList.toggle('is-open');
                el.setAttribute('aria-expanded', open ? 'true' : 'false');
                store.set('ll-sub-' + li.getAttribute('data-sub'), open ? '1' : '0');
            } else if (action === 'group') {
                var group = el.closest('.ll-group');
                if (doc.classList.contains('ll-collapsed') && window.innerWidth >= 992) {
                    setCollapsed(false);
                    return;
                }
                var closed = group.classList.toggle('is-closed');
                el.setAttribute('aria-expanded', closed ? 'false' : 'true');
                store.set('ll-group-' + group.getAttribute('data-group'), closed ? '0' : '1');
            } else if (action === 'palette') {
                setNavOpen(false);
                openPalette();
            } else if (action === 'closepalette') {
                closePalette();
            } else if (action === 'accent') {
                setAccentOpen(accentPop.hidden);
            } else if (action === 'blocks') {
                var region = document.querySelector('[data-ll-body]');
                setBlocks(!region.classList.contains('blocks-open'));
            }
            return;
        }
        if (accentPop && !accentPop.hidden && !e.target.closest('[data-ll-accent]')) {
            setAccentOpen(false);
        }
    });

    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            if (pal && pal.hidden) {
                openPalette();
            } else {
                closePalette();
            }
            return;
        }
        if (e.key === 'Escape') {
            if (pal && !pal.hidden) {
                closePalette();
            } else if (accentPop && !accentPop.hidden) {
                setAccentOpen(false);
                accentBtn.focus();
            } else if (body.classList.contains('ll-nav-open')) {
                setNavOpen(false);
            }
        }
    });

    // Course card image: pick a file on the card, it uploads and shows straight away.
    document.addEventListener('change', function(e) {
        var input = e.target;
        if (!input.matches || !input.matches('[data-ll-courseimage]') || !input.files.length) {
            return;
        }
        var body = new FormData();
        body.append('courseid', input.getAttribute('data-ll-courseimage'));
        body.append('sesskey', cfg.sesskey);
        body.append('image', input.files[0]);
        var card = input.closest('article');
        if (card) {
            card.classList.add('is-uploading');
        }
        fetch(cfg.wwwroot + '/theme/lmslabs/ajax/courseimage.php', {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function(r) {
                return r.json();
            }).then(function(r) {
                if (!r.ok) {
                    throw new Error(r.error || '');
                }
                var media = card && card.querySelector('.ll-ccourse-cover, .ll-ccard-media');
                if (media) {
                    var img = media.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        img.alt = '';
                        var mono = media.querySelector('span');
                        if (mono) {
                            mono.remove();
                        }
                        media.insertBefore(img, media.firstChild);
                    }
                    img.src = r.url + (r.url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
                }
                toast(r.message);
            }).catch(function(err) {
                toast(err.message || cfg.str.savefailed, true);
            }).finally(function() {
                if (card) {
                    card.classList.remove('is-uploading');
                }
                input.value = '';
            });
    });

    // Site admin menu (inside courses).
    var adm = document.querySelector('[data-ll-adm]');
    if (adm) {
        var admBtn = adm.querySelector('[data-ll-adm-toggle]');
        var admMenu = adm.querySelector('[data-ll-adm-menu]');
        admBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            admMenu.hidden = !admMenu.hidden;
            admBtn.setAttribute('aria-expanded', admMenu.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function(e) {
            if (!admMenu.hidden && !adm.contains(e.target)) {
                admMenu.hidden = true;
                admBtn.setAttribute('aria-expanded', 'false');
            }
        });
        adm.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                admMenu.hidden = true;
                admBtn.focus();
            }
        });
    }

    // Avatar menu.
    var um = document.querySelector('[data-ll-um]');
    if (um) {
        var umBtn = um.querySelector('[data-ll-um-toggle]');
        var umMenu = um.querySelector('[data-ll-um-menu]');
        var umSet = function(open) {
            umMenu.hidden = !open;
            umBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var first = umMenu.querySelector('.ll-um-item');
                if (first) {
                    first.focus();
                }
            }
        };
        umBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            umSet(umMenu.hidden);
        });
        document.addEventListener('click', function(e) {
            if (!umMenu.hidden && !um.contains(e.target)) {
                umSet(false);
            }
        });
        um.addEventListener('keydown', function(e) {
            var items = Array.prototype.slice.call(umMenu.querySelectorAll('.ll-um-item'));
            var i = items.indexOf(document.activeElement);
            if (e.key === 'Escape') {
                umSet(false);
                umBtn.focus();
            } else if (e.key === 'ArrowDown' && !umMenu.hidden) {
                e.preventDefault();
                items[(i + 1) % items.length].focus();
            } else if (e.key === 'ArrowUp' && !umMenu.hidden) {
                e.preventDefault();
                items[(i - 1 + items.length) % items.length].focus();
            }
        });
    }

    // ---------- Top bar shortcuts ----------
    // Each person pins pages from their own role-aware menus, with an icon and tooltip of their choice.
    var sc = document.querySelector('[data-ll-sc]');
    if (sc && cfg.shortcuts) {
        var scBtn = sc.querySelector('[data-ll-sc-toggle]');
        var scPop = sc.querySelector('[data-ll-sc-pop]');
        var scBar = sc.querySelector('[data-ll-sc-bar]');
        var scData = null;
        var scForm = null;
        var scBusy = false;

        var scPost = function(action, list) {
            var fd = new FormData();
            fd.append('sesskey', cfg.sesskey);
            fd.append('action', action);
            fd.append('courseid', cfg.shortcuts.courseid || 0);
            (list || []).forEach(function(s, n) {
                fd.append('k[' + n + ']', s.k);
                fd.append('i[' + n + ']', s.i);
                fd.append('t[' + n + ']', s.t || '');
            });
            return fetch(cfg.shortcuts.url, {method: 'POST', body: fd, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    if (!res || !res.ok) {
                        throw new Error('fail');
                    }
                    return res;
                });
        };

        var scIcon = function(name) {
            return (scData && scData.icons[name]) || (scData && scData.icons.link) || '';
        };

        var scItem = function(key) {
            for (var n = 0; n < scData.items.length; n++) {
                if (scData.items[n].k === key) {
                    return scData.items[n];
                }
            }
            return null;
        };

        var scRenderBar = function(bar) {
            var here = window.location.href.split('#')[0];
            scBar.innerHTML = bar.map(function(b) {
                var active = b.url === here;
                return '<a class="ll-iconbtn ll-sc-link' + (active ? ' is-active' : '') + '" href="' + escapeHtml(b.url) +
                    '" aria-label="' + escapeHtml(b.tip) + '" data-sc-tip="' + escapeHtml(b.tip) + '"' +
                    (active ? ' aria-current="page"' : '') + '>' + b.icon + '</a>';
            }).join('');
        };

        var scSave = function(list) {
            if (scBusy) {
                return;
            }
            scBusy = true;
            scPost('save', list)
                .then(function(res) {
                    scData.saved = res.saved;
                    scRenderBar(res.bar);
                    toast(res.message || str('sc_saved'));
                    scForm = null;
                    scRender();
                })
                .catch(function() {
                    toast(str('sc_failed'), true);
                })
                .then(function() {
                    scBusy = false;
                });
        };

        var scHead = function(title, sub) {
            return '<div class="ll-pop-head ll-sc-head"><div><div class="ll-pop-title">' + escapeHtml(title) + '</div>' +
                (sub ? '<div class="ll-pop-sub">' + escapeHtml(sub) + '</div>' : '') + '</div>' +
                '<button type="button" class="ll-iconbtn ll-sc-x" data-sc="close" aria-label="' +
                escapeHtml(str('sc_close')) + '">' + scData.ui.x + '</button></div>';
        };

        var scRenderList = function() {
            var saved = scData.saved;
            var html = scHead(str('sc_title'), str('sc_help', saved.length + ' / ' + scData.max));
            if (!saved.length) {
                html += '<p class="ll-sc-empty">' + escapeHtml(str('sc_empty')) + '</p>';
            } else {
                html += '<ul class="ll-sc-list">' + saved.map(function(s, n) {
                    var note = '';
                    if (s.c && !s.here) {
                        note = str('sc_incourse_hint');
                    } else if (!s.here) {
                        note = str('sc_unavailable');
                    }
                    return '<li class="ll-sc-row' + (s.here ? '' : ' is-away') + '">' +
                        '<span class="ll-sc-ico">' + scIcon(s.i) + '</span>' +
                        '<span class="ll-sc-name"><strong>' + escapeHtml(s.t) + '</strong>' +
                        (s.c ? '<em class="ll-sc-tag">' + escapeHtml(str('sc_incourse')) + '</em>' : '') +
                        (note ? '<small>' + escapeHtml(note) + '</small>' : '') + '</span>' +
                        '<span class="ll-sc-acts">' +
                        '<button type="button" class="ll-sc-act" data-sc="up" data-n="' + n + '"' + (n === 0 ? ' disabled' : '') +
                        ' aria-label="' + escapeHtml(str('sc_up')) + '" title="' + escapeHtml(str('sc_up')) + '">&larr;</button>' +
                        '<button type="button" class="ll-sc-act" data-sc="down" data-n="' + n + '"' +
                        (n === saved.length - 1 ? ' disabled' : '') + ' aria-label="' + escapeHtml(str('sc_down')) +
                        '" title="' + escapeHtml(str('sc_down')) + '">&rarr;</button>' +
                        '<button type="button" class="ll-sc-act" data-sc="edit" data-n="' + n + '"' +
                        (s.here ? '' : ' disabled') + '>' + escapeHtml(str('sc_edit')) + '</button>' +
                        '<button type="button" class="ll-sc-act is-danger" data-sc="remove" data-n="' + n + '">' +
                        escapeHtml(str('sc_remove')) + '</button>' +
                        '</span></li>';
                }).join('') + '</ul>';
            }
            if (saved.length >= scData.max) {
                html += '<p class="ll-sc-note">' + escapeHtml(str('sc_full', scData.max)) + '</p>';
            } else {
                html += '<button type="button" class="ll-sc-addbtn" data-sc="new">' + scData.ui.plus + '<span>' +
                    escapeHtml(str('sc_add')) + '</span></button>';
            }
            scPop.innerHTML = html;
        };

        var scRenderPicks = function() {
            var box = scPop.querySelector('[data-sc-picks]');
            if (!box) {
                return;
            }
            var q = scForm.q.toLowerCase();
            var html = '';
            var group = null;
            var count = 0;
            scData.items.forEach(function(it) {
                if (q && (it.n + ' ' + it.g).toLowerCase().indexOf(q) === -1) {
                    return;
                }
                if (it.g !== group) {
                    group = it.g;
                    html += '<div class="ll-sc-pickgroup">' + escapeHtml(group) + '</div>';
                }
                count++;
                var on = it.k === scForm.k;
                html += '<button type="button" class="ll-sc-pick' + (on ? ' is-on' : '') + '" data-sc="pick" data-k="' +
                    escapeHtml(it.k) + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + it.s +
                    '<span>' + escapeHtml(it.n) + '</span></button>';
            });
            box.innerHTML = count ? html : '<p class="ll-sc-empty">' + escapeHtml(str('sc_nomatch')) + '</p>';
        };

        var scRenderForm = function() {
            var html = scHead(scForm.n < 0 ? str('sc_add') : str('sc_edit'), '');
            html += '<div class="ll-sc-field"><label class="ll-sc-label" for="ll-sc-q">' + escapeHtml(str('sc_linkto')) + '</label>' +
                '<input type="search" id="ll-sc-q" class="ll-sc-input" data-sc-q autocomplete="off" placeholder="' +
                escapeHtml(str('sc_search')) + '" value="' + escapeHtml(scForm.q) + '">' +
                '<div class="ll-sc-picks" data-sc-picks role="group"></div>' +
                (scData.incourse ? '' : '<p class="ll-sc-note">' + escapeHtml(str('sc_opencourse')) + '</p>') + '</div>';
            html += '<div class="ll-sc-field"><div class="ll-sc-label">' + escapeHtml(str('sc_icon')) + '</div>' +
                '<div class="ll-sc-icons" role="radiogroup" aria-label="' + escapeHtml(str('sc_icon')) + '">' +
                Object.keys(scData.icons).map(function(name) {
                    var on = name === scForm.i;
                    return '<button type="button" class="ll-sc-iconopt' + (on ? ' is-on' : '') + '" data-sc="icon" data-i="' +
                        name + '" role="radio" aria-checked="' + (on ? 'true' : 'false') + '" aria-label="' + name + '">' +
                        scData.icons[name] + '</button>';
                }).join('') + '</div></div>';
            html += '<div class="ll-sc-field"><label class="ll-sc-label" for="ll-sc-t">' + escapeHtml(str('sc_tooltip')) + '</label>' +
                '<div class="ll-sc-tiprow"><span class="ll-sc-preview" data-sc-preview>' + scIcon(scForm.i) + '</span>' +
                '<input type="text" id="ll-sc-t" class="ll-sc-input" data-sc-t maxlength="' + scData.tipmax + '" value="' +
                escapeHtml(scForm.t) + '"></div><small class="ll-sc-hint">' + escapeHtml(str('sc_tooltip_help')) + '</small></div>';
            html += '<div class="ll-sc-foot"><button type="button" class="btn btn-secondary" data-sc="back">' +
                escapeHtml(str('sc_back')) + '</button><button type="button" class="btn btn-primary" data-sc="save">' +
                escapeHtml(str('sc_save')) + '</button></div>';
            scPop.innerHTML = html;
            scRenderPicks();
        };

        // Update the open form in place, so the page list keeps its scroll position.
        var scSync = function() {
            scPop.querySelectorAll('.ll-sc-pick').forEach(function(o) {
                var on = o.getAttribute('data-k') === scForm.k;
                o.classList.toggle('is-on', on);
                o.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            scPop.querySelectorAll('.ll-sc-iconopt').forEach(function(o) {
                var on = o.getAttribute('data-i') === scForm.i;
                o.classList.toggle('is-on', on);
                o.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            var pv = scPop.querySelector('[data-sc-preview]');
            if (pv) {
                pv.innerHTML = scIcon(scForm.i);
            }
            var t = scPop.querySelector('[data-sc-t]');
            if (t && t.value !== scForm.t) {
                t.value = scForm.t;
            }
        };

        var scRender = function() {
            if (scForm) {
                scRenderForm();
            } else {
                scRenderList();
            }
        };

        var scOpen = function(open) {
            scPop.hidden = !open;
            scBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open) {
                scForm = null;
                return;
            }
            if (scData) {
                scRender();
                return;
            }
            scPop.innerHTML = '<p class="ll-sc-empty">' + escapeHtml(str('sc_loading')) + '</p>';
            scPost('load')
                .then(function(res) {
                    scData = res;
                    scRender();
                })
                .catch(function() {
                    scPop.innerHTML = '<p class="ll-sc-empty">' + escapeHtml(str('sc_failed')) + '</p>';
                });
        };

        scBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            scOpen(scPop.hidden);
        });
        document.addEventListener('click', function(e) {
            // Re-rendering replaces the clicked node, so a detached target was inside the popover.
            if (!scPop.hidden && document.contains(e.target) && !sc.contains(e.target)) {
                scOpen(false);
            }
        });
        sc.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !scPop.hidden) {
                scOpen(false);
                scBtn.focus();
            }
        });
        scPop.addEventListener('input', function(e) {
            if (!scForm) {
                return;
            }
            if (e.target.hasAttribute('data-sc-q')) {
                scForm.q = e.target.value;
                scRenderPicks();
            } else if (e.target.hasAttribute('data-sc-t')) {
                scForm.t = e.target.value;
                scForm.tipset = true;
            }
        });
        scPop.addEventListener('click', function(e) {
            var b = e.target.closest('[data-sc]');
            if (!b || b.disabled) {
                return;
            }
            var act = b.getAttribute('data-sc');
            var n = parseInt(b.getAttribute('data-n'), 10);
            var list = scData ? scData.saved.slice() : [];
            if (act === 'close') {
                scOpen(false);
                scBtn.focus();
            } else if (act === 'up' || act === 'down') {
                var to = act === 'up' ? n - 1 : n + 1;
                var moved = list.splice(n, 1)[0];
                list.splice(to, 0, moved);
                scSave(list);
            } else if (act === 'remove') {
                list.splice(n, 1);
                scSave(list);
            } else if (act === 'new') {
                scForm = {n: -1, k: '', i: 'link', t: '', q: '', iconset: false, tipset: false};
                scRender();
                var q = scPop.querySelector('[data-sc-q]');
                if (q) {
                    q.focus();
                }
            } else if (act === 'edit') {
                var s = list[n];
                scForm = {n: n, k: s.k, i: s.i, t: s.t, q: '', iconset: true, tipset: true};
                scRender();
            } else if (act === 'back') {
                scForm = null;
                scRender();
            } else if (act === 'pick') {
                var it = scItem(b.getAttribute('data-k'));
                if (!it) {
                    return;
                }
                scForm.k = it.k;
                if (!scForm.iconset) {
                    scForm.i = scData.icons[it.i] ? it.i : 'link';
                }
                if (!scForm.tipset) {
                    scForm.t = it.n;
                }
                scSync();
            } else if (act === 'icon') {
                scForm.i = b.getAttribute('data-i');
                scForm.iconset = true;
                scSync();
            } else if (act === 'save') {
                if (!scForm.k) {
                    toast(str('sc_pick'), true);
                    return;
                }
                var entry = {k: scForm.k, i: scForm.i, t: (scForm.t || '').trim()};
                var dupe = -1;
                list.forEach(function(x, idx) {
                    if (x.k === entry.k) {
                        dupe = idx;
                    }
                });
                if (scForm.n >= 0) {
                    list[scForm.n] = entry;
                    if (dupe >= 0 && dupe !== scForm.n) {
                        list.splice(dupe, 1);
                    }
                } else if (dupe >= 0) {
                    list[dupe] = entry;
                } else {
                    list.push(entry);
                }
                scSave(list);
            }
        });
    }

    // Messages: the top bar icon and every "Message" button open the full-page inbox instead of Moodle's drawer.
    document.addEventListener('click', function(e) {
        var t = e.target.closest ? e.target.closest('[id^="message-drawer-toggle"], #message-user-button, ' +
            '[data-action="message-user"], a[href*="/message/index.php"]') : null;
        if (!t || e.ctrlKey || e.metaKey || e.shiftKey || !cfg.wwwroot) {
            return;
        }
        var id = t.getAttribute('data-userid') || '';
        var href = t.getAttribute('href') || '';
        var m = href.match(/[?&]id=(\d+)/);
        if (!id && m) {
            id = m[1];
        }
        e.preventDefault();
        e.stopPropagation();
        window.location.href = cfg.wwwroot + '/theme/lmslabs/messages.php' + (id ? '?id=' + id : '');
    }, true);

    // Format sync, small screens: Boost's backdrop sits under our frame, so a tap outside the
    // course index closes it here instead.
    if (body.classList.contains('ll-formatsync')) {
        document.addEventListener('click', function(e) {
            var drawer = document.getElementById('theme_boost-drawers-courseindex');
            if (!drawer || !drawer.classList.contains('show') || window.innerWidth >= 992 ||
                    drawer.contains(e.target) || e.target.closest('[data-toggler="drawers"]')) {
                return;
            }
            var close = drawer.querySelector('[data-action="closedrawer"]');
            if (close) {
                close.click();
            }
        });
    }
};

    // "Continue where you left off": animate the ring, bar and number once, then stagger the activity circles in.
    var resume = function() {
        var card = document.querySelector('[data-ll-resume]');
        if (!card) {
            return;
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var ring = card.querySelector('.ll-rr-bar');
        var fill = card.querySelector('.ll-bar-fill');
        var num = card.querySelector('[data-countup]');
        var run = function() {
            card.classList.add('is-live');
            if (ring) {
                ring.style.strokeDashoffset = ring.getAttribute('data-dash');
            }
            if (fill) {
                fill.style.width = fill.getAttribute('data-width') + '%';
            }
            if (num) {
                var target = parseInt(num.getAttribute('data-countup'), 10) || 0;
                if (reduce || !target) {
                    num.textContent = target;
                    return;
                }
                var t0 = null;
                var step = function(ts) {
                    t0 = t0 === null ? ts : t0;
                    var t = Math.min(1, (ts - t0) / 1200);
                    num.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }
        };
        if ('IntersectionObserver' in window && !reduce) {
            var io = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting) {
                    io.disconnect();
                    run();
                }
            }, {threshold: 0.3});
            io.observe(card);
        } else {
            run();
        }
    };


    /**
     * Plugin pages sometimes show the same licence notice twice. Keep one and mark it as a lock notice.
     */
    var tidyNotices = function() {
        var seen = {};
        document.querySelectorAll('#region-main .alert, #user-notifications .alert').forEach(function(el) {
            var text = el.textContent.replace(/\s+/g, ' ').trim();
            if (!text) {
                return;
            }
            if (seen[text]) {
                el.remove();
                return;
            }
            seen[text] = true;
            if (/requires .*credits to unlock|unlock this plugin/i.test(text)) {
                el.classList.add('ll-locked');
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            start();
            resume();
            tidyNotices();
        });
    } else {
        start();
        resume();
        tidyNotices();
    }
})();
