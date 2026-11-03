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
