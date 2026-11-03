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
 * Manage who is in a course group or a cohort.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;

$type = required_param('type', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);

if ($type === 'group') {
    $group = $DB->get_record('groups', ['id' => $id], '*', MUST_EXIST);
    $course = get_course($group->courseid);
    require_login($course);
    $PAGE->set_url(new moodle_url('/theme/lmslabs/members.php', ['type' => 'group', 'id' => $id]));
    $PAGE->set_pagelayout('admin');
    $members = new \theme_lmslabs\local\members('group', $id);
    $name = format_string($group->name, true, ['context' => $PAGE->context]);
    $backurl = new moodle_url('/theme/lmslabs/groups.php', ['id' => $course->id]);
    $PAGE->navbar->add(get_string('groups'), $backurl);
    $PAGE->navbar->add($name);
    $intro = get_string('members_group_intro', 'theme_lmslabs');
    $candidatestitle = get_string('members_group_candidates', 'theme_lmslabs');
    $editurl = (new moodle_url('/group/group.php', ['courseid' => $course->id, 'id' => $id]))->out(false);
    $classicurl = (new moodle_url('/group/members.php', ['group' => $id, 'classic' => 1]))->out(false);
    $heading = format_string($course->fullname, true, ['context' => $PAGE->context]);
} else {
    require_login(null, false);
    $cohort = $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
    $context = context::instance_by_id($cohort->contextid);
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/theme/lmslabs/members.php', ['type' => 'cohort', 'id' => $id]));
    $PAGE->set_pagelayout('admin');
    $members = new \theme_lmslabs\local\members('cohort', $id);
    $name = format_string($cohort->name, true, ['context' => $context]);
    $backurl = new moodle_url(
        '/cohort/index.php', $context->contextlevel == CONTEXT_COURSECAT ?
        ['contextid' => $context->id] : []
    );
    $PAGE->navbar->add(get_string('cohorts', 'cohort'), $backurl);
    $PAGE->navbar->add($name);
    $intro = get_string('members_cohort_intro', 'theme_lmslabs');
    $candidatestitle = get_string('members_cohort_candidates', 'theme_lmslabs');
    $editurl = $context->id == SYSCONTEXTID
        ? (new moodle_url('/theme/lmslabs/cohorts.php', ['open' => $id]))->out(false)
        : (new moodle_url('/cohort/edit.php', ['id' => $id]))->out(false);
    $classicurl = (new moodle_url('/cohort/assign.php', ['id' => $id, 'classic' => 1]))->out(false);
    $heading = get_string('cohorts', 'cohort');
}

$PAGE->set_title($name);
$PAGE->set_heading($heading);
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/members.js'));
$PAGE->requires->strings_for_js(['members_added', 'members_removed', 'savefailed', 'members_more', 'members_none',
    'members_nomatch', 'members_selected'], 'theme_lmslabs');

$data = [
    'type' => $type,
    'id' => $id,
    'name' => $name,
    'isgroup' => $type === 'group',
    'intro' => $intro,
    'candidatestitle' => $candidatestitle,
    'addlabel' => get_string($type === 'group' ? 'members_add' : 'members_add_cohort', 'theme_lmslabs'),
    'backurl' => $backurl->out(false),
    'editurl' => $editurl,
    'classicurl' => $classicurl,
    'ajaxurl' => (new moodle_url('/theme/lmslabs/ajax/members.php'))->out(false),
    'sesskey' => sesskey(),
    'i' => [
        'search' => icons::svg('search'), 'back' => icons::svg('arrow-left'), 'plus' => icons::svg('plus'),
        'x' => icons::svg('x'), 'pencil' => icons::svg('pencil'), 'users' => icons::svg('users'),
        'userplus' => icons::svg('user-plus'), 'group' => $type === 'group' ? icons::svg('users-round') : icons::svg('contact-round'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/members', $data);
echo $OUTPUT->footer();
