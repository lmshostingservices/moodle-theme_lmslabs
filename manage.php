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
 * Courses and categories on one page: category sections with course cards, one-click
 * visibility, drag to reorder, and straight into any course.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
if (!has_any_capability(['moodle/category:manage', 'moodle/course:create', 'moodle/course:visibility'], $context)) {
    require_capability('moodle/category:manage', $context);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/lmslabs/manage.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('coursescategories', 'theme_lmslabs'));
$PAGE->set_heading(get_string('coursescategories', 'theme_lmslabs'));
$PAGE->navbar->add(get_string('coursescategories', 'theme_lmslabs'));

$data = \theme_lmslabs\local\catalogue::export();
$data['sesskey'] = sesskey();
$data['ajaxurl'] = (new moodle_url('/theme/lmslabs/ajax/manage.php'))->out(false);
$data['newcategoryurl'] = (new moodle_url('/course/editcategory.php', ['parent' => 0]))->out(false);
$data['newcourseurl'] = (new moodle_url('/course/edit.php', ['category' => $data['defaultcategory']]))->out(false);
$data['classicurl'] = (new moodle_url('/course/management.php', ['classic' => 1]))->out(false);
$data['cancreatecategory'] = has_capability('moodle/category:manage', $context);
$data['strjson'] = json_encode(
    array_combine(
    ['nowvisible', 'nowhidden', 'ordersaved', 'error', 'collapseall', 'expandall'],
    array_map(function ($k) {
        return get_string('cm_' . $k, 'theme_lmslabs', '{name}');
    }, ['nowvisible', 'nowhidden', 'ordersaved', 'error', 'collapseall', 'expandall'])
), JSON_HEX_TAG | JSON_HEX_AMP
);
$data['i'] = [
    'plus' => icons::svg('plus'), 'folder' => icons::svg('folder-plus'), 'search' => icons::svg('search'),
    'chevron' => icons::svg('chevron-down', 'll-ico ll-cat-caret'), 'grip' => icons::svg('grip-vertical'),
    'pencil' => icons::svg('pencil'), 'arrow' => icons::svg('arrow-up-right'), 'users' => icons::svg('users'),
    'more' => icons::svg('ellipsis'), 'layers' => icons::svg('layers'), 'image' => icons::svg('image'), 'lock' => icons::svg('lock'), 'eye' => icons::svg('eye'), 'eyeoff' => icons::svg('eye-off'),
];

$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/manage.js'));
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/manage', $data);
echo $OUTPUT->footer();
