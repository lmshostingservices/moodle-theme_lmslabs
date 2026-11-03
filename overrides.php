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
 * Overrides: pick an assignment or quiz, pick people or a group, change dates, time or attempts, done.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\overrides;

$cmid = optional_param('cmid', 0, PARAM_INT);
if ($cmid) {
    [$course, $cm] = get_course_and_cm_from_cmid($cmid);
} else {
    $course = get_course(required_param('id', PARAM_INT));
    $cm = null;
}
require_login($course);
$ctx = context_course::instance($course->id);
if ($cm) {
    if (!in_array($cm->modname, ['assign', 'quiz'])) {
        throw new moodle_exception('invalidcoursemodule');
    }
    require_capability('mod/' . $cm->modname . ':manageoverrides', $cm->context);
}
$url = new moodle_url('/theme/lmslabs/overrides.php', $cm ? ['cmid' => $cm->id] : ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('ov_title', 'theme_lmslabs'));
$PAGE->set_heading(get_string('ov_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/overrides.js'));

$acts = overrides::activities($course);
$data = [
    'courseid' => $course->id,
    'sesskey' => sesskey(),
    'ajax' => (new moodle_url('/theme/lmslabs/ajax/overrides.php'))->out(false),
    'activities' => array_map(function ($a) use ($cm, $PAGE) {
        $a['selected'] = $cm && $a['cmid'] == $cm->id;
        $a['icon'] = icons::svg($a['type'] === 'quiz' ? 'file-question' : 'file-text');
        $a['isquiz'] = $a['type'] === 'quiz';
        return $a;
    }, $acts),
    'hasactivities' => (bool)$acts,
    'picked' => (bool)$cm,
    'i' => [
        'search' => icons::svg('search'), 'x' => icons::svg('x'), 'trash' => icons::svg('trash'),
        'users' => icons::svg('users'), 'clock' => icons::svg('clock'), 'plus' => icons::svg('plus'),
        'check' => icons::svg('check'), 'pencil' => icons::svg('pencil'),
    ],
];
if ($cm) {
    $inst = overrides::instance($cm);
    $isquiz = $cm->modname === 'quiz';
    $fields = [];
    foreach (overrides::fields($cm->modname) as $f) {
        $type = in_array($f, ['timelimit', 'attempts']) ? 'number' : ($f === 'password' ? 'text' : 'datetime-local');
        $val = $inst->$f ?? 0;
        $fields[] = [
            'name' => $f, 'label' => get_string('ov_f_' . $f, 'theme_lmslabs'), 'tip' => get_string('ov_tip_' . $f, 'theme_lmslabs'),
            'date' => $type === 'datetime-local', 'number' => $type === 'number', 'text' => $type === 'text',
            'base' => $type === 'datetime-local' ? overrides::input((int)$val) : ($f === 'timelimit' ? (int)round($val / 60) : $val),
            'hasbase' => (bool)$val,
            'shown' => $type === 'datetime-local' ? ($val ? overrides::show((int)$val) : get_string('ov_nodate', 'theme_lmslabs'))
                : ($f === 'timelimit' ? ($val ? format_time($val) : get_string('cb_unlimited', 'theme_lmslabs'))
                    : ($f === 'attempts' ? ($val ?: get_string('unlimited')) : '')),
            'unit' => $f === 'timelimit' ? get_string('minutes') : '',
        ];
    }
    $quick = $isquiz ? [
        ['label' => get_string('ov_q_time10', 'theme_lmslabs'), 'act' => 'timelimit', 'delta' => 10],
        ['label' => get_string('ov_q_time25', 'theme_lmslabs'), 'act' => 'timelimitpct', 'delta' => 25],
        ['label' => get_string('ov_q_attempt', 'theme_lmslabs'), 'act' => 'attempts', 'delta' => 1],
        ['label' => get_string('ov_q_week', 'theme_lmslabs'), 'act' => 'timeclose', 'delta' => 7 * 1440],
    ] : [
        ['label' => get_string('ov_q_day', 'theme_lmslabs'), 'act' => 'duedate', 'delta' => 1440],
        ['label' => get_string('ov_q_3days', 'theme_lmslabs'), 'act' => 'duedate', 'delta' => 3 * 1440],
        ['label' => get_string('ov_q_week_a', 'theme_lmslabs'), 'act' => 'duedate', 'delta' => 7 * 1440],
    ];
    // Only offer quick buttons that have something to build on.
    $quick = array_values(
        array_filter(
        $quick, function ($q) use ($inst, $isquiz) {
        $map = ['timelimit' => 'timelimit', 'timelimitpct' => 'timelimit', 'attempts' => 'attempts',
            'timeclose' => 'timeclose', 'duedate' => 'duedate'];
        return !empty($inst->{$map[$q['act']]});
    }
    )
    );
    $data += [
        'cmid' => $cm->id,
        'name' => format_string($cm->name),
        'type' => $cm->modname,
        'isquiz' => $isquiz,
        'fields' => $fields,
        'quick' => $quick,
        'hasquick' => (bool)$quick,
        'rows' => overrides::rows($cm),
        'activityurl' => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
        'classicurl' => \theme_lmslabs\local\router::classic('/mod/' . $cm->modname . '/overrides.php', ['cmid' => $cm->id]),
        'attempts' => (int)($inst->attempts ?? 0),
        'timelimitmin' => (int)round(($inst->timelimit ?? 0) / 60),
    ];
    $data['hasrows'] = (bool)$data['rows'];
    $data['rowcount'] = count($data['rows']);
} else {
    $data['classicurl'] = '';
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/overrides', $data);
echo $OUTPUT->footer();
