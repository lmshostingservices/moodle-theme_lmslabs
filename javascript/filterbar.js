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
 * Filter bar and list selection: one pill open at a time, in-pill search, apply on close,
 * single-choice filters apply instantly, and a bulk bar that follows the row checkboxes.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        document.querySelectorAll('[data-ll-fb]').forEach(function(bar) {
            var form = bar.querySelector('[data-fb-form]');
            var pills = Array.prototype.slice.call(bar.querySelectorAll('[data-fb-pill]'));
            var snapshot = function(pill) {
                return Array.prototype.map.call(pill.querySelectorAll('input, select'), function(el) {
                    return el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value;
                }).join('|');
            };

            // Keep shareable URLs short: drop empty fields and the default sort.
            if (form) {
                var tidy = function() {
                    form.querySelectorAll('input[type=search], input[type=text], select').forEach(function(el) {
                        if (el.value === '') {
                            el.disabled = true;
                        }
                    });
                    document.querySelectorAll('select[form="' + form.id + '"]').forEach(function(el) {
                        if (el.selectedIndex === 0) {
                            el.disabled = true;
                        }
                    });
                };
                var submit = form.submit.bind(form);
                form.submit = function() {
                    tidy();
                    submit();
                };
                form.addEventListener('submit', tidy);
            }

            pills.forEach(function(pill) {
                pill.addEventListener('toggle', function() {
                    if (pill.open) {
                        pills.forEach(function(other) {
                            if (other !== pill && other.open) {
                                other.open = false;
                            }
                        });
                        pill.dataset.before = snapshot(pill);
                        var search = pill.querySelector('[data-fb-psearch]');
                        if (search) {
                            search.focus();
                        }
                        return;
                    }
                    // Closing a filter pill with changed choices applies them.
                    if (form && form.contains(pill) && pill.dataset.before !== undefined &&
                            pill.dataset.before !== snapshot(pill)) {
                        form.submit();
                    }
                });

                var search = pill.querySelector('[data-fb-psearch]');
                if (search) {
                    search.addEventListener('input', function() {
                        var q = search.value.trim().toLowerCase();
                        pill.querySelectorAll('.ll-fb-opt').forEach(function(opt) {
                            opt.hidden = q !== '' && opt.textContent.toLowerCase().indexOf(q) === -1;
                        });
                        pill.querySelectorAll('.ll-fb-group').forEach(function(head) {
                            var el = head.nextElementSibling;
                            var any = false;
                            while (el && !el.classList.contains('ll-fb-group')) {
                                if (!el.hidden) {
                                    any = true;
                                }
                                el = el.nextElementSibling;
                            }
                            head.hidden = !any;
                        });
                    });
                    search.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                        }
                    });
                }

                var clear = pill.querySelector('[data-fb-clear]');
                if (clear) {
                    clear.addEventListener('click', function() {
                        pill.querySelectorAll('input[type=checkbox], input[type=radio]').forEach(function(el) {
                            el.checked = false;
                        });
                        pill.querySelectorAll('select, input[type=text]').forEach(function(el) {
                            el.value = '';
                        });
                        if (form && form.contains(pill)) {
                            form.submit();
                        }
                    });
                }
            });


            // Click outside or Escape closes the open pill.
            document.addEventListener('click', function(e) {
                pills.forEach(function(pill) {
                    if (pill.open && !pill.contains(e.target)) {
                        pill.open = false;
                    }
                });
            });
            bar.addEventListener('keydown', function(e) {
                if (e.key !== 'Escape') {
                    return;
                }
                pills.forEach(function(pill) {
                    if (pill.open) {
                        pill.open = false;
                        pill.querySelector('summary').focus();
                    }
                });
            });

            // "/" focuses the search box, as in most admin tools.
            var q = bar.querySelector('[data-fb-q]');
            document.addEventListener('keydown', function(e) {
                if (e.key === '/' && q && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) &&
                        !document.activeElement.isContentEditable) {
                    e.preventDefault();
                    q.focus();
                }
            });
        });

        // Single-choice filters and sort menus apply as soon as they change.
        document.querySelectorAll('[data-fb-instant]').forEach(function(el) {
            el.addEventListener('change', function() {
                if (el.form) {
                    el.form.submit();
                }
            });
        });

        // Row selection and the bulk bar.
        document.querySelectorAll('[data-bulk-form]').forEach(function(form) {
            var bar = form.querySelector('[data-bulkbar]');
            var count = form.querySelector('[data-bulk-count]');
            var all = form.querySelector('[data-select-all]');
            var rows = function() {
                return Array.prototype.slice.call(form.querySelectorAll('[data-select-row]'));
            };
            var update = function() {
                var list = rows();
                var n = list.filter(function(cb) {
                    return cb.checked;
                }).length;
                if (bar) {
                    bar.hidden = n === 0;
                }
                if (count) {
                    count.textContent = n + ' ' + (n === 1 ? (form.dataset.one || 'selected') : (form.dataset.many || 'selected'));
                }
                if (all) {
                    all.checked = n > 0 && n === list.length;
                    all.indeterminate = n > 0 && n < list.length;
                }
                form.dispatchEvent(new CustomEvent('ll:selection', {detail: {n: n, total: list.length}}));
                list.forEach(function(cb) {
                    var tr = cb.closest('tr, .ll-selectable');
                    if (tr) {
                        tr.classList.toggle('is-selected', cb.checked);
                    }
                });
            };
            if (all) {
                all.addEventListener('change', function() {
                    rows().forEach(function(cb) {
                        cb.checked = all.checked;
                    });
                    update();
                });
            }
            form.addEventListener('change', function(e) {
                if (e.target.matches('[data-select-row]')) {
                    update();
                }
            });
            var clear = form.querySelector('[data-bulk-clear]');
            if (clear) {
                clear.addEventListener('click', function() {
                    rows().forEach(function(cb) {
                        cb.checked = false;
                    });
                    update();
                });
            }
            update();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
