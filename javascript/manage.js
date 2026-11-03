// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Courses and categories: instant search and filters, collapsible categories,
 * one-click visibility, drag to reorder.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-cm]');
        if (!root) {
            return;
        }
        var str = {};
        try {
            str = JSON.parse(root.querySelector('[data-cm-str]').textContent);
        } catch (e) {
            str = {};
        }
        var ajax = root.dataset.ajax;
        var sesskey = root.dataset.sesskey;
        var toastEl = root.querySelector('[data-cm-toast]');
        var toastTimer = null;

        var toast = function(text, bad) {
            toastEl.textContent = text;
            toastEl.classList.toggle('is-bad', !!bad);
            toastEl.hidden = false;
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function() {
                toastEl.hidden = true;
            }, 2600);
        };
        var post = function(params) {
            var body = new URLSearchParams(params);
            body.append('sesskey', sesskey);
            return fetch(ajax, {method: 'POST', body: body, credentials: 'same-origin'}).then(function(r) {
                return r.json();
            }).then(function(j) {
                if (!j || !j.ok) {
                    throw new Error(j && j.error ? j.error : 'failed');
                }
                return j;
            });
        };

        // Filters and search.
        var filter = 'all';
        var q = '';
        var apply = function() {
            root.querySelectorAll('.ll-cat').forEach(function(cat) {
                var shown = 0;
                cat.querySelectorAll('.ll-ccourse').forEach(function(card) {
                    var ok = (q === '' || card.dataset.search.indexOf(q) !== -1) &&
                        (filter === 'all' || (filter === 'visible' && card.dataset.visible === '1') ||
                         (filter === 'hidden' && card.dataset.visible === '0') ||
                         (filter === 'empty' && card.dataset.empty === '1'));
                    card.hidden = !ok;
                    if (ok) {
                        shown++;
                    }
                });
                var filtering = q !== '' || filter !== 'all';
                var add = cat.querySelector('.ll-ccourse-add');
                if (add) {
                    add.hidden = filtering;
                }
                cat.hidden = filtering && shown === 0;
                var nomatch = cat.querySelector('[data-cm-nomatch]');
                if (nomatch) {
                    nomatch.hidden = true;
                }
            });
        };
        root.querySelectorAll('[data-cm-filter]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                filter = btn.dataset.cmFilter;
                root.querySelectorAll('[data-cm-filter]').forEach(function(b) {
                    b.classList.toggle('is-on', b === btn);
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
                apply();
            });
        });
        var search = root.querySelector('[data-cm-q]');
        search.addEventListener('input', function() {
            q = search.value.trim().toLowerCase();
            apply();
        });

        // Collapse / expand, remembered per category.
        var key = function(cat) {
            return 'll-cm-closed-' + cat.dataset.cat;
        };
        var setOpen = function(cat, open) {
            cat.classList.toggle('is-closed', !open);
            cat.querySelector('[data-cm-collapse]').setAttribute('aria-expanded', open ? 'true' : 'false');
            try {
                if (open) {
                    localStorage.removeItem(key(cat));
                } else {
                    localStorage.setItem(key(cat), '1');
                }
            } catch (e) {
                // Storage unavailable: state just isn't remembered.
            }
        };
        root.querySelectorAll('.ll-cat').forEach(function(cat) {
            var closed = false;
            try {
                closed = localStorage.getItem(key(cat)) === '1';
            } catch (e) {
                closed = false;
            }
            if (closed) {
                setOpen(cat, false);
            }
            cat.querySelector('[data-cm-collapse]').addEventListener('click', function() {
                setOpen(cat, cat.classList.contains('is-closed'));
                syncToggleAll();
            });
        });
        var toggleAll = root.querySelector('[data-cm-toggleall]');
        var syncToggleAll = function() {
            var anyOpen = root.querySelector('.ll-cat:not(.is-closed)');
            toggleAll.textContent = anyOpen ? (str.collapseall || 'Collapse all') : (str.expandall || 'Expand all');
        };
        toggleAll.addEventListener('click', function() {
            var anyOpen = !!root.querySelector('.ll-cat:not(.is-closed)');
            root.querySelectorAll('.ll-cat').forEach(function(cat) {
                setOpen(cat, !anyOpen);
            });
            syncToggleAll();
        });
        syncToggleAll();

        // Visibility switch.
        var counts = {
            visible: root.querySelector('[data-cm-count="visible"]'),
            hidden: root.querySelector('[data-cm-count="hidden"]')
        };
        var bump = function(visible) {
            var v = parseInt(counts.visible.textContent, 10) + (visible ? 1 : -1);
            var h = parseInt(counts.hidden.textContent, 10) + (visible ? -1 : 1);
            counts.visible.textContent = v;
            counts.hidden.textContent = h;
        };
        root.addEventListener('change', function(e) {
            var input = e.target.closest('[data-cm-visibility]');
            if (!input) {
                return;
            }
            var card = input.closest('.ll-ccourse');
            var show = input.checked;
            var name = card.querySelector('.ll-ccourse-name').textContent;
            card.classList.toggle('is-hidden', !show);
            card.dataset.visible = show ? '1' : '0';
            input.disabled = true;
            post({action: 'visibility', id: card.dataset.course, show: show ? 1 : 0}).then(function() {
                bump(show);
                toast((show ? str.nowvisible : str.nowhidden).replace('{name}', name));
            }).catch(function() {
                input.checked = !show;
                card.classList.toggle('is-hidden', show);
                card.dataset.visible = show ? '0' : '1';
                toast(str.error, true);
            }).then(function() {
                input.disabled = false;
            });
        });

        // Drag to reorder within a category.
        var dragging = null;
        root.querySelectorAll('.ll-cat[data-canorder] [data-cm-grid]').forEach(function(grid) {
            var cat = grid.closest('.ll-cat');
            grid.addEventListener('dragstart', function(e) {
                var card = e.target.closest('.ll-ccourse');
                if (!card) {
                    return;
                }
                dragging = card;
                card.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.dataset.course);
            });
            grid.addEventListener('dragover', function(e) {
                if (!dragging || dragging.parentNode !== grid) {
                    return;
                }
                e.preventDefault();
                var over = e.target.closest('.ll-ccourse');
                if (!over || over === dragging) {
                    return;
                }
                var r = over.getBoundingClientRect();
                var after = (e.clientX - r.left) > r.width / 2;
                grid.insertBefore(dragging, after ? over.nextSibling : over);
            });
            grid.addEventListener('dragend', function() {
                if (!dragging) {
                    return;
                }
                dragging.classList.remove('is-dragging');
                dragging = null;
                var ids = Array.prototype.map.call(grid.querySelectorAll('.ll-ccourse'), function(c) {
                    return parseInt(c.dataset.course, 10);
                });
                var before = grid.dataset.order || '';
                if (before === ids.join(',')) {
                    return;
                }
                grid.dataset.order = ids.join(',');
                post({action: 'reorder', category: cat.dataset.cat, ids: JSON.stringify(ids)}).then(function() {
                    toast(str.ordersaved);
                }).catch(function() {
                    toast(str.error, true);
                });
            });
            grid.dataset.order = Array.prototype.map.call(grid.querySelectorAll('.ll-ccourse'), function(c) {
                return c.dataset.course;
            }).join(',');
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
