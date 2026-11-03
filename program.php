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
 * Edit one program: name, audience, courses, headings and unlock rules.
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
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/lmslabs/program.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$data = programs::export_editor($id);
$title = $id ? format_string($data['name']) : get_string('newprogram', 'theme_lmslabs');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$listurl = new moodle_url('/theme/lmslabs/programs.php');
$PAGE->navbar->add(get_string('programs', 'theme_lmslabs'), $listurl);
$PAGE->navbar->add($title);
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/programs.js'));
$PAGE->requires->strings_for_js(['savefailed', 'confirmdeleteprogram'], 'theme_lmslabs');

$data += [
    'saveurl' => (new moodle_url('/theme/lmslabs/ajax/programs.php'))->out(false),
    'doneurl' => $listurl->out(false),
    'sesskey' => sesskey(),
    'i' => [
        'grip' => icons::svg('grip-vertical'), 'plus' => icons::svg('plus'), 'x' => icons::svg('x'),
        'eye' => icons::svg('eye'), 'eyeoff' => icons::svg('eye-off'), 'up' => icons::svg('chevron-up'),
        'down' => icons::svg('chevron-down'), 'search' => icons::svg('search'), 'users' => icons::svg('users'),
        'book' => icons::svg('book-open'), 'check' => icons::svg('check'), 'contact' => icons::svg('contact-round'),
        'folder' => icons::svg('folder-tree'), 'group' => icons::svg('users-round'), 'user' => icons::svg('user-round'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/program_editor', $data);
echo $OUTPUT->footer();
