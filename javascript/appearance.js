/**
 * Appearance: the preview follows every change as you make it.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var form = document.querySelector('[data-ll-ap]');
        if (!form) {
            return;
        }
        var prev = form.querySelector('[data-ap-prev]');
        var hex = form.querySelector('[data-ap-hex]');
        var dot = form.querySelector('[data-ap-dot]');
        var valid = function(v) {
            return /^#[0-9a-f]{6}$/i.test(v);
        };
        var setColour = function(v) {
            if (!valid(v)) {
                return;
            }
            prev.style.setProperty('--pa', v);
            dot.style.setProperty('--sw', v);
            form.querySelectorAll('[data-ap-sw]').forEach(function(b) {
                b.classList.toggle('is-on', b.getAttribute('data-ap-sw').toLowerCase() === v.toLowerCase());
            });
        };
        form.querySelectorAll('[data-ap-sw]').forEach(function(b) {
            b.addEventListener('click', function() {
                hex.value = b.getAttribute('data-ap-sw');
                setColour(hex.value);
            });
        });
        hex.addEventListener('input', function() {
            setColour(hex.value.trim());
        });
        form.querySelector('[data-ap-name]').addEventListener('input', function() {
            // The preview shows the short name, kept in step with the full name only when empty.
            return;
        });
        form.querySelector('[name=shortname]').addEventListener('input', function(e) {
            form.querySelector('[data-ap-pname]').textContent = e.target.value || ' ';
        });
        var contact = function() {
            var e = form.querySelector('[data-ap-email]').value;
            var p = form.querySelector('[data-ap-phone]').value;
            form.querySelector('[data-ap-pcontact]').textContent = [e, p].filter(Boolean).join(' · ');
        };
        form.querySelector('[data-ap-email]').addEventListener('input', contact);
        form.querySelector('[data-ap-phone]').addEventListener('input', contact);
        var font = form.querySelector('[data-ap-font]');
        var link = null;
        var setFont = function() {
            var v = font.value;
            if (v === '' || v === 'system') {
                prev.style.fontFamily = v === '' ? '"Geist", system-ui, sans-serif' : 'system-ui, sans-serif';
                return;
            }
            if (!link) {
                link = document.createElement('link');
                link.rel = 'stylesheet';
                document.head.appendChild(link);
            }
            link.href = 'https://fonts.googleapis.com/css2?family=' + v.replace(/ /g, '+') + ':wght@400;600;700&display=swap';
            prev.style.fontFamily = '"' + v + '", system-ui, sans-serif';
        };
        font.addEventListener('change', setFont);
        setFont();
        form.querySelectorAll('[data-ap-size]').forEach(function(r) {
            r.addEventListener('change', function() {
                prev.style.fontSize = (parseInt(r.value, 10) - 3) + 'px';
            });
            if (r.checked) {
                prev.style.fontSize = (parseInt(r.value, 10) - 3) + 'px';
            }
        });
        var logo = form.querySelector('[data-ap-logo]');
        logo.addEventListener('change', function() {
            if (!logo.files.length) {
                return;
            }
            var url = URL.createObjectURL(logo.files[0]);
            [form.querySelector('.ll-ap-logo'), form.querySelector('.ll-ap-prevlogo')].forEach(function(box) {
                box.innerHTML = '<img src="' + url + '" alt="">';
            });
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
