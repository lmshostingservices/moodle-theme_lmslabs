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
 * Course badge actions: create, switch on or off, list people, award by hand.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->libdir . '/filelib.php');

require_login(null, false);
require_sesskey();
$course = get_course(required_param('courseid', PARAM_INT));
$ctx = context_course::instance($course->id);
$PAGE->set_context($ctx);
if (empty($CFG->enablebadges) || empty($CFG->badges_allowcoursebadges)) {
    throw new moodle_exception('badgesdisabled', 'badges');
}
$action = required_param('action', PARAM_ALPHA);
$result = ['ok' => true];

$load = function () use ($course) {
    $badge = new badge(required_param('badgeid', PARAM_INT));
    if ($badge->type != BADGE_TYPE_COURSE || $badge->courseid != $course->id) {
        throw new invalid_parameter_exception('Badge is not in this course');
    }
    return $badge;
};

switch ($action) {
    case 'create':
        require_capability('moodle/badges:createbadge', $ctx);
        $name = trim(required_param('name', PARAM_TEXT));
        if ($name === '') {
            throw new invalid_parameter_exception(get_string('cb_needname', 'theme_lmslabs'));
        }
        $how = optional_param('how', 'course', PARAM_ALPHA);
        $data = (object)[
            'name' => $name, 'version' => '', 'language' => current_language(),
            'description' => trim(optional_param('description', '', PARAM_TEXT)) ?: $name,
            'imagecaption' => '', 'issuername' => $CFG->badges_defaultissuername ?: format_string($SITE->fullname),
            'issuerurl' => $CFG->badges_defaultissuerurl ?: $CFG->wwwroot,
            'issuercontact' => $CFG->badges_defaultissuercontact ?: '', 'expiry' => 0,
        ];
        $badge = badge::create_badge($data, $course->id);
        // The picture: theirs, or a plain one in the brand colour.
        $tmp = null;
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $tmp = make_request_directory() . '/' . clean_param($_FILES['image']['name'], PARAM_FILE);
            move_uploaded_file($_FILES['image']['tmp_name'], $tmp);
        }
        if (!$tmp || !@getimagesize($tmp)) {
            $tmp = \theme_lmslabs\local\badges::default_image();
        }
        badges_process_badge_image($badge, $tmp);
        // The rule.
        $overall = award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_OVERALL, 'badgeid' => $badge->id]);
        $overall->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        if ($how === 'course') {
            $crit = award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_COURSE, 'badgeid' => $badge->id]);
            $crit->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL, 'course_' . $course->id => $course->id]);
        } else {
            $roles = get_roles_with_capability('moodle/badges:awardbadge', CAP_ALLOW, $ctx);
            $params = ['agg' => BADGE_CRITERIA_AGGREGATION_ANY];
            foreach ($roles as $role) {
                $params['role_' . $role->id] = $role->id;
            }
            $crit = award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_MANUAL, 'badgeid' => $badge->id]);
            $crit->save($params);
        }
        if (optional_param('live', 1, PARAM_BOOL)) {
            $badge = new badge($badge->id);
            $badge->set_status(BADGE_STATUS_ACTIVE);
        }
        $result['id'] = (int)$badge->id;
        $result['message'] = get_string('cb_created', 'theme_lmslabs', format_string($name));
        break;

    case 'status':
        require_capability('moodle/badges:configurecriteria', $ctx);
        $badge = $load();
        $on = required_param('on', PARAM_BOOL);
        if ($badge->is_locked()) {
            throw new moodle_exception('error:cannotact', 'badges');
        }
        if ($on && !$badge->has_criteria()) {
            throw new invalid_parameter_exception(get_string('cb_nocriteria', 'theme_lmslabs'));
        }
        $badge->set_status($on ? BADGE_STATUS_ACTIVE : BADGE_STATUS_INACTIVE);
        if ($on && !$badge->has_manual_award_criteria()) {
            // Give it to everyone who already qualifies.
            $badge = new badge($badge->id);
            $badge->review_all_criteria();
        }
        $result['message'] = get_string($on ? 'cb_on' : 'cb_off', 'theme_lmslabs', format_string($badge->name));
        break;

    case 'people':
        require_capability('moodle/badges:awardbadge', $ctx);
        $badge = $load();
        $have = array_keys(
            $badge->get_awards() ? array_column(
            array_map(function ($a) {
            return (array)$a;
        }, $badge->get_awards()), null, 'userid'
        ) : []
        );
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = get_enrolled_users($ctx, '', 0, "u.id, $fields, u.email", 'u.lastname, u.firstname', 0, 0, true);
        $people = [];
        foreach ($users as $u) {
            $people[] = ['id' => (int)$u->id, 'name' => fullname($u), 'email' => $u->email,
                'initials' => \core_text::strtoupper(
                    \core_text::substr($u->firstname, 0, 1) .
                    \core_text::substr($u->lastname, 0, 1)
                ),
                'tint' => $u->id % 6, 'has' => in_array($u->id, $have)];
        }
        $result['people'] = $people;
        break;

    case 'award':
        require_capability('moodle/badges:awardbadge', $ctx);
        $badge = $load();
        if (!$badge->is_active() || !$badge->has_manual_award_criteria()) {
            throw new invalid_parameter_exception(get_string('cb_cantaward', 'theme_lmslabs'));
        }
        $allowed = array_map('intval', array_column($badge->criteria[BADGE_CRITERIA_TYPE_MANUAL]->params, 'role'));
        $issuerrole = 0;
        foreach (get_user_roles($ctx, $USER->id) as $r) {
            if (in_array((int)$r->roleid, $allowed)) {
                $issuerrole = (int)$r->roleid;
                break;
            }
        }
        if (!$issuerrole && is_siteadmin()) {
            $issuerrole = reset($allowed);
        }
        if (!$issuerrole) {
            throw new required_capability_exception($ctx, 'moodle/badges:awardbadge', 'nopermissions', '');
        }
        $enrolled = array_keys(get_enrolled_users($ctx, '', 0, 'u.id', null, 0, 0, true));
        $n = 0;
        foreach (array_filter(array_map('intval', explode(',', required_param('users', PARAM_SEQUENCE)))) as $uid) {
            if (in_array($uid, $enrolled) &&
                    \core_badges\award_manager::process_manual_award($uid, $USER->id, $issuerrole, $badge->id)) {
                // Issue it now, as Moodle's own award screen does.
                badges_award_handle_manual_criteria_review(
                    (object)['crit' => $badge->criteria[BADGE_CRITERIA_TYPE_MANUAL],
                    'userid' => $uid]
                );
                $n++;
            }
        }
        $result['message'] = get_string('cb_awarded', 'theme_lmslabs', $n);
        break;

    default:
        throw new invalid_parameter_exception('Unknown action');
}
echo json_encode($result);
