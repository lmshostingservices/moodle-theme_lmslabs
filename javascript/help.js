/* Help centre: search, filter, article panel. */
(function() {
    'use strict';
    var root = document.querySelector('[data-ll-help]');
    if (!root) {
        return;
    }
    var q = root.querySelector('[data-ll-help-q]');
    var cards = Array.prototype.slice.call(root.querySelectorAll('.ll-help-card'));
    var count = root.querySelector('[data-ll-help-count]');
    var empty = root.querySelector('[data-ll-help-empty]');
    var panel = root.querySelector('[data-ll-help-panel]');
    var scrim = root.querySelector('[data-ll-help-scrim]');
    // Fixed elements inside the page area are positioned against it, not the window, so the popup lives on <body>.
    var layer = document.createElement('div');
    layer.className = 'll-help ll-help-layer';
    layer.appendChild(scrim);
    layer.appendChild(panel);
    document.body.appendChild(layer);
    var audBtns = Array.prototype.slice.call(root.querySelectorAll('[data-ll-help-aud]'));
    var topicBtns = Array.prototype.slice.call(root.querySelectorAll('[data-ll-help-topic]'));
    var aud = 'all';
    var topic = '';
    var last = null;
    var saved = null;
    try {
        saved = window.localStorage.getItem('ll-help-aud');
    } catch (e) {
        saved = null;
    }
    aud = saved || root.getAttribute('data-mine') || 'all';
    if (!audBtns.some(function(b) {
        return b.getAttribute('data-ll-help-aud') === aud;
    })) {
        aud = 'all';
    }

    function mark(el, text, terms) {
        // Highlight the search words in a text node's parent, safely.
        var raw = el.getAttribute('data-raw') || el.textContent;
        el.setAttribute('data-raw', raw);
        if (!terms.length) {
            el.textContent = raw;
            return;
        }
        var esc = raw.replace(/&/g, '&amp;').replace(/</g, '&lt;');
        terms.forEach(function(t) {
            var re = new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
            esc = esc.replace(re, '<mark>$1</mark>');
        });
        el.innerHTML = esc;
    }

    function apply() {
        var terms = q.value.toLowerCase().split(/\s+/).filter(Boolean);
        var shown = 0;
        cards.forEach(function(card) {
            var ok = (aud === 'all' || card.getAttribute('data-aud') === aud || card.getAttribute('data-aud') === 'all') &&
                (!topic || card.getAttribute('data-topic') === topic);
            if (ok && terms.length) {
                var hay = card.getAttribute('data-search');
                ok = terms.every(function(t) {
                    return hay.indexOf(t) !== -1;
                });
            }
            card.hidden = !ok;
            if (ok) {
                shown++;
                mark(card.querySelector('strong'), '', terms);
                mark(card.querySelector('.ll-help-sum'), '', terms);
            }
        });
        audBtns.forEach(function(b) {
            b.classList.toggle('is-on', b.getAttribute('data-ll-help-aud') === aud);
        });
        topicBtns.forEach(function(b) {
            b.classList.toggle('is-on', b.getAttribute('data-ll-help-topic') === topic);
        });
        empty.hidden = shown > 0;
        count.textContent = shown + (shown === 1 ? ' guide' : ' guides');
    }

    function open(key) {
        var arts = panel.querySelectorAll('.ll-help-article');
        var found = false;
        Array.prototype.forEach.call(arts, function(a) {
            var on = a.getAttribute('data-article') === key;
            a.hidden = !on;
            found = found || on;
        });
        if (!found) {
            return;
        }
        last = document.activeElement;
        panel.hidden = false;
        scrim.hidden = false;
        panel.scrollTop = 0;
        panel.querySelectorAll('.ll-help-art-body, .ll-help-fig').forEach(function(el) {
            el.scrollTop = 0;
        });
        document.body.classList.add('ll-help-open');
        panel.querySelector('[data-ll-help-close]').focus();
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + key);
        }
    }

    function close() {
        panel.hidden = true;
        scrim.hidden = true;
        document.body.classList.remove('ll-help-open');
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        if (last && last.focus) {
            last.focus();
        }
    }

    function reset() {
        q.value = '';
        topic = '';
        aud = 'all';
        apply();
    }

    q.addEventListener('input', apply);
    audBtns.forEach(function(b) {
        b.addEventListener('click', function() {
            aud = b.getAttribute('data-ll-help-aud');
            try {
                window.localStorage.setItem('ll-help-aud', aud);
            } catch (e) {
                // Storage is only a convenience.
            }
            apply();
        });
    });
    topicBtns.forEach(function(b) {
        b.addEventListener('click', function() {
            topic = b.getAttribute('data-ll-help-topic');
            apply();
        });
    });
    cards.forEach(function(c) {
        c.addEventListener('click', function() {
            open(c.getAttribute('data-key'));
        });
    });
    panel.querySelector('[data-ll-help-close]').addEventListener('click', close);
    // Hovering a step lights its numbered pin on the screenshot.
    panel.addEventListener('mouseover', function(e) {
        var li = e.target.closest ? e.target.closest('.ll-help-steps li') : null;
        panel.querySelectorAll('.ll-help-pin.is-hot').forEach(function(p) {
            p.classList.remove('is-hot');
        });
        if (!li) {
            return;
        }
        var art = li.closest('.ll-help-article');
        var pin = art ? art.querySelector('.ll-help-pin[data-n="' + li.getAttribute('data-n') + '"]') : null;
        if (pin) {
            pin.classList.add('is-hot');
        }
    });
    panel.addEventListener('mouseover', function(e) {
        var b = e.target.closest ? e.target.closest('.ll-help-pin b') : null;
        if (b) {
            var li = b.closest('.ll-help-article').querySelector('.ll-help-steps li[data-n="' + b.textContent + '"]');
            if (li) {
                li.scrollIntoView({block: 'nearest', behavior: 'smooth'});
            }
        }
    });
    scrim.addEventListener('click', close);
    root.querySelector('[data-ll-help-reset]').addEventListener('click', reset);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !panel.hidden) {
            close();
        } else if (e.key === '/' && document.activeElement !== q && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            e.preventDefault();
            q.focus();
        }
    });
    // A guide whose screenshot is missing or broken falls back to its icon card.
    document.querySelectorAll('.ll-help-shot img, .ll-help-fig img').forEach(function(img) {
        img.addEventListener('error', function() {
            img.parentNode.classList.add('is-blank');
            img.remove();
        });
    });

    apply();
    if (window.location.hash.length > 1) {
        open(window.location.hash.slice(1));
    }
}());
