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
 * My courses: the programs this learner belongs to, with progress and unlock rules.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\programs;
use theme_lmslabs\local\icons;

require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/'));
}

$syscontext = context_system::instance();
$PAGE->set_context($syscontext);
$PAGE->set_url(new moodle_url('/theme/lmslabs/courses.php'));
$PAGE->set_pagelayout('mycourses');
$PAGE->set_title(get_string('mycourses'));
$PAGE->set_heading(get_string('mycourses'));
$PAGE->add_body_class('ll-programs');
$PAGE->add_body_class('ll-mycourses-page');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/programs.js'));

$data = programs::export_for_user($USER->id);
$data['overalldash'] = round(150.8 * (1 - ($data['overall'] ?? 0) / 100), 2);
$data['firstname'] = s($USER->firstname);
// Widths of the status bar segments, as percentages of all courses.
$segtotal = max(1, (int)($data['total'] ?? 0));
$data['seg'] = [
    'done' => round(100 * ($data['counts']['complete'] ?? 0) / $segtotal, 2),
    'prog' => round(100 * ($data['counts']['inprogress'] ?? 0) / $segtotal, 2),
    'lock' => round(100 * ($data['counts']['locked'] ?? 0) / $segtotal, 2),
];
$data += [
    'canedit' => has_capability('theme/lmslabs:manageprograms', $syscontext),
    'editurl' => (new moodle_url('/theme/lmslabs/programs.php'))->out(false),
    'i' => [
        'image' => icons::svg('image'), 'lock' => icons::svg('lock-keyhole'), 'check' => icons::svg('check'),
        'pencil' => icons::svg('layers'), 'book' => icons::svg('book-open'), 'play' => icons::svg('play'),
        'search' => icons::svg('search'), 'folder' => icons::svg('folder-tree'), 'sort' => icons::svg('arrow-up-down'),
        'path' => icons::svg('route'), 'grid' => icons::svg('layout-grid'), 'list' => icons::svg('list'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/courses', $data);
echo $OUTPUT->footer();
