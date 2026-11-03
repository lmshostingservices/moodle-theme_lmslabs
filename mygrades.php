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
 * Grades: every course at a glance, the latest marks and feedback, one click to the detail.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir . '/completionlib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no grades');
}
$context = context_user::instance($USER->id);
$url = new moodle_url('/theme/lmslabs/mygrades.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('grades', 'grades'));
$PAGE->set_heading(get_string('grades', 'grades'));
$PAGE->add_body_class('ll-gradespage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/mygrades.js'));

$learning = [];
$teaching = [];
$percents = [];
$tint = 0;
foreach (enrol_get_all_users_courses($USER->id, true, 'id, fullname, shortname, showgrades, visible, enablecompletion',
        'fullname ASC') as $course) {
    $ctx = context_course::instance($course->id);
    $card = [
        'id' => $course->id,
        'name' => format_string($course->fullname, true, ['context' => $ctx]),
        'short' => format_string($course->shortname, true, ['context' => $ctx]),
        'tint' => $tint++ % 6,
        'image' => \theme_lmslabs\local\courseimage::url($course),
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ];
    if (has_capability('moodle/grade:viewall', $ctx)) {
        // A course they teach: how many learners, how much is waiting to be marked.
        $card['graderurl'] = (new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]))->out(false);
        $card['gradingurl'] = (new moodle_url('/theme/lmslabs/grading.php', ['id' => $course->id]))->out(false);
        $card['learners'] = count_enrolled_users($ctx, 'mod/assign:submit', 0, true);
        $card['tomark'] = \theme_lmslabs\local\grading::count_waiting($course->id);
        $teaching[] = $card;
        continue;
    }
    if (!$course->showgrades || !has_capability('moodle/grade:view', $ctx)) {
        continue;
    }
    $item = grade_item::fetch_course_item($course->id);
    $grade = new grade_grade(['itemid' => $item->id, 'userid' => $USER->id]);
    $grade->grade_item =& $item;
    $final = $grade->finalgrade;
    if ($final !== null && $grade->is_hidden() && !has_capability('moodle/grade:viewhidden', $ctx)) {
        $final = null;
    }
    $percent = null;
    if ($final !== null && $item->grademax > $item->grademin) {
        $percent = (int)round(($final - $item->grademin) / ($item->grademax - $item->grademin) * 100);
        $percents[] = $percent;
    }
    $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
    $card += [
        'hasgrade' => $final !== null,
        'grade' => $final !== null ? grade_format_gradevalue($final, $item, true) : null,
        'letter' => $final !== null ? grade_format_gradevalue($final, $item, true, GRADE_DISPLAY_TYPE_LETTER) : null,
        'percent' => $percent,
        'dash' => $percent !== null ? round(163.4 * (1 - $percent / 100), 1) : 163.4,
        'band' => $percent === null ? 'none' : ($percent >= 80 ? 'high' : ($percent >= 50 ? 'mid' : 'low')),
        'progress' => $progress === null ? null : (int)round($progress),
        'reporturl' => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id]))->out(false),
    ];
    $learning[] = $card;
}

// Latest marks with feedback, across every course.
$recent = [];
$rs = $DB->get_recordset_sql(
    "SELECT gg.id, gg.finalgrade, gg.feedback, gg.feedbackformat, gg.timemodified, gi.id AS itemid,
                                     gi.itemname, gi.itemmodule, gi.iteminstance, gi.courseid
                                FROM {grade_grades} gg
                                JOIN {grade_items} gi ON gi.id = gg.itemid
                               WHERE gg.userid = :u AND gi.itemtype = 'mod' AND gg.finalgrade IS NOT NULL
                                     AND gg.hidden = 0 AND gi.hidden = 0
                            ORDER BY gg.timemodified DESC", ['u' => $USER->id], 0, 30
);
$names = array_column(array_merge($learning, $teaching), 'name', 'id');
foreach ($rs as $r) {
    if (!isset($names[$r->courseid]) || count($recent) >= 6) {
        continue;
    }
    $item = grade_item::fetch(['id' => $r->itemid]);
    $percent = $item->grademax > $item->grademin
        ? (int)round(($r->finalgrade - $item->grademin) / ($item->grademax - $item->grademin) * 100) : null;
    $cm = get_coursemodule_from_instance($r->itemmodule, $r->iteminstance, $r->courseid, false, IGNORE_MISSING);
    $recent[] = [
        'name' => format_string($r->itemname),
        'course' => $names[$r->courseid],
        'grade' => grade_format_gradevalue($r->finalgrade, $item, true),
        'percent' => $percent,
        'band' => $percent === null ? 'none' : ($percent >= 80 ? 'high' : ($percent >= 50 ? 'mid' : 'low')),
        'feedback' => trim(html_to_text(format_text((string)$r->feedback, $r->feedbackformat), 220, false)),
        'when' => userdate($r->timemodified, get_string('strftimedatefullshort', 'langconfig')),
        'url' => $cm ? (new moodle_url('/mod/' . $r->itemmodule . '/view.php', ['id' => $cm->id]))->out(false) : null,
        'icon' => icons::svg($r->itemmodule === 'quiz' ? 'file-question' : 'file-text'),
    ];
}
$rs->close();

$average = $percents ? (int)round(array_sum($percents) / count($percents)) : null;
$data = [
    'learning' => $learning,
    'haslearning' => (bool)$learning,
    'teaching' => $teaching,
    'hasteaching' => (bool)$teaching,
    'recent' => $recent,
    'hasrecent' => (bool)$recent,
    'average' => $average,
    'hasaverage' => $average !== null,
    'avgband' => $average === null ? 'none' : ($average >= 80 ? 'high' : ($average >= 50 ? 'mid' : 'low')),
    'graded' => count($percents),
    'total' => count($learning),
    'summary' => get_string('gr_summary', 'theme_lmslabs', ['graded' => count($percents), 'total' => count($learning)]),
    'classicurl' => \theme_lmslabs\local\router::classic('/grade/report/overview/index.php'),
    'i' => [
        'chart' => icons::svg('chart-column'), 'trophy' => icons::svg('trophy'), 'arrow' => icons::svg('arrow-up-right'),
        'check' => icons::svg('check-check'), 'users' => icons::svg('users'), 'search' => icons::svg('search'),
        'book' => icons::svg('book-open'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/mygrades', $data);
echo $OUTPUT->footer();
