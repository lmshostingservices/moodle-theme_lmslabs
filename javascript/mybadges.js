/**
 * My badges: share a badge link in one click.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var root = document.querySelector('[data-ll-badges]');
        if (!root) {
            return;
        }
        root.addEventListener('click', function(e) {
            var b = e.target.closest('[data-mb-share]');
            if (!b) {
                return;
            }
            var url = b.getAttribute('data-mb-share');
            if (navigator.share) {
                navigator.share({url: url}).catch(function() {
                    return null;
                });
                return;
            }
            navigator.clipboard.writeText(url).then(function() {
                if (window.require) {
                    window.require(['core/toast'], function(Toast) {
                        Toast.add(root.getAttribute('data-copied'));
                    });
                }
            });
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
