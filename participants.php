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
 * Participants: everyone in the course with progress and last visit; enrol, message and group in one click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\participants;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
require_capability('moodle/course:viewparticipants', $ctx);

$url = new moodle_url('/theme/lmslabs/participants.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('participants') . ': ' . format_string($course->shortname));
$PAGE->set_heading(get_string('participants'));
$PAGE->add_body_class('ll-ptpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/participants.js'));

$rows = participants::rows($course);
$counts = ['all' => count($rows), 'learners' => 0, 'active' => 0, 'idle' => 0, 'never' => 0, 'suspended' => 0];
$progress = [];
foreach ($rows as $r) {
    $counts[$r['status']]++;
    if ($r['learner']) {
        $counts['learners']++;
        if ($r['progress'] !== null) {
            $progress[] = $r['progress'];
        }
    }
}
$roles = [];
foreach (role_fix_names(get_profile_roles($ctx), $ctx, ROLENAME_ALIAS, true) as $rid => $name) {
    $roles[] = ['id' => $rid, 'name' => $name];
}
$groups = [];
foreach (groups_get_all_groups($course->id) as $g) {
    $groups[] = ['id' => $g->id, 'name' => format_string($g->name)];
}
$canenrol = has_capability('enrol/manual:enrol', $ctx);
$canunenrol = has_capability('enrol/manual:unenrol', $ctx);
$canmessage = has_capability('moodle/course:bulkmessaging', $ctx) && !empty($CFG->messaging);
$cangroup = has_capability('moodle/course:managegroups', $ctx) && $groups;
$assignable = $canenrol ? participants::assignable_roles($course) : [];
$student = $DB->get_field('role', 'id', ['shortname' => 'student']);
foreach ($assignable as &$a) {
    $a['selected'] = $a['id'] == $student;
}
unset($a);

$data = [
    'courseid' => $course->id,
    'sesskey' => sesskey(),
    'ajax' => (new moodle_url('/theme/lmslabs/ajax/participants.php'))->out(false),
    'rows' => $rows,
    'hasrows' => (bool)$rows,
    'counts' => $counts,
    'avgprogress' => $progress ? (int)round(array_sum($progress) / count($progress)) : null,
    'roles' => $roles,
    'groups' => $groups,
    'hasgroups' => (bool)$groups,
    'canenrol' => $canenrol,
    'canunenrol' => $canunenrol,
    'canmessage' => $canmessage,
    'cangroup' => $cangroup,
    'canbulk' => $canunenrol || $canmessage || $cangroup,
    'assignable' => $assignable,
    'groupsurl' => (new moodle_url('/theme/lmslabs/groups.php', ['id' => $course->id]))->out(false),
    'enrolurl' => (new moodle_url('/theme/lmslabs/enrolmethods.php', ['id' => $course->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/user/index.php', ['id' => $course->id]),
    'countone' => get_string('bk_count_one', 'theme_lmslabs'),
    'countmany' => get_string('bk_count', 'theme_lmslabs', '{n}'),
    'i' => [
        'userplus' => icons::svg('user-plus'), 'search' => icons::svg('search'), 'send' => icons::svg('send'),
        'users' => icons::svg('users-round'), 'minus' => icons::svg('user-minus'), 'x' => icons::svg('x'),
        'eye' => icons::svg('eye'), 'chart' => icons::svg('chart-column'), 'msg' => icons::svg('message-square'),
        'download' => icons::svg('download'), 'door' => icons::svg('door-open'), 'paste' => icons::svg('file-text'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/participants', $data);
echo $OUTPUT->footer();
