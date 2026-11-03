/**
 * Gradebook: filter, type-to-save marks, CSV.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var root = document.querySelector('[data-ll-gb]');
        if (!root) {
            return;
        }
        var all = function(sel) {
            return Array.prototype.slice.call(root.querySelectorAll(sel));
        };
        var toast = function(msg, error) {
            if (window.require) {
                window.require(['core/toast'], function(Toast) {
                    Toast.add(msg, {type: error ? 'danger' : 'success'});
                });
            }
        };
        var band = function(p, pass) {
            return p === null || p === undefined ? 'none' : (p >= pass ? 'pass' : 'fail');
        };

        // ---------- Display scheme ----------
        var letter = function(p) {
            return p >= 85 ? 'HD' : (p >= 75 ? 'D' : (p >= 65 ? 'C' : (p >= 50 ? 'P' : 'F')));
        };
        var render = function() {
            var scheme = root.getAttribute('data-scheme') || 'snys';
            if (typeof fit === 'function') {
                window.setTimeout(fit, 0);
            }
            all('[data-gb-scheme]').forEach(function(b) {
                b.classList.toggle('is-on', b.getAttribute('data-gb-scheme') === scheme);
            });
            all('.ll-gb-table tbody tr').forEach(function(tr) {
                var anyNys = false;
                var cells = Array.prototype.slice.call(tr.querySelectorAll('.ll-gb-cell'));
                cells.forEach(function(td) {
                    if (!td.classList.contains('is-total') && td.hasAttribute('data-pct')
                        && Number(td.getAttribute('data-pct')) < (Number(td.getAttribute('data-passpct')) || 50)) {
                        anyNys = true;
                    }
                });
                cells.forEach(function(td) {
                    var b = td.querySelector('[data-gb-badge]');
                    if (!b) {
                        return;
                    }
                    var has = td.hasAttribute('data-pct');
                    var p = has ? Number(td.getAttribute('data-pct')) : null;
                    var pass = Number(td.getAttribute('data-passpct')) || 50;
                    var tot = td.classList.contains('is-total');
                    var t = '';
                    if (has) {
                        if (scheme === 'snys') {
                            var ok = p >= pass && !(tot && anyNys);
                            t = tot ? (ok ? root.getAttribute('data-competent') : root.getAttribute('data-notcompetent')) : (ok ? 'S' : 'NYS');
                        } else if (scheme === 'pct') {
                            t = p + '%';
                        } else if (scheme === 'grade') {
                            t = letter(p);
                        } else if (scheme === 'points') {
                            t = tot ? '' : (p >= pass ? '2' : '1');
                        }
                    }
                    if (!has && scheme !== 'marks' && !tot) {
                        t = td.classList.contains('is-waiting') ? root.getAttribute('data-awaiting') : root.getAttribute('data-notsub');
                    }
                    b.textContent = t;
                });
            });
        };
        var stats = function() {
            var st = {mark: 0, gap: 0, notsub: 0, ok: 0};
            all('.ll-gb-table tbody tr').forEach(function(tr) {
                var cells = Array.prototype.slice.call(tr.querySelectorAll('.ll-gb-cell:not(.is-total)'));
                var bad = false;
                var all_ok = cells.length > 0;
                cells.forEach(function(td) {
                    var has = td.hasAttribute('data-pct');
                    if (td.classList.contains('is-waiting') && !has) {
                        st.mark++;
                    } else if (!has) {
                        st.notsub++;
                    }
                    if (has && Number(td.getAttribute('data-pct')) < (Number(td.getAttribute('data-passpct')) || 50)) {
                        bad = true;
                    }
                    if (!has || Number(td.getAttribute('data-pct')) < (Number(td.getAttribute('data-passpct')) || 50)) {
                        all_ok = false;
                    }
                });
                st.gap += bad ? 1 : 0;
                st.ok += all_ok ? 1 : 0;
            });
            Object.keys(st).forEach(function(k) {
                var el = root.querySelector('[data-stat="' + k + '"]');
                if (el) {
                    el.textContent = st[k];
                }
            });
        };
        var renderBase = render;
        render = function() {
            renderBase();
            stats();
            if (typeof recalcAverage === 'function') {
                recalcAverage();
            }
        };
        render();
        all('[data-gb-scheme]').forEach(function(b) {
            b.addEventListener('click', function() {
                root.setAttribute('data-scheme', b.getAttribute('data-gb-scheme'));
                render();
                window.fetch(root.getAttribute('data-ajax'), {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({
                    sesskey: root.getAttribute('data-sesskey'), courseid: root.getAttribute('data-course'), colours: 1,
                    pass: root.style.getPropertyValue('--ll-gb-pass').trim(), fail: root.style.getPropertyValue('--ll-gb-fail').trim(),
                    scheme: b.getAttribute('data-gb-scheme')
                })});
            });
        });
        // Click an S / NYS badge to cycle the mark: none -> S -> NYS -> none.
        var cycle = function(b) {
            var td = b.closest('td');
            if (td.getAttribute('data-mark') && td.classList.contains('is-waiting')) {
                window.location.href = td.getAttribute('data-mark');
                return;
            }
            var inp = td.querySelector('[data-gb-input]');
            if (!inp || root.getAttribute('data-scheme') !== 'snys') {
                return;
            }
            var max = td.getAttribute('data-max');
            var cur = b.textContent;
            inp.value = cur === 'S' ? '0' : (cur === 'NYS' ? '' : max);
            inp.dispatchEvent(new Event('change', {bubbles: true}));
        };
        root.addEventListener('click', function(e) {
            var wt = e.target.closest ? e.target.closest('td.is-waiting[data-mark]') : null;
            if (wt && root.getAttribute('data-scheme') !== 'marks' && !e.target.matches('[data-gb-badge]')) {
                window.location.href = wt.getAttribute('data-mark');
                return;
            }
            if (e.target.matches('[data-gb-badge]')) {
                cycle(e.target);
            }
        });
        root.addEventListener('keydown', function(e) {
            if (e.target.matches('[data-gb-badge]') && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                cycle(e.target);
            }
        });

        // ---------- Colours (saved per teacher) ----------
        var DEF = {pass: '#24c45f', fail: '#c8007f'};
        var saveColours = function() {
            var body = new URLSearchParams({
                sesskey: root.getAttribute('data-sesskey'), courseid: root.getAttribute('data-course'), colours: 1,
                pass: root.style.getPropertyValue('--ll-gb-pass').trim(), fail: root.style.getPropertyValue('--ll-gb-fail').trim()
            });
            window.fetch(root.getAttribute('data-ajax'), {method: 'POST', body: body, credentials: 'same-origin'});
        };
        all('[data-gb-colour]').forEach(function(inp) {
            inp.addEventListener('input', function() {
                root.style.setProperty('--ll-gb-' + inp.getAttribute('data-gb-colour'), inp.value);
            });
            inp.addEventListener('change', saveColours);
        });
        var rst = root.querySelector('[data-gb-colour-reset]');
        if (rst) {
            rst.addEventListener('click', function() {
                all('[data-gb-colour]').forEach(function(inp) {
                    var k = inp.getAttribute('data-gb-colour');
                    inp.value = DEF[k];
                    root.style.setProperty('--ll-gb-' + k, DEF[k]);
                });
                saveColours();
            });
        }

        render();

        // ---------- Smart widths: columns share the space, never too narrow or too wide ----------
        var fit = function() {
            var wrap = root.querySelector('.ll-gb-scroll');
            var tbl = root.querySelector('.ll-gb-table');
            if (!wrap || !tbl) {
                return;
            }
            var n = tbl.querySelectorAll('thead th.ll-gb-col').length || 1;
            var avail = root.clientWidth - 230 - 2;
            var min = (root.getAttribute('data-scheme') || 'snys') === 'marks' ? 96 : 80;
            var w = Math.max(min, Math.min(150, Math.floor(avail / n)));
            tbl.style.setProperty('--ll-gb-colw', w + 'px');
            tbl.style.setProperty('--ll-gb-tw', (230 + n * w) + 'px');
        };
        fit();
        window.addEventListener('resize', fit);

        // ---------- Filter ----------
        var f = {q: '', mode: ''};
        var apply = function() {
            var shown = 0;
            all('[data-gb-row]').forEach(function(tr) {
                var on = (!f.q || tr.getAttribute('data-name').toLowerCase().indexOf(f.q) !== -1);
                if (on && f.mode === 'waiting') {
                    on = Number(tr.getAttribute('data-waiting')) > 0;
                }
                if (on && f.mode === 'missing') {
                    on = !!tr.querySelector('.ll-gb-cell.is-empty:not(.is-total)');
                }
                tr.hidden = !on;
                shown += on ? 1 : 0;
            });
            var none = root.querySelector('[data-gb-none]');
            if (none) {
                none.hidden = shown > 0;
            }
        };
        var q = root.querySelector('[data-gb-q]');
        if (q) {
            q.addEventListener('input', function() {
                f.q = q.value.trim().toLowerCase();
                apply();
            });
        }
        all('[data-gb-filter]').forEach(function(b) {
            b.addEventListener('click', function() {
                all('[data-gb-filter]').forEach(function(x) {
                    x.classList.toggle('is-on', x === b);
                });
                f.mode = b.getAttribute('data-gb-filter');
                apply();
            });
        });

        // ---------- Save a mark ----------
        var recalcAverage = function() {
            var scheme = root.getAttribute('data-scheme') || 'snys';
            all('.ll-gb-avg').forEach(function(foot) {
                var id = foot.getAttribute('data-avg');
                var cells = all('.ll-gb-cell[data-item="' + id + '"]');
                var tot = cells.length && cells[0].classList.contains('is-total');
                var n = 0;
                var ok = 0;
                var sum = 0;
                var wait = 0;
                cells.forEach(function(td) {
                    if (td.hasAttribute('data-pct')) {
                        var p = Number(td.getAttribute('data-pct'));
                        n++;
                        sum += p;
                        ok += p >= (Number(td.getAttribute('data-passpct')) || 50) ? 1 : 0;
                    } else if (td.classList.contains('is-waiting')) {
                        wait++;
                    }
                });
                var main = foot.querySelector('[data-rate-main]');
                var unit = foot.querySelector('[data-rate-unit]');
                var bar = foot.querySelector('[data-rate-bar]');
                var sub = foot.querySelector('[data-rate-sub]');
                var pct = n ? Math.round((scheme === 'snys' ? ok : sum / 100) / n * 100) : 0;
                if (!n) {
                    main.textContent = '\u2013';
                    unit.textContent = '';
                } else if (scheme === 'snys') {
                    main.textContent = ok + ' / ' + n;
                    unit.textContent = tot ? foot.getAttribute('data-u-c') || 'C' : 'S';
                } else {
                    main.textContent = Math.round(sum / n) + '%';
                    unit.textContent = '';
                    pct = Math.round(sum / n);
                }
                if (bar) {
                    bar.style.width = pct + '%';
                }
                if (sub) {
                    sub.textContent = wait ? wait + ' ' + (root.getAttribute('data-awaiting') || '').toLowerCase() : '';
                }
            });
        };
        recalcAverage();
        var paint = function(td, pct) {
            td.classList.remove('band-pass', 'band-fail', 'band-none');
            td.classList.add('band-' + band(pct, Number(td.getAttribute('data-passpct')) || 50));
            if (pct === null) {
                td.removeAttribute('data-pct');
            } else {
                td.setAttribute('data-pct', pct);
            }
            if (typeof render === 'function') {
                render();
            }
        };
        // Seed percentages for averages from the server-rendered bands.
        all('.ll-gb-input').forEach(function(inp) {
            var max = Number(inp.getAttribute('data-max'));
            if (inp.value !== '' && max > 0) {
                inp.closest('td').setAttribute('data-pct', Math.round(Number(inp.value.replace(',', '.')) / max * 100));
            }
        });

        var save = function(inp) {
            var td = inp.closest('td');
            if (inp.value.trim() === inp.getAttribute('data-orig')) {
                return;
            }
            var body = new URLSearchParams({
                courseid: root.getAttribute('data-course'), sesskey: root.getAttribute('data-sesskey'),
                itemid: inp.getAttribute('data-item'), userid: inp.getAttribute('data-user'), value: inp.value
            });
            td.classList.add('is-saving');
            fetch(root.getAttribute('data-ajax'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                }).then(function(r) {
                    td.classList.remove('is-saving');
                    if (r.error) {
                        td.classList.add('is-error');
                        toast(r.error, true);
                        inp.value = inp.getAttribute('data-orig');
                        window.setTimeout(function() {
                            td.classList.remove('is-error');
                        }, 1500);
                        return;
                    }
                    inp.value = r.value;
                    inp.setAttribute('data-orig', r.value);
                    td.classList.toggle('is-empty', r.value === '');
                    td.classList.remove('is-waiting');
                    td.classList.add('is-overridden', 'is-saved');
                    window.setTimeout(function() {
                        td.classList.remove('is-saved');
                    }, 1200);
                    paint(td, r.pct);
                    recalcAverage();
                    if (r.total) {
                        var tr = td.closest('tr');
                        var tt = tr.querySelector('.ll-gb-cell.is-total[data-item="' + r.total.itemid + '"]');
                        if (tt) {
                            var span = tt.querySelector('[data-gb-static]');
                            if (span) {
                                span.textContent = r.total.display;
                            }
                            tt.classList.remove('is-empty');
                            paint(tt, r.total.pct);
                        }
                    }
                    toast(root.getAttribute('data-saved'));
                }).catch(function(err) {
                    td.classList.remove('is-saving');
                    toast(err.message, true);
                });
        };
        root.addEventListener('change', function(e) {
            if (e.target.matches('[data-gb-input]')) {
                save(e.target);
            }
        });
        root.addEventListener('keydown', function(e) {
            if (!e.target.matches('[data-gb-input]')) {
                return;
            }
            var move = {ArrowDown: [0, 1], ArrowUp: [0, -1], Enter: [0, 1]}[e.key];
            if (!move) {
                return;
            }
            e.preventDefault();
            var td = e.target.closest('td');
            var tr = td.closest('tr');
            var idx = Array.prototype.indexOf.call(tr.children, td);
            var next = move[1] > 0 ? tr.nextElementSibling : tr.previousElementSibling;
            while (next && next.hidden) {
                next = move[1] > 0 ? next.nextElementSibling : next.previousElementSibling;
            }
            e.target.blur();
            if (next && next.children[idx]) {
                var t = next.children[idx].querySelector('input');
                if (t) {
                    t.focus();
                    t.select();
                }
            }
        });

        // ---------- CSV ----------
        var csvBtn = root.querySelector('[data-gb-csv]');
        if (csvBtn) {
            csvBtn.addEventListener('click', function() {
                var lines = [];
                var text = function(el) {
                    return el.textContent.trim().replace(/\s+/g, ' ');
                };
                lines.push(all('thead th').map(text));
                all('[data-gb-row]').forEach(function(tr) {
                    if (tr.hidden) {
                        return;
                    }
                    lines.push([text(tr.querySelector('th'))].concat(all_cells(tr)));
                });
                var csv = lines.map(function(l) {
                    return l.map(function(v) {
                        return '"' + String(v).replace(/"/g, '""') + '"';
                    }).join(',');
                }).join('\n');
                var a = document.createElement('a');
                a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
                a.download = 'gradebook.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
        }
        var all_cells = function(tr) {
            return Array.prototype.slice.call(tr.querySelectorAll('td')).map(function(td) {
                var inp = td.querySelector('input');
                return inp ? inp.value : td.textContent.trim();
            });
        };
        apply();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
