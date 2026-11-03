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
 * Programs: who sees which courses on My courses, in what order, with what rules.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\programs;
use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
require_capability('theme/lmslabs:manageprograms', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/lmslabs/programs.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('programs', 'theme_lmslabs'));
$PAGE->set_heading(get_string('programs', 'theme_lmslabs'));
$PAGE->navbar->add(get_string('programs', 'theme_lmslabs'), $PAGE->url);
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/programs.js'));
$PAGE->requires->strings_for_js(['savefailed', 'confirmdeleteprogram'], 'theme_lmslabs');

$data = programs::export_list() + [
    'newurl' => (new moodle_url('/theme/lmslabs/program.php'))->out(false),
    'previewurl' => (new moodle_url('/theme/lmslabs/courses.php'))->out(false),
    'saveurl' => (new moodle_url('/theme/lmslabs/ajax/programs.php'))->out(false),
    'sesskey' => sesskey(),
    'i' => [
        'plus' => icons::svg('plus'), 'users' => icons::svg('users'), 'book' => icons::svg('book-open'),
        'up' => icons::svg('chevron-up'), 'down' => icons::svg('chevron-down'), 'x' => icons::svg('x'),
        'pencil' => icons::svg('pencil'), 'layers' => icons::svg('layers'), 'eye' => icons::svg('eye'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/programs_list', $data);
echo $OUTPUT->footer();
