/**
 * Premium SaaS field tips: a small (i) beside every field that explains it on hover or focus.
 *
 * Sources, in order:
 *  - data-ll-tip="..." on any element in the theme's own pages;
 *  - Moodle's own help buttons in forms and settings (shown on hover instead of needing a click);
 *  - the theme's tip_<field> strings for common Moodle form fields that have no help of their own.
 *
 * A footer switch turns tips off for this browser.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var KEY = 'll-tips';
    var body;
    var tip;
    var current = null;
    var hideTimer = null;

    var store = {
        get: function() {
            try {
                return window.localStorage.getItem(KEY);
            } catch (e) {
                return null;
            }
        },
        set: function(v) {
            try {
                window.localStorage.setItem(KEY, v);
            } catch (e) {
                // Not remembered: tips simply follow the default next time.
            }
        }
    };

    var on = function() {
        return !body.classList.contains('ll-tips-off');
    };

    var place = function(target) {
        var r = target.getBoundingClientRect();
        var tw = tip.offsetWidth;
        var th = tip.offsetHeight;
        var left = Math.min(Math.max(8, r.left + r.width / 2 - tw / 2), window.innerWidth - tw - 8);
        var top = r.top - th - 10;
        tip.classList.toggle('is-below', top < 8);
        if (top < 8) {
            top = r.bottom + 10;
        }
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
        tip.style.setProperty('--ll-tip-x', (r.left + r.width / 2 - left) + 'px');
    };

    var show = function(target) {
        if (!on()) {
            return;
        }
        var html = target.getAttribute('data-ll-tip-html');
        var text = target.getAttribute('data-ll-tip');
        if (!html && !text) {
            return;
        }
        window.clearTimeout(hideTimer);
        current = target;
        if (html) {
            tip.innerHTML = html;
        } else {
            tip.textContent = text;
        }
        tip.hidden = false;
        target.setAttribute('aria-describedby', 'll-tip');
        place(target);
        tip.classList.add('is-on');
    };

    var hide = function() {
        hideTimer = window.setTimeout(function() {
            tip.classList.remove('is-on');
            tip.hidden = true;
            if (current) {
                current.removeAttribute('aria-describedby');
            }
            current = null;
        }, 80);
    };

    /**
     * A focusable (i) for a label or heading.
     *
     * @param {string} text plain text
     * @param {string} html optional trusted HTML from Moodle's own help strings
     * @return {HTMLElement}
     */
    var icon = function(text, html) {
        var i = document.createElement('span');
        i.className = 'll-tipi';
        i.tabIndex = 0;
        i.setAttribute('role', 'button');
        i.setAttribute('aria-label', text.length > 120 ? text.slice(0, 117) + '...' : text);
        if (html) {
            i.setAttribute('data-ll-tip-html', html);
        } else {
            i.setAttribute('data-ll-tip', text);
        }
        i.textContent = '?';
        return i;
    };

    var decorate = function(root, map) {
        // 1. Theme pages: labels carrying data-ll-tip get an (i); controls show the tip themselves.
        root.querySelectorAll('[data-ll-tip]:not([data-ll-tip-done])').forEach(function(el) {
            el.setAttribute('data-ll-tip-done', '1');
            if (el.classList.contains('ll-tipi') || el.hasAttribute('data-ll-tip-self')) {
                return;
            }
            var text = el.getAttribute('data-ll-tip');
            el.removeAttribute('data-ll-tip');
            var host = el.querySelector('[data-ll-tip-host]') || el;
            host.appendChild(icon(text));
        });

        // 2. Moodle's help buttons: show their text on hover too.
        root.querySelectorAll('a[data-bs-toggle="popover"], a[data-toggle="popover"], .btn-link[data-bs-content]')
            .forEach(function(a) {
                if (a.hasAttribute('data-ll-tip-done')) {
                    return;
                }
                var html = a.getAttribute('data-bs-content') || a.getAttribute('data-content') || '';
                if (!html) {
                    return;
                }
                a.setAttribute('data-ll-tip-done', '1');
                // Drop Moodle's "More help" link markup from the hover view; the click popover keeps it.
                var clean = html.replace(/<div class="helpdoclink">[\s\S]*?<\/div>/g, '');
                a.setAttribute('data-ll-tip-html', clean);
                a.classList.add('ll-tiphelp');
            });

        // 3. Common Moodle fields without help of their own.
        if (!map) {
            return;
        }
        root.querySelectorAll('.fitem, .form-item').forEach(function(item) {
            if (item.hasAttribute('data-ll-tip-done') || item.querySelector('.ll-tiphelp, .ll-tipi')) {
                return;
            }
            var control = item.querySelector('input[name], select[name], textarea[name]');
            if (!control) {
                return;
            }
            var name = control.getAttribute('name').replace(/\[.*$/, '').replace(/^s__/, '').toLowerCase();
            var text = map[name];
            if (!text) {
                return;
            }
            item.setAttribute('data-ll-tip-done', '1');
            var label = item.querySelector('.col-form-label label, .col-form-label p, label.form-label, .form-label label, ' +
                '.col-form-label, legend');
            if (label) {
                label.appendChild(icon(text));
            }
        });
    };

    var slug = function(text) {
        return (text || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    };

    var scopeName = function() {
        var m = window.location.pathname.match(/\/(?:local|enrol|blocks|mod|theme)\/([a-z0-9_]+)\//);
        return m ? m[1] : '';
    };

    var labelOf = function(control) {
        var l = control.id ? document.querySelector('label[for="' + control.id.replace(/"/g, '') + '"]') : null;
        if (l) {
            return l;
        }
        var box = control.closest('.mb-3, .fitem, .form-group, .form-item, [class*="col-md-"]');
        return box ? box.querySelector('label:not(.tp-emp-item), .form-label, .col-form-label') : null;
    };

    /**
     * Plugin pages: tips for fields and buttons, keyed by the plugin folder, so one plugin's "subject"
     * is never confused with another's. Keys are scope__name, scope__label_<label>, scope__btn_<button>.
     *
     * @param {HTMLElement} root
     * @param {Object} map
     */
    var decoratePlugin = function(root, map) {
        var scope = scopeName();
        if (!scope || !map) {
            return;
        }
        var main = root.querySelector('#region-main') || root;
        main.querySelectorAll('input, select, textarea').forEach(function(c) {
            if (c.hasAttribute('data-ll-tip-done') || c.type === 'hidden' || c.type === 'submit' || c.type === 'button') {
                return;
            }
            var label = labelOf(c);
            var keys = [];
            if (c.name) {
                keys.push(scope + '__' + slug(c.name));
            }
            if (c.id && c.id.indexOf('form_autocomplete') !== 0) {
                keys.push(scope + '__' + slug(c.id));
            }
            if (label) {
                keys.push(scope + '__label_' + slug(label.textContent.replace(/\?$/, '')));
            }
            var text = null;
            for (var i = 0; i < keys.length && !text; i++) {
                text = map[keys[i]];
            }
            if (!text) {
                return;
            }
            c.setAttribute('data-ll-tip-done', '1');
            if (label && !label.hasAttribute('data-ll-tip-done') && !label.querySelector('.ll-tipi, .ll-tiphelp')) {
                label.setAttribute('data-ll-tip-done', '1');
                label.appendChild(icon(text));
            } else if (!label) {
                c.parentElement.appendChild(icon(text));
            }
        });
        main.querySelectorAll('a.btn, button, input[type="submit"], .btn, .tm-btn, .tp-btn').forEach(function(b) {
            if (b.hasAttribute('data-ll-tip-done') || b.hasAttribute('data-bs-toggle')) {
                return;
            }
            var text = map[scope + '__btn_' + slug(b.value || b.textContent)];
            if (!text) {
                return;
            }
            b.setAttribute('data-ll-tip-done', '1');
            b.setAttribute('data-ll-tip', text);
            b.setAttribute('data-ll-tip-self', '1');
        });
    };

    /**
     * The page heading gets one (i) that says, in a sentence or two, what the whole page is for.
     * Keys: scope__page_<file>_<tab> (tabbed pages), scope__page_<file>, scope__page.
     *
     * @param {Object} map
     */
    var decoratePage = function(map) {
        var scope = scopeName();
        if (!scope || !map) {
            return;
        }
        var h = null;
        document.querySelectorAll('#page-header h1, .page-header-headings h1, #region-main h1, #page-content h1, .tp-page-title, .gm-title').forEach(function(el) {
            if (!h && el.offsetParent && !el.closest('.ll-pagehero, .modal, .card-deck, .ll-hero')) {
                h = el;
            }
        });
        if (!h || h.hasAttribute('data-ll-tip-done')) {
            return;
        }
        var file = window.location.pathname.split('/').pop().replace(/\.php$/, '') || 'index';
        var tab = new URLSearchParams(window.location.search).get('tab');
        var keys = [];
        if (tab) {
            keys.push(scope + '__page_' + slug(file) + '_' + slug(tab));
        }
        keys.push(scope + '__page_' + slug(file), scope + '__page');
        var text = null;
        for (var i = 0; i < keys.length && !text; i++) {
            text = map[keys[i]];
        }
        if (!text) {
            return;
        }
        h.setAttribute('data-ll-tip-done', '1');
        var i = icon(text);
        i.classList.add('ll-tipi-page');
        h.appendChild(i);
    };

    var start = function() {
        body = document.body;
        var cfgEl = document.getElementById('ll-tips-config');
        var map = null;
        if (cfgEl) {
            try {
                map = JSON.parse(cfgEl.textContent);
            } catch (e) {
                map = null;
            }
        }
        if (store.get() === 'off') {
            body.classList.add('ll-tips-off');
        }

        tip = document.createElement('div');
        tip.id = 'll-tip';
        tip.className = 'll-tip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        body.appendChild(tip);

        decorate(document, map);
        decoratePlugin(document, map);
        decoratePage(map);
        // Forms that arrive later (modals, dynamic tabs) get tips too.
        var pending = false;
        new MutationObserver(function() {
            if (pending) {
                return;
            }
            pending = true;
            window.setTimeout(function() {
                pending = false;
                decorate(document, map);
                decoratePlugin(document, map);
                decoratePage(map);
            }, 250);
        }).observe(document.body, {childList: true, subtree: true});

        var find = function(e) {
            return e.target && e.target.closest ? e.target.closest('.ll-tipi, .ll-tiphelp, [data-ll-tip-self]') : null;
        };
        document.addEventListener('mouseover', function(e) {
            var t = find(e);
            if (t && t !== current) {
                show(t);
            } else if (!t && current && !tip.contains(e.target)) {
                hide();
            }
        });
        document.addEventListener('focusin', function(e) {
            var t = find(e);
            if (t) {
                show(t);
            }
        });
        document.addEventListener('focusout', function(e) {
            if (find(e)) {
                hide();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && current) {
                hide();
            }
        });
        window.addEventListener('scroll', function() {
            if (current) {
                hide();
            }
        }, true);

        // Footer switch.
        document.querySelectorAll('[data-ll-tips-toggle]').forEach(function(btn) {
            var sync = function() {
                btn.setAttribute('aria-pressed', on() ? 'true' : 'false');
            };
            sync();
            btn.addEventListener('click', function() {
                body.classList.toggle('ll-tips-off');
                store.set(on() ? 'on' : 'off');
                hide();
                sync();
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
