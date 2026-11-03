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
 * Gradebook: every learner and every mark in one grid, edit by typing.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\gradebook;
use theme_lmslabs\local\icons;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/grade:viewall', $context);

$PAGE->set_url(new moodle_url('/theme/lmslabs/gradebook.php', ['id' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('gb_title', 'theme_lmslabs'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->navbar->add(get_string('gb_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/gradebook.js'));

$data = gradebook::export($course);
$data['sesskey'] = sesskey();
$data['ajax'] = (new moodle_url('/theme/lmslabs/ajax/gradebook.php'))->out(false);
$data['i'] = [
    'search' => icons::svg('search'), 'download' => icons::svg('download'), 'inbox' => icons::svg('inbox'),
    'settings' => icons::svg('settings'), 'chart' => icons::svg('chart-column'), 'link' => icons::svg('external-link'),
    'check' => icons::svg('check'),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/gradebook', $data);
echo $OUTPUT->footer();
