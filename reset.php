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
 * Reset: get the course ready for a new intake in one step, keeping every activity and setting.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

use theme_lmslabs\local\icons;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
require_capability('moodle/course:reset', $ctx);

$url = new moodle_url('/theme/lmslabs/reset.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('resetcourse'));
$PAGE->set_heading(get_string('rs_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/reset.js'));

$tab = optional_param('tab', 'now', PARAM_ALPHA) === 'rolling' ? 'rolling' : 'now';
$rolling = null;
if ($tab === 'rolling') {
    if (data_submitted() && confirm_sesskey()) {
        if (optional_param('runnow', 0, PARAM_BOOL)) {
            $ran = \theme_lmslabs\local\rolling::run_course($course);
            redirect(
                new moodle_url($url, ['tab' => 'rolling']), get_string('rl_ran', 'theme_lmslabs', (object)$ran), null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        $old = \theme_lmslabs\local\rolling::settings($course->id);
        \theme_lmslabs\local\rolling::save(
            $course->id, [
            'on' => optional_param('on', 0, PARAM_BOOL),
            'months' => min(120, max(1, optional_param('months', 12, PARAM_INT))),
            'warn' => min(365, max(0, optional_param('warn', 14, PARAM_INT))),
            'after' => optional_param('after', 'restart', PARAM_ALPHA) === 'unenrol' ? 'unenrol' : 'restart',
            'notify' => optional_param('notify', 0, PARAM_BOOL),
            'subject' => clean_param(optional_param('subject', '', PARAM_TEXT), PARAM_TEXT) ?: $old['subject'],
            'body' => clean_param(optional_param('body', '', PARAM_TEXT), PARAM_TEXT) ?: $old['body'],
            'donesubject' => clean_param(optional_param('donesubject', '', PARAM_TEXT), PARAM_TEXT) ?: $old['donesubject'],
            'donebody' => clean_param(optional_param('donebody', '', PARAM_TEXT), PARAM_TEXT) ?: $old['donebody'],
        ]
        );
        redirect(
            new moodle_url($url, ['tab' => 'rolling']), get_string('rl_saved', 'theme_lmslabs'), null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $rs = \theme_lmslabs\local\rolling::settings($course->id);
    $learners = \theme_lmslabs\local\rolling::learners($course, $rs);
    usort(
        $learners, function ($a, $b) {
        return $a['due'] <=> $b['due'];
    }
    );
    $count = ['waiting' => 0, 'soon' => 0, 'due' => 0];
    $upcoming = [];
    foreach ($learners as $l) {
        $count[$l['state']]++;
        if (count($upcoming) < 8) {
            $upcoming[] = ['name' => fullname($l['user']), 'initials' => \core_text::strtoupper(
                \core_text::substr($l['user']->firstname, 0, 1) . \core_text::substr($l['user']->lastname, 0, 1)),
                'tint' => $l['user']->id % 6, 'start' => userdate($l['start'], get_string('strftimedatefullshort', 'langconfig')),
                'due' => userdate($l['due'], get_string('strftimedatefullshort', 'langconfig')),
                'state' => $l['state'], 'statelabel' => get_string('rl_state_' . $l['state'], 'theme_lmslabs'),
                'isdue' => $l['state'] === 'due', 'issoon' => $l['state'] === 'soon'];
        }
    }
    $rolling = $rs + ['afterrestart' => $rs['after'] === 'restart', 'afterunenrol' => $rs['after'] === 'unenrol',
        'upcoming' => $upcoming, 'hasupcoming' => (bool)$upcoming, 'nwaiting' => $count['waiting'], 'nsoon' => $count['soon'],
        'ndue' => $count['due'], 'total' => count($learners)];
}

$student = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
$groups = ['learners', 'work', 'grades', 'completion', 'groupmembers', 'events', 'notes'];

$results = null;
if (data_submitted() && confirm_sesskey() && optional_param('confirm', '', PARAM_ALPHA) === 'RESET') {
    $on = function ($k) {
        return optional_param('rs_' . $k, 0, PARAM_BOOL);
    };
    $data = (object)['id' => $course->id, 'reset_start_date_old' => $course->startdate, 'reset_end_date_old' => $course->enddate];
    $start = optional_param('startdate', '', PARAM_TEXT);
    if ($start) {
        $data->reset_start_date = strtotime($start . ' 00:00');
    }
    if ($on('learners')) {
        $data->unenrol_users = [$student];
    }
    if ($on('work')) {
        // Each activity's own "delete learner work" defaults, the same ones Moodle's reset form ticks.
        foreach (get_fast_modinfo($course)->get_used_module_names() as $modname => $label) {
            $lib = $CFG->dirroot . '/mod/' . $modname . '/lib.php';
            if (file_exists($lib)) {
                include_once($lib);
                $fn = $modname . '_reset_course_form_defaults';
                if (function_exists($fn) && ($defs = $fn($course))) {
                    foreach ($defs as $k => $v) {
                        $data->$k = $v;
                    }
                }
            }
        }
        $data->reset_comments = 1;
    }
    if ($on('grades')) {
        $data->reset_gradebook_grades = 1;
    }
    if ($on('completion')) {
        $data->reset_completion = 1;
    }
    if ($on('groupmembers')) {
        $data->reset_groups_members = 1;
        $data->reset_groupings_members = 1;
    }
    if ($on('events')) {
        $data->reset_events = 1;
    }
    if ($on('notes')) {
        $data->reset_notes = 1;
    }
    $status = reset_course_userdata($data);
    $results = array_map(function ($s) {
        return ['component' => $s['component'], 'item' => $s['item'], 'ok' => empty($s['error'])];
    }, $status);
}

$cards = [];
$icons = ['learners' => 'user-minus', 'work' => 'file-text', 'grades' => 'chart-column', 'completion' => 'circle-check-big',
    'groupmembers' => 'users-round', 'events' => 'calendar-days', 'notes' => 'notebook'];
foreach ($groups as $g) {
    $cards[] = ['key' => $g, 'icon' => icons::svg($icons[$g]), 'label' => get_string('rs_' . $g, 'theme_lmslabs'),
        'desc' => get_string('rs_' . $g . '_desc', 'theme_lmslabs'), 'on' => in_array(
            $g, ['learners', 'work', 'grades', 'completion',
            'groupmembers']
        )];
}
$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'tabnow' => $tab === 'now',
    'tabrolling' => $tab === 'rolling',
    'rolling' => $rolling,
    'nowurl' => $url->out(false),
    'rollingurl' => (new moodle_url($url, ['tab' => 'rolling']))->out(false),
    'rollingaction' => (new moodle_url($url, ['tab' => 'rolling']))->out(false),
    'cards' => $cards,
    'startdate' => date('Y-m-d', max(time(), $course->startdate)),
    'datesdesc' => $course->startdate ? get_string(
        'rs_dates_desc', 'theme_lmslabs',
        userdate($course->startdate, get_string('strftimedate', 'langconfig'))
    ) : get_string('rs_dates_none', 'theme_lmslabs'),
    'learners' => count_enrolled_users($ctx, 'mod/assign:submit'),
    'results' => $results,
    'hasresults' => $results !== null,
    'backupurl' => (new moodle_url('/theme/lmslabs/backup.php', ['id' => $course->id]))->out(false),
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/course/reset.php', ['id' => $course->id]),
    'i' => ['check' => icons::svg('check'), 'calendar' => icons::svg('calendar-days'), 'alert' => icons::svg('circle-alert'),
        'backup' => icons::svg('hard-drive-download'), 'refresh' => icons::svg('refresh'), 'x' => icons::svg('x'),
        'repeat' => icons::svg('repeat'), 'mail' => icons::svg('mail'), 'play' => icons::svg('play'), 'users' => icons::svg('users')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/reset', $data);
echo $OUTPUT->footer();
