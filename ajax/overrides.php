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
 * Override actions: people and groups to pick from, save, remove.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use theme_lmslabs\local\overrides;

require_login(null, false);
require_sesskey();
[$course, $cm] = get_course_and_cm_from_cmid(required_param('cmid', PARAM_INT));
require_login($course, false, $cm);
if (!in_array($cm->modname, ['assign', 'quiz'])) {
    throw new invalid_parameter_exception('Not an assignment or quiz');
}
require_capability('mod/' . $cm->modname . ':manageoverrides', $cm->context);
$PAGE->set_context($cm->context);
$action = required_param('action', PARAM_ALPHA);
$result = ['ok' => true];

switch ($action) {
    case 'people':
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = get_enrolled_users($cm->context, '', 0, "u.id, $fields, u.email", 'u.lastname, u.firstname', 0, 0, true);
        $result['people'] = [];
        foreach (array_slice($users, 0, 500, true) as $u) {
            if (!has_capability('mod/' . $cm->modname . ':view', $cm->context, $u) ||
                    has_capability('mod/' . $cm->modname . ':manageoverrides', $cm->context, $u)) {
                continue;
            }
            $result['people'][] = ['id' => (int)$u->id, 'name' => fullname($u), 'email' => $u->email,
                'initials' => \core_text::strtoupper(
                    \core_text::substr($u->firstname, 0, 1) .
                    \core_text::substr($u->lastname, 0, 1)
                ), 'tint' => $u->id % 6];
        }
        $result['groups'] = [];
        foreach (groups_get_all_groups($course->id) as $g) {
            $result['groups'][] = ['id' => (int)$g->id, 'name' => format_string($g->name)];
        }
        break;

    case 'save':
        $values = [];
        foreach (overrides::fields($cm->modname) as $f) {
            $raw = trim(optional_param($f, '', PARAM_TEXT));
            if ($raw === '') {
                continue;
            }
            if (in_array($f, ['timeopen', 'timeclose', 'duedate', 'cutoffdate', 'allowsubmissionsfromdate'])) {
                $ts = overrides::stamp($raw);
                if ($ts === null) {
                    throw new invalid_parameter_exception(get_string('ov_baddate', 'theme_lmslabs'));
                }
                $values[$f] = $ts;
            } else if ($f === 'timelimit') {
                $values[$f] = max(0, (int)$raw) * 60;
            } else if ($f === 'attempts') {
                $values[$f] = max(0, (int)$raw);
            } else {
                $values[$f] = clean_param($raw, PARAM_TEXT);
            }
        }
        $userids = array_filter(array_map('intval', explode(',', optional_param('users', '', PARAM_SEQUENCE))));
        $groupid = optional_param('groupid', 0, PARAM_INT);
        if (!$userids && !$groupid) {
            throw new invalid_parameter_exception(get_string('ov_nobody', 'theme_lmslabs'));
        }
        $n = overrides::save($cm, $userids, $groupid, $values);
        $result['message'] = get_string('ov_saved', 'theme_lmslabs', $n);
        break;

    case 'delete':
        $ids = array_filter(array_map('intval', explode(',', required_param('ids', PARAM_SEQUENCE))));
        overrides::delete($cm, $ids);
        $result['message'] = get_string('ov_removed', 'theme_lmslabs', count($ids));
        break;

    default:
        throw new invalid_parameter_exception('Unknown action');
}
echo json_encode($result);
