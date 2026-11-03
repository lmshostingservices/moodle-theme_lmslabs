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
 * Bulk actions from the People page.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/enrollib.php');

require_login(null, false);
require_sesskey();
$context = context_system::instance();

$action = required_param('action', PARAM_ALPHA);
$userids = array_filter(array_map('intval', optional_param_array('users', [], PARAM_INT)));
$return = new moodle_url(required_param('returnurl', PARAM_LOCALURL));
// "Select all matching": rebuild the People filters from the page and take every match.
if (optional_param('allmatching', 0, PARAM_BOOL)) {
    parse_str(optional_param('filterquery', '', PARAM_TEXT), $filters);
    \theme_lmslabs\local\filterbar::$inject = $filters;
    $courses = array_map('intval', (array)($filters['f_course'] ?? []));
    $fb = new \theme_lmslabs\local\filterbar(
        'people', new moodle_url('/theme/lmslabs/bulk.php'),
        \theme_lmslabs\local\people::pills($courses), \theme_lmslabs\local\people::quick_cards(),
        \theme_lmslabs\local\people::sorts(), true
    );
    $userids = \theme_lmslabs\local\people::ids($fb);
    \theme_lmslabs\local\filterbar::$inject = null;
}
if (!$userids) {
    redirect($return);
}
// Never act on the guest user, a site admin (unless you are one), or yourself for suspension.
$userids = array_filter(
    $userids, function ($id) use ($USER, $CFG) {
    return $id != $CFG->siteguest && (is_siteadmin() || !is_siteadmin($id));
}
);

$done = 0;
switch ($action) {
    case 'suspend':
    case 'unsuspend':
        require_capability('moodle/user:update', $context);
        foreach ($userids as $id) {
            if ($action === 'suspend' && $id == $USER->id) {
                continue;
            }
            $user = $DB->get_record('user', ['id' => $id, 'deleted' => 0]);
            if (!$user || $user->suspended == ($action === 'suspend' ? 1 : 0)) {
                continue;
            }
            $change = (object)['id' => $id, 'suspended' => $action === 'suspend' ? 1 : 0];
            if (method_exists('\\core\\user', 'update_user')) {
                \core\user::update_user($change, false, true);
            } else {
                user_update_user($change, false, true);
            }
            if ($action === 'suspend') {
                \core\session\manager::kill_user_sessions($id);
            }
            $done++;
        }
        $message = get_string($action === 'suspend' ? 'bulk_suspended' : 'bulk_unsuspended', 'theme_lmslabs', $done);
        break;
    case 'cohort':
        $cohort = $DB->get_record('cohort', ['id' => required_param('cohortid', PARAM_INT), 'component' => ''], '*', MUST_EXIST);
        require_capability('moodle/cohort:assign', context::instance_by_id($cohort->contextid));
        foreach ($userids as $id) {
            if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $id])) {
                cohort_add_member($cohort->id, $id);
                $done++;
            }
        }
        $message = get_string('bulk_cohort', 'theme_lmslabs', ['n' => $done, 'cohort' => format_string($cohort->name)]);
        break;
    case 'uncohort':
        $cohort = $DB->get_record('cohort', ['id' => required_param('cohortid', PARAM_INT), 'component' => ''], '*', MUST_EXIST);
        require_capability('moodle/cohort:assign', context::instance_by_id($cohort->contextid));
        foreach ($userids as $id) {
            if ($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $id])) {
                cohort_remove_member($cohort->id, $id);
                $done++;
            }
        }
        $message = get_string('bk_uncohorted', 'theme_lmslabs', ['n' => $done, 'cohort' => format_string($cohort->name)]);
        break;
    case 'enrol':
        $courseid = required_param('courseid', PARAM_INT);
        $roleid = required_param('roleid', PARAM_INT);
        $course = get_course($courseid);
        $cctx = context_course::instance($course->id);
        require_capability('enrol/manual:enrol', $cctx);
        if (!in_array($DB->get_field('role', 'shortname', ['id' => $roleid]), ['student', 'teacher', 'editingteacher'])) {
            throw new invalid_parameter_exception('Role not allowed');
        }
        $manual = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instance = $DB->get_record('enrol', ['id' => $manual->add_default_instance($course)]);
        }
        foreach ($userids as $id) {
            if (!is_enrolled($cctx, $id, '', false)) {
                $manual->enrol_user($instance, $id, $roleid);
                $done++;
            }
        }
        $message = get_string('bk_enrolled', 'theme_lmslabs', ['n' => $done, 'course' => format_string($course->fullname)]);
        break;
    case 'message':
        require_capability('moodle/site:sendmessage', $context);
        require_once($CFG->dirroot . '/message/lib.php');
        $text = trim(required_param('message', PARAM_TEXT));
        if ($text === '') {
            redirect($return, get_string('bk_message_empty', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_WARNING);
        }
        foreach ($userids as $id) {
            if ($id == $USER->id || !($to = $DB->get_record('user', ['id' => $id, 'deleted' => 0]))) {
                continue;
            }
            message_post_message($USER, $to, $text, FORMAT_PLAIN);
            $done++;
        }
        $message = get_string('bk_messaged', 'theme_lmslabs', $done);
        break;
    case 'forcepw':
        require_capability('moodle/user:update', $context);
        foreach ($userids as $id) {
            if ($id != $USER->id) {
                set_user_preference('auth_forcepasswordchange', 1, $id);
                $done++;
            }
        }
        $message = get_string('bk_forced', 'theme_lmslabs', $done);
        break;
    case 'download':
        require_capability('moodle/user:update', $context);
        $fields = ['username', 'firstname', 'lastname', 'email', 'idnumber', 'institution', 'department', 'city', 'country',
            'lastaccess', 'suspended'];
        $filename = clean_filename('people-' . userdate(time(), '%Y-%m-%d') . '.csv');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $fields);
        foreach (array_chunk($userids, 500) as $chunk) {
            [$in, $p] = $DB->get_in_or_equal($chunk);
            foreach ($DB->get_records_select('user', "id $in AND deleted = 0", $p, 'lastname, firstname',
                    implode(',', array_merge(['id'], $fields))) as $u) {
                $row = [];
                foreach ($fields as $f) {
                    $row[] = $f === 'lastaccess' ? ($u->lastaccess ? date('Y-m-d H:i', $u->lastaccess) : '') : $u->$f;
                }
                fputcsv($out, $row);
            }
        }
        fclose($out);
        die;
    case 'delete':
        require_capability('moodle/user:delete', $context);
        if (optional_param('confirm', '', PARAM_TEXT) !== 'DELETE') {
            redirect(
                $return, get_string('bk_delete_confirm', 'theme_lmslabs'), null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        foreach ($userids as $id) {
            if ($id == $USER->id || is_siteadmin($id)) {
                continue;
            }
            if ($user = $DB->get_record('user', ['id' => $id, 'deleted' => 0])) {
                delete_user($user);
                $done++;
            }
        }
        $message = get_string('bk_deleted', 'theme_lmslabs', $done);
        break;
    case 'moodle':
        // "More actions" from People: open Bulk user actions with the same filters and these people ticked.
        require_capability('moodle/user:update', $context);
        $SESSION->theme_lmslabs_bulksel = array_values($userids);
        $target = new moodle_url('/theme/lmslabs/bulk.php', $return->params());
        redirect($target);
        break;
    default:
        throw new invalid_parameter_exception('Unknown action');
}
redirect($return, $message, null, \core\output\notification::NOTIFY_SUCCESS);
