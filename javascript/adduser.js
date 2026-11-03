/**
 * Add a person: email follows the username, password tools, course picker and live preview.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var form = document.querySelector('[data-ll-adduser]');
        if (!form) {
            return;
        }
        var q = function(name) {
            return form.querySelector('[data-au="' + name + '"]');
        };
        var username = q('username');
        var email = q('email');
        var link = q('link');
        var password = q('password');
        var first = q('first');
        var last = q('last');
        var createpw = q('createpassword');

        // Email follows the username until someone types an email of their own.
        var synced = email.getAttribute('data-synced') === '1';
        var setSynced = function(on) {
            synced = on;
            link.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) {
                email.value = username.value.trim();
            }
            preview();
        };
        username.addEventListener('input', function() {
            var lower = username.value.toLowerCase().replace(/\s+/g, '');
            if (lower !== username.value) {
                var pos = username.selectionStart;
                username.value = lower;
                username.setSelectionRange(pos, pos);
            }
            if (synced) {
                email.value = username.value;
            }
            preview();
        });
        email.addEventListener('input', function() {
            if (synced && email.value !== username.value) {
                setSynced(false);
            }
            preview();
        });
        link.addEventListener('click', function() {
            setSynced(!synced);
        });

        // Password: show/hide, generate, strength.
        var reveal = q('reveal');
        if (reveal) {
            reveal.addEventListener('click', function() {
                password.type = password.type === 'password' ? 'text' : 'password';
            });
        }
        var meter = q('meter');
        var strength = function() {
            var v = password.value;
            var s = 0;
            if (v.length >= 8) {
                s++;
            }
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) {
                s++;
            }
            if (/\d/.test(v)) {
                s++;
            }
            if (/[^A-Za-z0-9]/.test(v) && v.length >= 10) {
                s++;
            }
            meter.setAttribute('data-level', v ? s : 0);
        };
        password.addEventListener('input', strength);
        q('generate').addEventListener('click', function() {
            var sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#$%*+-=?@'];
            var all = sets.join('');
            var rnd = function(n) {
                var a = new Uint32Array(1);
                window.crypto.getRandomValues(a);
                return a[0] % n;
            };
            var out = sets.map(function(s) {
                return s[rnd(s.length)];
            });
            while (out.length < 14) {
                out.push(all[rnd(all.length)]);
            }
            for (var i = out.length - 1; i > 0; i--) {
                var j = rnd(i + 1);
                var t = out[i];
                out[i] = out[j];
                out[j] = t;
            }
            password.value = out.join('');
            password.type = 'text';
            strength();
            password.focus();
            password.select();
        });

        // Email link instead of a password.
        var syncPw = function() {
            q('pwrow').hidden = createpw.checked;
            q('forcerow').hidden = createpw.checked;
            preview();
        };
        createpw.addEventListener('change', syncPw);

        // Course picker: filter, chips.
        var pick = q('pick');
        var chips = pick ? pick.querySelector('[data-pick-chips]') : null;
        var renderChips = function() {
            if (!pick) {
                return;
            }
            chips.innerHTML = '';
            pick.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'll-chip-tag';
                chip.textContent = cb.closest('label').querySelector('.ll-pick-name').textContent;
                chip.setAttribute('aria-label', chip.textContent + ' ×');
                chip.addEventListener('click', function() {
                    cb.checked = false;
                    renderChips();
                    preview();
                });
                chips.appendChild(chip);
            });
            chips.hidden = !chips.children.length;
        };
        if (pick) {
            pick.querySelector('[data-pick-filter]').addEventListener('input', function(e) {
                var term = e.target.value.trim().toLowerCase();
                pick.querySelectorAll('.ll-pick-item').forEach(function(item) {
                    item.hidden = term !== '' && item.getAttribute('data-name').toLowerCase().indexOf(term) === -1;
                });
            });
            pick.addEventListener('change', function() {
                renderChips();
                preview();
            });
        }
        var cohort = q('cohort');
        if (cohort) {
            cohort.addEventListener('change', preview);
        }
        first.addEventListener('input', preview);
        last.addEventListener('input', preview);

        // Live preview.
        function preview() {
            var name = (first.value.trim() + ' ' + last.value.trim()).trim();
            var pname = q('pname');
            pname.textContent = name || pname.getAttribute('data-empty');
            q('initials').textContent = name ? ((first.value.trim()[0] || '') + (last.value.trim()[0] || '')).toUpperCase() : '?';
            q('pmail').textContent = email.value.trim() || ' ';
            var login = q('plogin');
            login.textContent = createpw.checked ? login.getAttribute('data-link') : login.getAttribute('data-pw');
            var pc = q('pcourses');
            if (pc && pick) {
                var n = pick.querySelectorAll('input[type="checkbox"]:checked').length;
                pc.textContent = n ? pc.getAttribute('data-some').replace('{n}', n) : pc.getAttribute('data-none');
            }
            var pco = q('pcohort');
            if (pco && cohort) {
                pco.textContent = cohort.value !== '0' ? cohort.options[cohort.selectedIndex].text : pco.getAttribute('data-none');
            }
        }

        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                form.querySelector('button[type="submit"]').click();
            }
        });

        renderChips();
        syncPw();
        strength();
        // Errors open the accordion they live in.
        form.querySelectorAll('.ll-acc .has-error').forEach(function(el) {
            el.closest('details').open = true;
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
