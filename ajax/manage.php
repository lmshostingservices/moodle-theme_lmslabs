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
 * Courses and categories actions: show/hide a course, reorder courses within a category.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login(null, false);
require_sesskey();

$action = required_param('action', PARAM_ALPHA);
$result = ['ok' => true];

switch ($action) {
    case 'visibility':
        $courseid = required_param('id', PARAM_INT);
        $show = required_param('show', PARAM_BOOL);
        require_capability('moodle/course:visibility', context_course::instance($courseid));
        course_change_visibility($courseid, $show);
        $result['visible'] = (bool)$DB->get_field('course', 'visible', ['id' => $courseid]);
        break;
    case 'reorder':
        $categoryid = required_param('category', PARAM_INT);
        require_capability('moodle/category:manage', context_coursecat::instance($categoryid));
        $json = required_param('ids', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, cleaned.
        $ids = json_decode($json, true);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $valid = $DB->get_fieldset_select('course', 'id', 'category = ?', [$categoryid]);
        $ids = array_values(array_intersect($ids, $valid));
        // Move each course after the one before it; the first goes to the top.
        $prev = 0;
        foreach ($ids as $id) {
            course_change_sortorder_after_course($id, $prev);
            $prev = $id;
        }
        break;
    default:
        throw new invalid_parameter_exception('Unknown action');
}
echo json_encode($result);
