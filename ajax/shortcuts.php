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
 * Top bar shortcuts: load the picker, save the person's shortcuts.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use theme_lmslabs\local\nav;
use theme_lmslabs\local\shortcuts;

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

if ($courseid && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course, false, null, false, true);
    $PAGE->set_url(new moodle_url('/course/view.php', ['id' => $course->id]));
} else {
    require_login(null, false);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/my/'));
}
require_sesskey();
if (isguestuser()) {
    throw new require_login_exception('Guests cannot have shortcuts');
}

if ($action === 'save') {
    $keys = optional_param_array('k', [], PARAM_ALPHANUMEXT);
    $icons = optional_param_array('i', [], PARAM_ALPHANUMEXT);
    $tips = optional_param_array('t', [], PARAM_TEXT);
    shortcuts::save($keys, $icons, $tips, (new nav($PAGE))->catalogue());
    $data = shortcuts::picker($PAGE);
    echo json_encode(['ok' => true, 'saved' => $data['saved'], 'bar' => $data['bar'],
        'message' => get_string('sc_saved', 'theme_lmslabs')]);
} else {
    echo json_encode(['ok' => true] + shortcuts::picker($PAGE));
}
