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
 * Course completion: one plain choice for when the course is finished, and the activities that count.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
require_once($CFG->dirroot . '/course/lib.php');

use theme_lmslabs\local\icons;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
require_capability('moodle/course:update', $ctx);

$url = new moodle_url('/theme/lmslabs/completion.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursecompletion'));
$PAGE->set_heading(get_string('coursecompletion'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/completion.js'));

$completion = new completion_info($course);

// Turn tracking on.
if (optional_param('enable', 0, PARAM_BOOL) && confirm_sesskey()) {
    update_course((object)['id' => $course->id, 'enablecompletion' => 1]);
    redirect($url, get_string('cp_enabled', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_SUCCESS);
}
// Unlock (deletes completion data so the rules can change).
if (optional_param('unlock', 0, PARAM_BOOL) && confirm_sesskey()) {
    $completion->delete_course_completion_data();
    redirect($url, get_string('cp_unlocked', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_WARNING);
}

// Activities that track completion.
$modinfo = get_fast_modinfo($course);
$activities = [];
foreach ($modinfo->get_cms() as $cm) {
    if ($cm->completion == COMPLETION_TRACKING_NONE || $cm->deletioninprogress) {
        continue;
    }
    $activities[$cm->id] = $cm;
}

// Save.
if (data_submitted() && confirm_sesskey() && optional_param('save', 0, PARAM_BOOL)) {
    $rule = optional_param('rule', 'all', PARAM_ALPHA);
    $data = (object)['id' => $course->id, 'overall_aggregation' => COMPLETION_AGGREGATION_ALL,
        'activity_aggregation' => COMPLETION_AGGREGATION_ALL];
    if ($rule === 'all') {
        $data->criteria_activity = array_fill_keys(array_keys($activities), 1);
    } else if ($rule === 'some') {
        $picked = array_intersect(array_map('intval', optional_param_array('cms', [], PARAM_INT)), array_keys($activities));
        $data->criteria_activity = array_fill_keys($picked, 1);
    } else if ($rule === 'date') {
        $date = optional_param('date', '', PARAM_TEXT);
        $data->criteria_date = 1;
        $data->criteria_date_value = $date ? strtotime($date . ' 00:00') : time() + 30 * DAYSECS;
    } else if ($rule === 'teacher') {
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $data->criteria_role = [$role => 1];
    }
    if (optional_param('self', 0, PARAM_BOOL)) {
        $data->criteria_self = 1;
        if ($rule !== 'self') {
            $data->overall_aggregation = COMPLETION_AGGREGATION_ANY;
        }
    }
    $completion->clear_criteria(false);
    global $COMPLETION_CRITERIA_TYPES;
    foreach ($COMPLETION_CRITERIA_TYPES as $type) {
        $class = 'completion_criteria_' . $type;
        (new $class())->update_config($data);
    }
    foreach ([null => $data->overall_aggregation, COMPLETION_CRITERIA_TYPE_ACTIVITY => $data->activity_aggregation,
            COMPLETION_CRITERIA_TYPE_COURSE => 0, COMPLETION_CRITERIA_TYPE_ROLE => 0] as $type => $method) {
        $agg = new completion_aggregation(['course' => $course->id, 'criteriatype' => $type ?: null]);
        $agg->setMethod($method);
        $agg->save();
    }
    \core\event\course_completion_updated::create(['courseid' => $course->id, 'context' => $ctx])->trigger();
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Current rule, read back from the criteria.
$criteria = $completion->get_criteria();
$current = 'none';
$chosen = [];
$date = '';
$self = false;
foreach ($criteria as $c) {
    switch ($c->criteriatype) {
        case COMPLETION_CRITERIA_TYPE_ACTIVITY:
            $chosen[] = (int)$c->moduleinstance;
            break;
        case COMPLETION_CRITERIA_TYPE_DATE:
            $current = 'date';
            $date = date('Y-m-d', $c->timeend);
            break;
        case COMPLETION_CRITERIA_TYPE_ROLE:
            $current = 'teacher';
            break;
        case COMPLETION_CRITERIA_TYPE_SELF:
            $self = true;
            break;
    }
}
if ($chosen) {
    $current = count($chosen) >= count($activities) ? 'all' : 'some';
}
$rows = [];
foreach ($activities as $cm) {
    $rows[] = ['id' => $cm->id, 'name' => $cm->get_formatted_name(), 'section' => get_section_name($course, $cm->sectionnum),
        'icon' => $cm->get_icon_url()->out(false), 'checked' => in_array($cm->id, $chosen) || $current !== 'some'];
}
$learners = count_enrolled_users($ctx, 'moodle/course:isincompletionreports');
$done = $DB->count_records_select('course_completions', 'course = ? AND timecompleted IS NOT NULL', [$course->id]);

$rules = [];
foreach (['all' => 'circle-check-big', 'some' => 'list-checks', 'date' => 'calendar-days', 'teacher' => 'user-check'] as $key => $icon) {
    $rules[] = ['key' => $key, 'icon' => icons::svg($icon), 'label' => get_string('cp_rule_' . $key, 'theme_lmslabs'),
        'desc' => get_string('cp_rule_' . $key . '_desc', 'theme_lmslabs'), 'on' => $current === $key || ($current === 'none' && $key === 'all')];
}

$data = [
    'sesskey' => sesskey(),
    'action' => $url->out(false),
    'enabled' => $completion->is_enabled(),
    'locked' => $completion->is_course_locked(),
    'rules' => $rules,
    'rows' => $rows,
    'hasrows' => (bool)$rows,
    'activitycount' => count($rows),
    'date' => $date ?: date('Y-m-d', time() + 90 * DAYSECS),
    'self' => $self,
    'learners' => $learners,
    'done' => $done,
    'notset' => $current === 'none',
    'reporturl' => (new moodle_url('/report/completion/index.php', ['course' => $course->id]))->out(false),
    'bulkurl' => (new moodle_url('/course/bulkcompletion.php', ['id' => $course->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/course/completion.php', ['id' => $course->id]),
    'i' => ['check' => icons::svg('check'), 'trophy' => icons::svg('trophy'), 'lock' => icons::svg('lock'),
        'toggle' => icons::svg('toggle'), 'arrow' => icons::svg('arrow-up-right'), 'user' => icons::svg('user-round'),
        'list' => icons::svg('list-checks')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/completion', $data);
echo $OUTPUT->footer();
