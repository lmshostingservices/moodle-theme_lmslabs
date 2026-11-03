/**
 * Add a course: short name suggestion, length chips set the end date, image preview, live course card.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var start = function() {
        var form = document.querySelector('[data-ll-addcourse]');
        if (!form) {
            return;
        }
        var q = function(n) {
            return form.querySelector('[data-ac="' + n + '"]');
        };
        var full = q('full');
        var short = q('short');
        var cat = q('cat');
        var startIn = q('start');
        var endIn = q('end');
        var auto = short.getAttribute('data-auto') === '1';
        var year = short.getAttribute('data-year');

        // Short name: initials of the main words plus the year, until someone types their own.
        var suggest = function() {
            var words = full.value.replace(/[^A-Za-z0-9 ]+/g, ' ').split(/\s+/).filter(function(w) {
                return w && !/^(and|of|the|in|for|to|a|an|with|on)$/i.test(w);
            });
            var code = /^[A-Z]{3,}[0-9]{3,}$/.test(words[0] || '') ? words[0] : words.map(function(w) {
                return /^\d+$/.test(w) ? w : w[0].toUpperCase();
            }).join('').slice(0, 8);
            return code ? code + '-' + year : '';
        };
        var syncAuto = function() {
            q('autotag').hidden = !auto || !short.value;
        };
        full.addEventListener('input', function() {
            if (auto) {
                short.value = suggest();
            }
            syncAuto();
            preview();
        });
        short.addEventListener('input', function() {
            auto = short.value === '';
            syncAuto();
            preview();
        });

        // Length chips set the end date from the start date.
        var applyDuration = function() {
            var r = form.querySelector('input[name="duration"]:checked');
            var months = r ? parseInt(r.value, 10) : 0;
            if (!months) {
                endIn.value = '';
            } else if (startIn.value) {
                var d = new Date(startIn.value + 'T00:00:00');
                d.setMonth(d.getMonth() + months);
                d.setDate(d.getDate() - 1);
                var pad = function(x) {
                    return (x < 10 ? '0' : '') + x;
                };
                endIn.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            }
            preview();
        };
        form.querySelectorAll('input[name="duration"]').forEach(function(r) {
            r.addEventListener('change', applyDuration);
        });
        startIn.addEventListener('change', applyDuration);
        endIn.addEventListener('change', function() {
            form.querySelectorAll('input[name="duration"]').forEach(function(r) {
                r.checked = false;
            });
            preview();
        });
        cat.addEventListener('change', preview);

        // Image: drag and drop, preview.
        var drop = q('drop');
        var file = q('file');
        var media = q('media');
        var showImage = function() {
            var f = file.files && file.files[0];
            if (!f) {
                return;
            }
            q('dropname').textContent = f.name;
            drop.classList.add('has-file');
            var reader = new FileReader();
            reader.onload = function() {
                media.style.backgroundImage = 'url("' + reader.result + '")';
                media.classList.add('has-image');
            };
            reader.readAsDataURL(f);
        };
        file.addEventListener('change', showImage);
        ['dragenter', 'dragover'].forEach(function(t) {
            drop.addEventListener(t, function(e) {
                e.preventDefault();
                drop.classList.add('is-over');
            });
        });
        ['dragleave', 'drop'].forEach(function(t) {
            drop.addEventListener(t, function(e) {
                e.preventDefault();
                drop.classList.remove('is-over');
            });
        });
        drop.addEventListener('drop', function(e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                file.files = e.dataTransfer.files;
                showImage();
            }
        });

        var fmt = function(v) {
            if (!v) {
                return '';
            }
            return new Date(v + 'T00:00:00').toLocaleDateString(undefined, {day: 'numeric', month: 'short', year: 'numeric'});
        };
        function preview() {
            var name = full.value.trim();
            var pname = q('pname');
            pname.textContent = name || pname.getAttribute('data-empty');
            var initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map(function(w) {
                return w[0].toUpperCase();
            }).join('');
            q('mono').textContent = initials || '?';
            q('pcat').textContent = cat.options[cat.selectedIndex] ? cat.options[cat.selectedIndex].text.split(' / ').pop() : '';
            q('pmeta').textContent = short.value;
            var pd = q('pdates');
            pd.textContent = fmt(startIn.value) + ' – ' + (endIn.value ? fmt(endIn.value) : pd.getAttribute('data-noend'));
        }

        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                form.querySelector('button[type="submit"]').click();
            }
        });
        if (!endIn.value && form.querySelector('input[name="duration"]:checked')) {
            applyDuration();
        }
        syncAuto();
        preview();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
