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
 * Inbox helpers that Moodle's message web services don't cover: who the other person is in your courses,
 * and messaging a whole course in one go.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->libdir . '/completionlib.php');

require_login(null, false);
require_sesskey();
$PAGE->set_context(context_user::instance($USER->id));
if (empty($CFG->messaging)) {
    throw new moodle_exception('disabled', 'message');
}

$action = required_param('action', PARAM_ALPHA);
$result = ['ok' => true];

switch ($action) {
    case 'person':
        // Shared courses, with the learner's progress where the viewer teaches them.
        $userid = required_param('userid', PARAM_INT);
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        $mine = enrol_get_all_users_courses($USER->id, true);
        $theirs = enrol_get_all_users_courses($userid, true);
        $courses = [];
        foreach (array_intersect_key($theirs, $mine) as $course) {
            $ctx = context_course::instance($course->id);
            $row = [
                'id' => $course->id,
                'name' => format_string($course->fullname, true, ['context' => $ctx]),
                'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'progress' => null,
            ];
            if (has_capability('moodle/grade:viewall', $ctx) || $userid == $USER->id) {
                $pct = \core_completion\progress::get_course_progress_percentage($course, $userid);
                $row['progress'] = $pct === null ? null : (int)round($pct);
                $row['gradesurl'] = (new moodle_url('/grade/report/user/index.php', ['id' => $course->id, 'userid' => $userid]))
                    ->out(false);
            }
            $roles = get_user_roles($ctx, $userid, false);
            $row['role'] = $roles ? role_get_name(reset($roles), $ctx) : '';
            $courses[] = $row;
        }
        $canviewemail = has_capability('moodle/site:viewuseridentity', context_system::instance()) ||
            $user->maildisplay == 1;
        $result['person'] = [
            'id' => $user->id,
            'name' => fullname($user),
            'email' => $canviewemail ? $user->email : '',
            'lastaccess' => $user->lastaccess ? get_string('ago', 'message', implode(' ', array_slice(explode(' ', format_time(time() - $user->lastaccess)), 0, 2))) : get_string('never'),
            'profileurl' => (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
            'courses' => $courses,
        ];
        break;

    case 'courses':
        // Courses where the viewer may message everyone at once.
        $out = [];
        foreach (enrol_get_all_users_courses($USER->id, true) as $course) {
            $ctx = context_course::instance($course->id);
            if (has_capability('moodle/course:bulkmessaging', $ctx)) {
                $out[] = ['id' => $course->id, 'name' => format_string($course->fullname, true, ['context' => $ctx]),
                    'learners' => count_enrolled_users($ctx, 'mod/assign:submit', 0, true)];
            }
        }
        $result['courses'] = $out;
        break;

    case 'broadcast':
        // One message to every active learner in a course, each as their own private conversation.
        $courseid = required_param('courseid', PARAM_INT);
        $text = trim(required_param('text', PARAM_TEXT));
        $ctx = context_course::instance($courseid);
        require_capability('moodle/course:bulkmessaging', $ctx);
        require_capability('moodle/site:sendmessage', context_system::instance());
        if ($text === '') {
            throw new invalid_parameter_exception('Empty message');
        }
        $sent = 0;
        foreach (get_enrolled_users($ctx, 'mod/assign:submit', 0, 'u.*', null, 0, 0, true) as $to) {
            if ($to->id == $USER->id || $to->suspended) {
                continue;
            }
            if (message_post_message($USER, $to, $text, FORMAT_PLAIN)) {
                $sent++;
            }
        }
        $result['sent'] = $sent;
        $result['message'] = get_string('msg_broadcastsent', 'theme_lmslabs', $sent);
        break;

    default:
        throw new invalid_parameter_exception('Unknown action');
}

echo json_encode($result);
