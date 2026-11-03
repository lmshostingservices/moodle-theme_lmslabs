<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Save one preference instantly.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

require_login(null, false);
require_sesskey();
if (isguestuser()) {
    throw new require_login_exception('Guests cannot change preferences');
}
$PAGE->set_context(context_user::instance($USER->id));
require_capability('moodle/user:editownprofile', context_user::instance($USER->id));

\theme_lmslabs\local\prefs::save(required_param('key', PARAM_ALPHANUMEXT), trim(optional_param('value', '', PARAM_TEXT)));
echo json_encode(['ok' => true, 'message' => get_string('pf_saved', 'theme_lmslabs'),
    'reload' => required_param('key', PARAM_ALPHANUMEXT) === 'lang']);
