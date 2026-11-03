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
 * Grading inbox for a course: what's waiting, and one click into the right grader.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\grading;
use theme_lmslabs\local\icons;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
if (!grading::applies($context)) {
    require_capability('mod/assign:grade', $context);
}

$PAGE->set_url(new moodle_url('/theme/lmslabs/grading.php', ['id' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('gr_title', 'theme_lmslabs'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->navbar->add(get_string('gr_title', 'theme_lmslabs'));

$data = grading::export($course);
$data['labsurl'] = 'https://lms-labs.com/plugins';
$data['i'] = [
    'bolt' => icons::svg('zap'), 'sparkles' => icons::svg('sparkles'), 'check' => icons::svg('check'),
    'chart' => icons::svg('chart-pie'), 'inbox' => icons::svg('inbox'), 'arrow' => icons::svg('arrow-up-right'),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/grading', $data);
echo $OUTPUT->footer();
