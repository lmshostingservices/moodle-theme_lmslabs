/**
 * Reset: the button unlocks once RESET is typed.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var input = document.querySelector('[data-rs-confirm]');
        var go = document.querySelector('[data-rs-go]');
        if (!input || !go) {
            return;
        }
        input.addEventListener('input', function() {
            go.disabled = input.value.trim().toUpperCase() !== 'RESET';
            if (!go.disabled) {
                input.value = 'RESET';
            }
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
