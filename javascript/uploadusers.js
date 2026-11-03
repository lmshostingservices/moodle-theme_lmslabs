/**
 * Upload users: drag-and-drop CSV, busy states.
 *
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var start = function() {
        var drop = document.querySelector('[data-rt-drop]');
        if (drop) {
            var input = drop.querySelector('[data-rt-file]');
            var send = function() {
                if (input.files.length) {
                    drop.classList.add('is-busy');
                    drop.querySelector('[data-rt-busy]').hidden = false;
                    drop.submit();
                }
            };
            input.addEventListener('change', send);
            ['dragenter', 'dragover'].forEach(function(ev) {
                drop.addEventListener(ev, function(e) {
                    e.preventDefault();
                    drop.classList.add('is-over');
                });
            });
            ['dragleave', 'drop'].forEach(function(ev) {
                drop.addEventListener(ev, function(e) {
                    e.preventDefault();
                    drop.classList.remove('is-over');
                });
            });
            drop.addEventListener('drop', function(e) {
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    send();
                }
            });
        }
        var go = document.querySelector('[data-rt-go]');
        if (go) {
            var sync = function() {
                var picked = go.querySelector('[data-rt-where]:checked');
                var where = picked ? picked.value : 'new';
                var cat = go.querySelector('[data-rt-cat]');
                if (cat) {
                    cat.hidden = where !== 'new';
                }
                go.querySelector('[data-rt-warn]').hidden = where !== 'replace';
                go.querySelector('[data-rt-submit]').classList.toggle('btn-danger', where === 'replace');
            };
            go.addEventListener('change', sync);
            sync();
            go.addEventListener('submit', function() {
                go.querySelector('[data-rt-submit]').disabled = true;
                go.querySelector('[data-rt-busy]').hidden = false;
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
