/**
 * Preferences: save each change the moment it's made.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var root = document.querySelector('[data-ll-prefs]');
        if (!root) {
            return;
        }
        root.addEventListener('change', function(e) {
            var el = e.target.closest('[data-pref]');
            if (!el) {
                return;
            }
            var key = el.getAttribute('data-pref');
            var value = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
            // "Email me notifications" is Moodle's "Disable notifications" the other way round.
            if (key === 'emailstop') {
                value = el.checked ? '0' : '1';
            }
            var body = new URLSearchParams({key: key, value: value, sesskey: root.getAttribute('data-sesskey')});
            var card = el.closest('.ll-pcard');
            card.classList.add('is-saving');
            fetch(root.getAttribute('data-ajax'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function(r) {
                    return r.json();
                }).then(function(r) {
                    if (!r.ok) {
                        throw new Error(r.error || '');
                    }
                    card.classList.remove('is-saving');
                    card.classList.add('is-saved');
                    window.setTimeout(function() {
                        card.classList.remove('is-saved');
                    }, 1400);
                    if (r.reload) {
                        window.location.reload();
                    } else if (window.require) {
                        window.require(['core/toast'], function(Toast) {
                            Toast.add(r.message);
                        });
                    }
                }).catch(function(err) {
                    card.classList.remove('is-saving');
                    if (window.require) {
                        window.require(['core/toast'], function(Toast) {
                            Toast.add(err.message || 'Not saved', {type: 'danger'});
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
