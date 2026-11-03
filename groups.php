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
 * Course groups at a glance: cards with members, one click to manage.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/group/lib.php');

use theme_lmslabs\local\icons;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:managegroups', $context);

$PAGE->set_url(new moodle_url('/theme/lmslabs/groups.php', ['id' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('groups'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->navbar->add(get_string('groups'));

$groups = groups_get_all_groups($courseid, 0, 0, 'g.*', true);
$cards = [];
$ingroup = [];
foreach ($groups as $g) {
    $memberids = array_keys($g->members ?? []);
    foreach ($memberids as $uid) {
        $ingroup[$uid] = true;
    }
    $people = [];
    if ($memberids) {
        $users = $DB->get_records_list(
            'user', 'id', array_slice($memberids, 0, 5), 'lastname',
            'id, ' . implode(', ', \core_user\fields::get_name_fields())
        );
        foreach ($users as $u) {
            $people[] = ['initials' => core_text::strtoupper(
                core_text::substr($u->firstname, 0, 1) .
                core_text::substr($u->lastname, 0, 1)
            ), 'name' => fullname($u), 'tint' => $u->id % 6];
        }
    }
    $count = count($memberids);
    $cards[] = [
        'id' => $g->id,
        'name' => format_string($g->name, true, ['context' => $context]),
        'description' => shorten_text(strip_tags(format_text($g->description, $g->descriptionformat, ['context' => $context])), 120),
        'count' => $count,
        'people' => $people,
        'more' => max(0, $count - 5),
        'hasmore' => $count > 5,
        'idnumber' => $g->idnumber,
        'enrolkey' => !empty($g->enrolmentkey),
        'membersurl' => (new moodle_url('/theme/lmslabs/members.php', ['type' => 'group', 'id' => $g->id]))->out(false),
        'editurl' => (new moodle_url('/group/group.php', ['courseid' => $courseid, 'id' => $g->id]))->out(false),
        'deleteurl' => (new moodle_url('/group/delete.php', ['courseid' => $courseid, 'groups' => $g->id]))->out(false),
    ];
}
$enrolled = count_enrolled_users($context, '', 0, true);
$ungrouped = max(0, $enrolled - count($ingroup));

$data = [
    'groups' => $cards,
    'hasgroups' => (bool)$cards,
    'stats' => [
        ['label' => get_string('stat_groups', 'theme_lmslabs'), 'value' => count($cards)],
        ['label' => get_string('stat_participants', 'theme_lmslabs'), 'value' => $enrolled],
        ['label' => get_string('stat_ungrouped', 'theme_lmslabs'), 'value' => $ungrouped, 'warn' => $ungrouped > 0],
    ],
    'createurl' => (new moodle_url('/group/group.php', ['courseid' => $courseid]))->out(false),
    'autourl' => (new moodle_url('/group/autogroup.php', ['courseid' => $courseid]))->out(false),
    'importurl' => (new moodle_url('/group/import.php', ['id' => $courseid]))->out(false),
    'overviewurl' => (new moodle_url('/group/overview.php', ['id' => $courseid]))->out(false),
    'groupingsurl' => (new moodle_url('/group/groupings.php', ['id' => $courseid]))->out(false),
    'classicurl' => (new moodle_url('/group/index.php', ['id' => $courseid, 'classic' => 1]))->out(false),
    'i' => [
        'plus' => icons::svg('plus'), 'users' => icons::svg('users'), 'pencil' => icons::svg('pencil'),
        'x' => icons::svg('x'), 'sparkles' => icons::svg('sparkles'), 'upload' => icons::svg('upload'),
        'layers' => icons::svg('layers'), 'chart' => icons::svg('chart-column'), 'group' => icons::svg('users-round'),
        'key' => icons::svg('key-round'), 'arrow' => icons::svg('arrow-up-right'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/groups', $data);
echo $OUTPUT->footer();
