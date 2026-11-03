/**
 * My courses: progress animation and the program editor.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- Progress rings, bars and count-up ----------
    var animateCard = function(card) {
        var bar = card.querySelector('.ll-ring-bar');
        if (bar) {
            bar.style.strokeDashoffset = bar.getAttribute('data-dash');
        }
        var fill = card.querySelector('.ll-bar-fill');
        if (fill) {
            fill.style.width = fill.getAttribute('data-width') + '%';
        }
        card.querySelectorAll('[data-countup]').forEach(countUp);
    };
    var countUp = function(el) {
        var target = parseInt(el.getAttribute('data-countup'), 10) || 0;
        if (reduce || target === 0) {
            el.textContent = target;
            return;
        }
        var start = null;
        var duration = 1100;
        var step = function(ts) {
            if (start === null) {
                start = ts;
            }
            var t = Math.min(1, (ts - start) / duration);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(target * eased);
            if (t < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    };

    var prog = document.querySelector('[data-ll-prog]');
    if (prog) {
        var cards = prog.querySelectorAll('.ll-ccard, .ll-mc-row');
        if ('IntersectionObserver' in window && !reduce) {
            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        animateCard(entry.target);
                        io.unobserve(entry.target);
                    }
                });
            }, {threshold: 0.2});
            cards.forEach(function(c) {
                io.observe(c);
            });
        } else {
            cards.forEach(animateCard);
        }
        prog.querySelectorAll('.ll-prog-overall [data-countup], .ll-mc-summary [data-countup]').forEach(countUp);
        var ov = prog.querySelector('.ll-mc-overall .ll-ring-bar');
        if (ov) {
            window.requestAnimationFrame(function() {
                ov.style.strokeDashoffset = ov.getAttribute('data-dash');
            });
        }
        // My courses: search, status, category, sort and view, remembered per browser.
        var mc = prog.matches('[data-ll-mc]') ? prog : null;
        if (mc && mc.querySelector('[data-mc-q]')) {
            var state = {q: '', status: '', cat: '', sort: 'order', view: ''};
            try {
                Object.assign(state, JSON.parse(localStorage.getItem('ll-mc') || '{}'), {q: ''});
            } catch (e) {
                // Storage unavailable.
            }
            var views = mc.querySelectorAll('[data-view]');
            var hasPath = !!mc.querySelector('[data-mc-view="path"]');
            if (!state.view || (state.view === 'path' && !hasPath)) {
                state.view = hasPath ? 'path' : 'cards';
            }
            var save = function() {
                try {
                    localStorage.setItem('ll-mc', JSON.stringify({status: state.status, cat: state.cat, sort: state.sort, view: state.view}));
                } catch (e) {
                    // Storage unavailable.
                }
            };
            var match = function(el) {
                return (state.q === '' || el.dataset.search.indexOf(state.q) !== -1) &&
                    (state.status === '' || el.dataset.status === state.status) &&
                    (state.cat === '' || el.dataset.cat === state.cat);
            };
            var sorter = {
                order: function(a, b) {
                    return a.dataset.order - b.dataset.order;
                },
                access: function(a, b) {
                    return b.dataset.access - a.dataset.access;
                },
                name: function(a, b) {
                    return a.dataset.name.localeCompare(b.dataset.name);
                },
                progress: function(a, b) {
                    return b.dataset.percent - a.dataset.percent;
                }
            };
            var apply = function() {
                views.forEach(function(v) {
                    v.hidden = v.dataset.view !== state.view;
                });
                mc.querySelectorAll('[data-mc-view]').forEach(function(b) {
                    b.classList.toggle('is-on', b.dataset.mcView === state.view);
                    b.setAttribute('aria-pressed', b.dataset.mcView === state.view ? 'true' : 'false');
                });
                mc.querySelectorAll('[data-mc-status]').forEach(function(b) {
                    b.classList.toggle('is-on', b.dataset.mcStatus === state.status);
                    b.setAttribute('aria-pressed', b.dataset.mcStatus === state.status ? 'true' : 'false');
                });
                var shown = 0;
                var current = mc.querySelector('[data-view="' + state.view + '"]');
                current.querySelectorAll('[data-mc-item]').forEach(function(el) {
                    el.hidden = !match(el);
                    if (!el.hidden) {
                        shown++;
                        animateCard(el);
                    }
                });
                current.querySelectorAll('[data-mc-sortable]').forEach(function(box) {
                    Array.prototype.slice.call(box.querySelectorAll(':scope > [data-mc-item]'))
                        .sort(sorter[state.sort] || sorter.order).forEach(function(el) {
                            box.appendChild(el);
                        });
                });
                // Path view: hide headings with nothing left under them.
                current.querySelectorAll('.ll-prog-section').forEach(function(sec) {
                    sec.hidden = !sec.querySelector('[data-mc-item]:not([hidden])');
                });
                current.querySelectorAll('.ll-program').forEach(function(p) {
                    p.hidden = !p.querySelector('[data-mc-item]:not([hidden])');
                });
                var sortSel = mc.querySelector('[data-mc-sort]');
                sortSel.disabled = state.view === 'path';
                mc.querySelector('[data-mc-none]').hidden = shown > 0;
                save();
            };
            var q = mc.querySelector('[data-mc-q]');
            q.addEventListener('input', function() {
                state.q = q.value.trim().toLowerCase();
                apply();
            });
            mc.addEventListener('click', function(e) {
                var b = e.target.closest('[data-mc-status]');
                if (b) {
                    state.status = b.dataset.mcStatus;
                    apply();
                }
                var v = e.target.closest('[data-mc-view]');
                if (v) {
                    state.view = v.dataset.mcView;
                    apply();
                }
            });
            var cat = mc.querySelector('[data-mc-cat]');
            if (cat) {
                cat.value = state.cat;
                if (cat.value !== state.cat) {
                    state.cat = '';
                }
                cat.addEventListener('change', function() {
                    state.cat = cat.value;
                    apply();
                });
            } else {
                state.cat = '';
            }
            var sortSel = mc.querySelector('[data-mc-sort]');
            sortSel.value = state.sort;
            sortSel.addEventListener('change', function() {
                state.sort = sortSel.value;
                apply();
            });
            apply();
        }
        prog.addEventListener('click', function(e) {
            if (e.target.closest('[data-ll-locked]')) {
                e.preventDefault();
            }
        });
    }

    var post = function(url, fields) {
        var body = new FormData();
        Object.keys(fields).forEach(function(k) {
            body.append(k, fields[k]);
        });
        return fetch(url, {method: 'POST', body: body, credentials: 'same-origin'}).then(function(r) {
            return r.json();
        }).then(function(res) {
            if (!res || !res.ok) {
                throw new Error('fail');
            }
            return res;
        });
    };
    var str = function(id) {
        return (window.M && M.util && M.util.get_string) ? M.util.get_string(id, 'theme_lmslabs') : id;
    };

    // ---------- Program list ----------
    var listPage = document.querySelector('[data-ll-programs]');
    if (listPage) {
        var plist = listPage.querySelector('[data-list]');
        var saveOrder = function() {
            var ids = Array.prototype.map.call(plist.querySelectorAll('[data-id]'), function(li) {
                return li.getAttribute('data-id');
            });
            post(listPage.getAttribute('data-save'), {
                sesskey: listPage.getAttribute('data-sesskey'), action: 'reorder', ids: JSON.stringify(ids)
            });
        };
        listPage.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-act]');
            if (!btn) {
                return;
            }
            var card = btn.closest('[data-id]');
            var act = btn.getAttribute('data-act');
            if (act === 'pup' && card.previousElementSibling) {
                plist.insertBefore(card, card.previousElementSibling);
                saveOrder();
                btn.focus();
            } else if (act === 'pdown' && card.nextElementSibling) {
                plist.insertBefore(card.nextElementSibling, card);
                saveOrder();
                btn.focus();
            } else if (act === 'pdelete') {
                if (!window.confirm(str('confirmdeleteprogram'))) {
                    return;
                }
                post(listPage.getAttribute('data-save'), {
                    sesskey: listPage.getAttribute('data-sesskey'), action: 'delete', id: card.getAttribute('data-id')
                }).then(function() {
                    card.remove();
                });
            }
        });
    }

    // ---------- Program editor ----------
    var editor = document.querySelector('[data-ll-pedit]');
    if (!editor) {
        return;
    }
    var status = editor.querySelector('[data-status]');
    var groupsWrap = editor.querySelector('[data-groups]');
    var picker = editor.querySelector('[data-picker]');
    var dirty = false;
    var emptyText = editor.querySelector('template[data-group-template]').content.querySelector('.ll-plist-empty').textContent;

    var syncEmpty = function() {
        editor.querySelectorAll('[data-list]').forEach(function(list) {
            var rows = list.querySelectorAll('.ll-prow').length;
            var empty = list.querySelector('.ll-plist-empty');
            if (!rows && !empty) {
                var li = document.createElement('li');
                li.className = 'll-plist-empty';
                li.textContent = emptyText;
                list.appendChild(li);
            } else if (rows && empty) {
                empty.remove();
            }
        });
        // Mark picker items already in the program.
        var inprog = {};
        editor.querySelectorAll('.ll-prow').forEach(function(r) {
            inprog[r.getAttribute('data-course')] = true;
        });
        editor.querySelectorAll('[data-pick]').forEach(function(li) {
            li.classList.toggle('is-added', !!inprog[li.getAttribute('data-pick')]);
        });
    };
    var markDirty = function() {
        dirty = true;
        status.textContent = '';
        syncEmpty();
    };

    // Audience: everyone vs specific.
    var rulesBox = editor.querySelector('[data-aud-rules]');
    editor.querySelectorAll('input[name="aud"]').forEach(function(r) {
        r.addEventListener('change', function() {
            rulesBox.hidden = r.value === 'everyone' && r.checked;
            markDirty();
        });
    });
    // Checklist filters.
    editor.querySelectorAll('[data-filter]').forEach(function(input) {
        input.addEventListener('input', function() {
            var q = input.value.trim().toLowerCase();
            input.parentNode.querySelectorAll('[data-checklist] label').forEach(function(l) {
                l.hidden = q !== '' && l.textContent.toLowerCase().indexOf(q) === -1;
            });
        });
    });
    // Group-name chips.
    var chipInput = editor.querySelector('[data-chip-input]');
    var addChip = function(value) {
        value = value.trim();
        if (!value) {
            return;
        }
        var exists = Array.prototype.some.call(editor.querySelectorAll('.ll-chip-tag'), function(c) {
            return c.getAttribute('data-value').toLowerCase() === value.toLowerCase();
        });
        if (!exists) {
            var chip = document.createElement('span');
            chip.className = 'll-chip-tag';
            chip.setAttribute('data-value', value);
            chip.textContent = value;
            var x = document.createElement('button');
            x.type = 'button';
            x.setAttribute('data-act', 'chipx');
            x.setAttribute('aria-label', 'Remove');
            x.innerHTML = '&times;';
            chip.appendChild(x);
            chipInput.parentNode.insertBefore(chip, chipInput);
        }
        chipInput.value = '';
        markDirty();
    };
    if (chipInput) {
        chipInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addChip(chipInput.value);
            } else if (e.key === 'Backspace' && chipInput.value === '') {
                var last = chipInput.previousElementSibling;
                if (last && last.classList.contains('ll-chip-tag')) {
                    last.remove();
                    markDirty();
                }
            }
        });
        chipInput.addEventListener('blur', function() {
            addChip(chipInput.value);
        });
    }

    // Picker.
    var pickerSearch = picker.querySelector('[data-picker-search]');
    pickerSearch.addEventListener('input', function() {
        var q = pickerSearch.value.trim().toLowerCase();
        picker.querySelectorAll('[data-pick]').forEach(function(li) {
            li.hidden = q !== '' && li.getAttribute('data-search').toLowerCase().indexOf(q) === -1;
        });
    });
    var addCourse = function(li) {
        var id = li.getAttribute('data-pick');
        if (editor.querySelector('.ll-prow[data-course="' + id + '"]')) {
            return;
        }
        var row = editor.querySelector('template[data-row-template]').content.querySelector('.ll-prow').cloneNode(true);
        row.setAttribute('data-course', id);
        row.classList.remove('is-hidden');
        row.querySelector('.ll-prow-name strong').textContent = li.getAttribute('data-name');
        row.querySelector('.ll-prow-name span').textContent = li.getAttribute('data-short');
        row.querySelector('[data-f="req"]').value = '0';
        row.querySelector('[data-f="days"]').value = '0';
        row.querySelector('[data-f="hours"]').value = '0';
        var lists = editor.querySelectorAll('[data-list]');
        lists[lists.length - 1].appendChild(row);
        markDirty();
    };

    // Drag and drop.
    var dragging = null;
    editor.addEventListener('dragstart', function(e) {
        var row = e.target.closest('.ll-prow');
        if (!row) {
            return;
        }
        dragging = row;
        row.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try {
            e.dataTransfer.setData('text/plain', row.getAttribute('data-course'));
        } catch (err) {
            // Ignore.
        }
    });
    editor.addEventListener('dragend', function() {
        if (dragging) {
            dragging.classList.remove('is-dragging');
        }
        editor.querySelectorAll('.is-over').forEach(function(el) {
            el.classList.remove('is-over');
        });
        dragging = null;
    });
    editor.addEventListener('dragover', function(e) {
        if (!dragging) {
            return;
        }
        var list = e.target.closest('[data-list]');
        if (!list) {
            return;
        }
        e.preventDefault();
        editor.querySelectorAll('.is-over').forEach(function(el) {
            el.classList.remove('is-over');
        });
        list.classList.add('is-over');
        var rows = Array.prototype.filter.call(list.querySelectorAll('.ll-prow'), function(r) {
            return r !== dragging;
        });
        var after = null;
        for (var i = 0; i < rows.length; i++) {
            var box = rows[i].getBoundingClientRect();
            if (e.clientY < box.top + box.height / 2) {
                after = rows[i];
                break;
            }
        }
        if (after) {
            list.insertBefore(dragging, after);
        } else {
            list.appendChild(dragging);
        }
    });
    editor.addEventListener('drop', function(e) {
        if (dragging) {
            e.preventDefault();
            markDirty();
        }
    });

    var allRows = function() {
        return Array.prototype.slice.call(editor.querySelectorAll('.ll-prow'));
    };

    editor.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) {
            return;
        }
        var act = btn.getAttribute('data-act');
        var row = btn.closest('.ll-prow');
        var group = btn.closest('.ll-pgroup');
        if (act === 'toggle') {
            var hidden = row.classList.toggle('is-hidden');
            btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
            markDirty();
        } else if (act === 'remove') {
            row.remove();
            markDirty();
        } else if (act === 'up' || act === 'down') {
            var rows = allRows();
            var idx = rows.indexOf(row);
            var target = rows[idx + (act === 'up' ? -1 : 1)];
            if (target) {
                if (act === 'up') {
                    target.parentNode.insertBefore(row, target);
                } else {
                    target.parentNode.insertBefore(row, target.nextSibling);
                }
            } else {
                var lists = Array.prototype.slice.call(editor.querySelectorAll('[data-list]'));
                var li = lists.indexOf(row.parentNode) + (act === 'up' ? -1 : 1);
                if (lists[li]) {
                    if (act === 'up') {
                        lists[li].appendChild(row);
                    } else {
                        lists[li].insertBefore(row, lists[li].firstChild);
                    }
                }
            }
            btn.focus();
            markDirty();
        } else if (act === 'addgroup') {
            var node = editor.querySelector('template[data-group-template]').content.firstElementChild.cloneNode(true);
            groupsWrap.appendChild(node);
            node.querySelector('[data-f="name"]').focus();
            markDirty();
        } else if (act === 'gup' || act === 'gdown') {
            var sib = act === 'gup' ? group.previousElementSibling : group.nextElementSibling;
            if (sib) {
                if (act === 'gup') {
                    groupsWrap.insertBefore(group, sib);
                } else {
                    groupsWrap.insertBefore(sib, group);
                }
                btn.focus();
                markDirty();
            }
        } else if (act === 'gdelete') {
            var defaultList = editor.querySelector('.ll-pgroup.is-default [data-list]');
            group.querySelectorAll('.ll-prow').forEach(function(r) {
                defaultList.appendChild(r);
            });
            group.remove();
            markDirty();
        } else if (act === 'chipx') {
            btn.parentNode.remove();
            markDirty();
        } else if (act === 'addfield') {
            var tpl = editor.querySelector('template[data-fieldrow-template]').content.firstElementChild.cloneNode(true);
            editor.querySelector('[data-fieldrows]').appendChild(tpl);
            tpl.querySelector('[data-f="value"]').focus();
            markDirty();
        } else if (act === 'fieldx') {
            btn.closest('.ll-fieldrow').remove();
            markDirty();
        } else if (act === 'openpicker') {
            picker.hidden = false;
            document.body.classList.add('ll-palette-open');
            pickerSearch.value = '';
            pickerSearch.dispatchEvent(new Event('input'));
            setTimeout(function() {
                pickerSearch.focus();
            }, 10);
        } else if (act === 'closepicker') {
            picker.hidden = true;
            document.body.classList.remove('ll-palette-open');
        } else if (act === 'pick') {
            var item = btn.closest('[data-pick]');
            if (item.classList.contains('is-added')) {
                var existing = editor.querySelector('.ll-prow[data-course="' + item.getAttribute('data-pick') + '"]');
                if (existing) {
                    existing.remove();
                }
                markDirty();
            } else {
                addCourse(item);
            }
        } else if (act === 'save') {
            save(btn);
        }
    });
    editor.addEventListener('input', function(e) {
        if (!e.target.closest('[data-filter], [data-picker-search], [data-chip-input]')) {
            markDirty();
        }
    });
    editor.addEventListener('change', function(e) {
        if (!e.target.closest('[data-filter], [data-picker-search]')) {
            markDirty();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !picker.hidden) {
            picker.hidden = true;
            document.body.classList.remove('ll-palette-open');
        }
    });

    var collect = function(section) {
        return Array.prototype.map.call(section.querySelectorAll('.ll-prow'), function(r) {
            return {
                id: parseInt(r.getAttribute('data-course'), 10),
                req: parseInt(r.querySelector('[data-f="req"]').value, 10) || 0,
                days: parseInt(r.querySelector('[data-f="days"]').value, 10) || 0,
                hours: parseInt(r.querySelector('[data-f="hours"]').value, 10) || 0,
                hidden: r.classList.contains('is-hidden') ? 1 : 0
            };
        });
    };
    var checked = function(name) {
        return Array.prototype.map.call(editor.querySelectorAll('[data-checklist="' + name + '"] input:checked'), function(c) {
            return parseInt(c.value, 10);
        });
    };
    var save = function(btn) {
        var everyone = editor.querySelector('input[name="aud"][value="everyone"]').checked;
        var data = {
            name: editor.querySelector('[data-f="pname"]').value,
            description: editor.querySelector('[data-f="pdesc"]').value,
            audience: {
                everyone: everyone,
                match: editor.querySelector('[data-f="match"]').value,
                cohorts: checked('cohorts'),
                categories: checked('categories'),
                courses: checked('courses'),
                groups: Array.prototype.map.call(editor.querySelectorAll('.ll-chip-tag'), function(c) {
                    return c.getAttribute('data-value');
                }),
                fields: Array.prototype.map.call(editor.querySelectorAll('[data-fieldrows] .ll-fieldrow'), function(f) {
                    return {
                        field: f.querySelector('[data-f="field"]').value,
                        op: f.querySelector('[data-f="op"]').value,
                        value: f.querySelector('[data-f="value"]').value
                    };
                })
            },
            'default': collect(editor.querySelector('.ll-pgroup.is-default')),
            groups: Array.prototype.map.call(groupsWrap.querySelectorAll('.ll-pgroup'), function(g) {
                return {
                    id: parseInt(g.getAttribute('data-group'), 10) || 0,
                    name: g.querySelector('[data-f="name"]').value,
                    courses: collect(g)
                };
            })
        };
        btn.disabled = true;
        post(editor.getAttribute('data-save'), {
            sesskey: editor.getAttribute('data-sesskey'), action: 'save',
            id: editor.getAttribute('data-id') || 0, data: JSON.stringify(data)
        }).then(function() {
            dirty = false;
            window.location.href = editor.getAttribute('data-done');
        }).catch(function() {
            btn.disabled = false;
            status.textContent = str('savefailed');
        });
    };
    window.addEventListener('beforeunload', function(e) {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    syncEmpty();
})();
