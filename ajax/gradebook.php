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
 * Gradebook: save a single mark.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

require_login(null, false);
require_sesskey();
if (optional_param('colours', 0, PARAM_BOOL)) {
    // Per-user pass / not-yet-competent colours.
    $pass = optional_param('pass', '', PARAM_ALPHANUMEXT);
    $fail = optional_param('fail', '', PARAM_ALPHANUMEXT);
    foreach (['pass' => $pass, 'fail' => $fail] as $k => $v) {
        $v = ltrim($v, '#');
        set_user_preference('theme_lmslabs_gb_' . $k, preg_match('/^[0-9a-fA-F]{6}$/', $v) ? '#' . strtolower($v) : null);
    }
    $scheme = optional_param('scheme', '', PARAM_ALPHA);
    if (in_array($scheme, ['marks', 'snys', 'pct', 'grade', 'points'], true)) {
        set_user_preference('theme_lmslabs_gb_scheme', $scheme);
    }
    echo json_encode(['ok' => true]);
    die;
}
$course = get_course(required_param('courseid', PARAM_INT));
require_login($course, false);
require_capability('moodle/grade:edit', context_course::instance($course->id));
$PAGE->set_context(context_course::instance($course->id));

try {
    $result = ['ok' => true] + \theme_lmslabs\local\gradebook::save(
        $course, required_param('itemid', PARAM_INT),
        required_param('userid', PARAM_INT), optional_param('value', '', PARAM_TEXT)
    );
    $result['message'] = get_string('gb_saved', 'theme_lmslabs');
} catch (\moodle_exception $e) {
    $result = ['error' => $e->getMessage()];
}
echo json_encode($result);
