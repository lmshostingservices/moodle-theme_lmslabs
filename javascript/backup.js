/**
 * Backup: busy state while the backup runs, two-step delete, auto-download of a backup just made.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var form = document.querySelector('[data-ll-bk]');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function() {
            var go = form.querySelector('[data-bk-go]');
            go.disabled = true;
            form.querySelector('[data-bk-busy]').hidden = false;
        });
        document.querySelectorAll('[data-bk-del]').forEach(function(a) {
            a.addEventListener('click', function(e) {
                if (!a.classList.contains('is-armed')) {
                    e.preventDefault();
                    a.classList.add('is-armed');
                    window.setTimeout(function() {
                        a.classList.remove('is-armed');
                    }, 3500);
                }
            });
        });
        var dl = document.querySelector('[data-bk-autodl]');
        if (dl) {
            dl.closest('.ll-bk-file').scrollIntoView({block: 'center'});
            window.setTimeout(function() {
                window.location.href = dl.href;
            }, 400);
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
