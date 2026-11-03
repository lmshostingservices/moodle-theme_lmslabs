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
 * Participants actions: search, enrol, remove, message, add to group.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/message/lib.php');

require_login(null, false);
require_sesskey();
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$ctx = context_course::instance($course->id);
$PAGE->set_context($ctx);
require_capability('moodle/course:viewparticipants', $ctx);

$action = required_param('action', PARAM_ALPHA);
$ids = array_filter(array_map('intval', explode(',', optional_param('users', '', PARAM_SEQUENCE))));
$result = ['ok' => true];

switch ($action) {
    case 'search':
        require_capability('enrol/manual:enrol', $ctx);
        $result['people'] = \theme_lmslabs\local\participants::search($course, optional_param('q', '', PARAM_TEXT));
        break;

    case 'enrol':
        require_capability('enrol/manual:enrol', $ctx);
        $roleid = required_param('roleid', PARAM_INT);
        $assignable = get_assignable_roles($ctx);
        if (!isset($assignable[$roleid])) {
            throw new invalid_parameter_exception('Role not allowed');
        }
        // Emails or usernames pasted in are resolved too.
        $raw = trim(optional_param('paste', '', PARAM_TEXT));
        $missing = [];
        foreach (array_filter(array_map('trim', preg_split('/[\s,;]+/', core_text::strtolower($raw)))) as $t) {
            $id = $DB->get_field_select(
                'user', 'id', 'deleted = 0 AND mnethostid = :h AND (LOWER(email) = :e OR LOWER(username) = :u)',
                ['h' => $CFG->mnet_localhost_id, 'e' => $t, 'u' => $t], IGNORE_MULTIPLE
            );
            if ($id) {
                $ids[] = (int)$id;
            } else {
                $missing[] = $t;
            }
        }
        $manual = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instance = $DB->get_record('enrol', ['id' => $manual->add_default_instance($course)]);
        }
        $done = 0;
        foreach (array_unique($ids) as $uid) {
            if ($uid == $CFG->siteguest) {
                continue;
            }
            if (!is_enrolled($ctx, $uid, '', false)) {
                $done++;
            }
            $manual->enrol_user($instance, $uid, $roleid);
        }
        $result['message'] = get_string('pt_enrolled', 'theme_lmslabs', $done) .
            ($missing ? ' ' . get_string('ch_missing', 'theme_lmslabs', s(implode(', ', array_slice($missing, 0, 8)))) : '');
        break;

    case 'unenrol':
        // Only manual enrolments are removed; other methods (cohort sync, self) are left alone.
        require_capability('enrol/manual:unenrol', $ctx);
        $manual = enrol_get_plugin('manual');
        $done = 0;
        foreach ($DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']) as $instance) {
            foreach ($ids as $uid) {
                if ($uid == $USER->id) {
                    continue;
                }
                if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $uid])) {
                    $manual->unenrol_user($instance, $uid);
                    $done++;
                }
            }
        }
        $result['message'] = get_string('pt_removed', 'theme_lmslabs', $done);
        break;

    case 'message':
        require_capability('moodle/course:bulkmessaging', $ctx);
        $text = trim(required_param('text', PARAM_TEXT));
        $done = 0;
        foreach ($ids as $uid) {
            if ($uid != $USER->id && ($to = core_user::get_user($uid)) && is_enrolled($ctx, $uid, '', false)) {
                message_post_message($USER, $to, $text, FORMAT_PLAIN);
                $done++;
            }
        }
        $result['message'] = get_string('bk_messaged', 'theme_lmslabs', $done);
        break;

    case 'group':
        require_capability('moodle/course:managegroups', $ctx);
        $groupid = required_param('groupid', PARAM_INT);
        $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $course->id], '*', MUST_EXIST);
        $done = 0;
        foreach ($ids as $uid) {
            if (is_enrolled($ctx, $uid, '', false) && groups_add_member($group, $uid)) {
                $done++;
            }
        }
        $result['message'] = get_string('pt_grouped', 'theme_lmslabs', ['n' => $done, 'group' => format_string($group->name)]);
        break;

    default:
        throw new invalid_parameter_exception('Unknown action');
}
echo json_encode($result);
