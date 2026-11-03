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
 * Course reports: how the course is going at a glance, who needs a nudge, and every report one click away.
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
require_capability('moodle/site:viewreports', $ctx);

$url = new moodle_url('/theme/lmslabs/reports.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('reports') . ': ' . format_string($course->shortname));
$PAGE->set_heading(get_string('reports'));
$PAGE->add_body_class('ll-reportspage');

$rows = array_values(
    array_filter(
    participants::rows($course), function ($r) {
    return $r['learner'];
}
)
);
$learners = count($rows);
$active7 = count(
    array_filter(
    $rows, function ($r) {
    return $r['lastts'] > time() - 7 * DAYSECS;
}
)
);
$prog = array_filter(
    array_column($rows, 'progress'), function ($p) {
    return $p !== null;
}
);
$avg = $prog ? (int)round(array_sum($prog) / count($prog)) : null;
$completed = $DB->count_records_select('course_completions', 'course = ? AND timecompleted IS NOT NULL', [$course->id]);

// Activity over the last 14 days (views and actions in the standard log).
$days = [];
$from = usergetmidnight(time()) - 13 * DAYSECS;
$counts = [];
try {
    $counts = $DB->get_records_sql_menu(
        "SELECT CAST(FLOOR((timecreated - :f) / 86400) AS INTEGER) AS d, COUNT(1)
                                           FROM {logstore_standard_log}
                                          WHERE courseid = :c AND timecreated >= :f2 AND anonymous = 0
                                       GROUP BY CAST(FLOOR((timecreated - :f3) / 86400) AS INTEGER)",
        ['f' => $from, 'f2' => $from, 'f3' => $from, 'c' => $course->id]
    );
} catch (\Throwable $e) {
    $counts = [];
}
$counts = array_combine(array_map('intval', array_keys($counts)), array_values($counts));
$max = max(1, max($counts ?: [0]));
for ($i = 0; $i < 14; $i++) {
    $n = (int)($counts[$i] ?? 0);
    $days[] = ['n' => $n, 'h' => max(2, round($n / $max * 100)), 'label' => userdate($from + $i * DAYSECS, '%a %d'),
        'today' => $i === 13];
}

// Who needs a nudge: never started, then longest away.
$attention = array_values(
    array_filter(
    $rows, function ($r) {
    return in_array($r['status'], ['never', 'idle']);
}
)
);
usort(
    $attention, function ($a, $b) {
    return $a['lastts'] <=> $b['lastts'];
}
);
$attention = array_slice($attention, 0, 6);

$links = [];
$add = function ($icon, $name, $desc, $path, $params, $cap) use (&$links, $ctx) {
    if ($cap && !has_capability($cap, $ctx)) {
        return;
    }
    if (!file_exists($GLOBALS['CFG']->dirroot . $path)) {
        return;
    }
    $links[] = ['icon' => icons::svg($icon), 'name' => $name, 'desc' => $desc,
        'url' => (new moodle_url($path, $params))->out(false)];
};
$add(
    'circle-check-big', get_string('rp_progress', 'theme_lmslabs'), get_string('ph_progress', 'theme_lmslabs'),
    '/report/progress/index.php', ['course' => $course->id], 'report/progress:view'
);
$add(
    'chart-column', get_string('grades', 'grades'), get_string('ph_grader', 'theme_lmslabs'),
    '/grade/report/grader/index.php', ['id' => $course->id], 'gradereport/grader:view'
);
$add(
    'scroll-text', get_string('rp_logs', 'theme_lmslabs'), get_string('ph_logs', 'theme_lmslabs'),
    '/report/log/index.php', ['id' => $course->id], 'report/log:view'
);
$add(
    'activity', get_string('rp_outline', 'theme_lmslabs'), get_string('ph_outline', 'theme_lmslabs'),
    '/report/outline/index.php', ['id' => $course->id], 'report/outline:view'
);
$add(
    'users', get_string('rp_participation', 'theme_lmslabs'), get_string('ph_participation', 'theme_lmslabs'),
    '/report/participation/index.php', ['id' => $course->id], 'report/participation:view'
);
$add(
    'trophy', get_string('rp_completion', 'theme_lmslabs'), get_string('ph_coursecompletion', 'theme_lmslabs'),
    '/report/completion/index.php', ['course' => $course->id], 'report/completion:view'
);
$add(
    'clock', get_string('rp_live', 'theme_lmslabs'), get_string('rp_live_desc', 'theme_lmslabs'),
    '/report/loglive/index.php', ['id' => $course->id], 'report/loglive:view'
);

$data = [
    'learners' => $learners,
    'active7' => $active7,
    'activepct' => $learners ? (int)round($active7 / $learners * 100) : 0,
    'avg' => $avg,
    'hasavg' => $avg !== null,
    'completed' => $completed,
    'days' => $days,
    'attention' => $attention,
    'hasattention' => (bool)$attention,
    'links' => $links,
    'participantsurl' => (new moodle_url('/theme/lmslabs/participants.php', ['id' => $course->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/report/view.php', ['courseid' => $course->id]),
    'i' => ['users' => icons::svg('users'), 'activity' => icons::svg('activity'), 'chart' => icons::svg('chart-column'),
        'trophy' => icons::svg('trophy'), 'msg' => icons::svg('message-square'), 'arrow' => icons::svg('arrow-up-right'),
        'alert' => icons::svg('circle-alert')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/reports', $data);
echo $OUTPUT->footer();
