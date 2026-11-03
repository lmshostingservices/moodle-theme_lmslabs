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
 * One click: turn a category into a program. Its courses in their current order, shown to
 * learners enrolled in any course in the category. Opens the editor to add unlock rules.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\programs;

require_login(null, false);
require_sesskey();
require_capability('theme/lmslabs:manageprograms', context_system::instance());

$cat = core_course_category::get(required_param('category', PARAM_INT));
$courses = $DB->get_records('course', ['category' => $cat->id], 'sortorder ASC', 'id');
$id = programs::save(
    0, [
    'name' => $cat->get_formatted_name(),
    'description' => '',
    'audience' => ['everyone' => false, 'match' => 'any', 'categories' => [$cat->id]],
    'default' => array_map(function ($c) {
        return ['id' => $c->id];
    }, array_values($courses)),
]
);
redirect(
    new moodle_url('/theme/lmslabs/program.php', ['id' => $id]),
    get_string('cm_programmade', 'theme_lmslabs', $cat->get_formatted_name()), null, \core\output\notification::NOTIFY_SUCCESS
);
