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


namespace theme_lmslabs\local;

use context_course;
use moodle_url;

/**
 * Course gradebook: learners down the side, graded items across the top, edit a mark by typing in the cell.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook {
    /** Most learners shown on one page. */
    const LIMIT = 500;

    /**
     * Everything the page needs.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function export(\stdClass $course): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/lib.php');
        $ctx = context_course::instance($course->id);
        $canedit = has_capability('moodle/grade:edit', $ctx);
        \grade_regrade_final_grades($course->id);

        $items = [];
        $total = null;
        foreach (\grade_item::fetch_all(['courseid' => $course->id]) ?: [] as $gi) {
            if ($gi->itemtype === 'course') {
                $total = $gi;
            } else if ($gi->itemtype === 'mod' || $gi->itemtype === 'manual') {
                $items[$gi->id] = $gi;
            }
        }
        uasort(
            $items, function ($a, $b) {
            return ($a->sortorder ?? 0) <=> ($b->sortorder ?? 0);
        }
        );
        if ($total) {
            $items[$total->id] = $total;
        }

        $cols = [];
        foreach ($items as $gi) {
            $istotal = $gi->itemtype === 'course';
            $editable = $canedit && !$istotal && $gi->gradetype == GRADE_TYPE_VALUE && !$gi->is_locked();
            $url = null;
            $icon = $istotal ? 'chart-column' : 'file-text';
            if ($gi->itemtype === 'mod') {
                $cm = get_coursemodule_from_instance($gi->itemmodule, $gi->iteminstance, $course->id, false, IGNORE_MISSING);
                $url = $cm ? (new moodle_url('/mod/' . $gi->itemmodule . '/view.php', ['id' => $cm->id]))->out(false) : null;
                $icon = $gi->itemmodule === 'quiz' ? 'file-question' : 'file-text';
            }
            $cols[] = [
                'id' => (int)$gi->id,
                'name' => $istotal ? get_string('gb_total', 'theme_lmslabs') : format_string($gi->get_name()),
                'max' => $gi->gradetype == GRADE_TYPE_VALUE ? (float)$gi->grademax : null,
                'maxlabel' => $gi->gradetype == GRADE_TYPE_VALUE ? format_float($gi->grademax, 0, true, true) : '',
                'url' => $url,
                'istotal' => $istotal,
                'editable' => $editable,
                'icon' => icons::svg($icon),
            ];
        }

        // Learners: those with a graded role in the course.
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = get_enrolled_users($ctx, '', 0, "u.id, $fields, u.email", 'u.lastname, u.firstname', 0, self::LIMIT, true);
        $gradable = \grade_report::get_gradable_users($course->id);
        $users = array_intersect_key($users, $gradable);

        $grades = [];
        if ($users && $items) {
            [$uin, $up] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'u');
            [$iin, $ip] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'i');
            foreach ($DB->get_recordset_select('grade_grades', "userid $uin AND itemid $iin", $up + $ip) as $g) {
                $grades[$g->userid][$g->itemid] = $g;
            }
        }

        $coursectx = \context_course::instance($course->id);
        $rows = [];
        $sums = [];
        $counts = [];
        foreach ($users as $u) {
            $cells = [];
            $needs = 0;
            foreach ($items as $gi) {
                $g = $grades[$u->id][$gi->id] ?? null;
                $final = $g && $g->finalgrade !== null ? (float)$g->finalgrade : null;
                $istotal = $gi->itemtype === 'course';
                $pct = null;
                if ($final !== null && $gi->gradetype == GRADE_TYPE_VALUE && $gi->grademax > $gi->grademin) {
                    $pct = (int)round(($final - $gi->grademin) / ($gi->grademax - $gi->grademin) * 100);
                    $sums[$gi->id] = ($sums[$gi->id] ?? 0) + $pct;
                    $counts[$gi->id] = ($counts[$gi->id] ?? 0) + 1;
                }
                $passpct = ($gi->gradepass > 0 && $gi->grademax > $gi->grademin)
                    ? (int)ceil(($gi->gradepass - $gi->grademin) / ($gi->grademax - $gi->grademin) * 100) : 50;
                $submittedwaiting = false;
                $markurl = '';
                if ($final === null && $gi->itemmodule === 'assign' && !$istotal) {
                    $submittedwaiting = $DB->record_exists(
                        'assign_submission',
                        ['assignment' => $gi->iteminstance, 'userid' => $u->id, 'status' => 'submitted', 'latest' => 1]
                    );
                    $needs += $submittedwaiting ? 1 : 0;
                    if ($submittedwaiting) {
                        $cmid = get_coursemodule_from_instance('assign', $gi->iteminstance, $course->id);
                        $fast = grading::fastgrade('assign') && has_capability('local/fastgrade:grade', $coursectx);
                        $markurl = $cmid ? (new moodle_url(
                            $fast ? '/local/fastgrade/grade.php' : '/mod/assign/view.php',
                            $fast ? ['id' => $cmid->id, 'userid' => $u->id] :
                            ['id' => $cmid->id, 'action' => 'grader', 'userid' => $u->id]
                        ))->out(false) : '';
                    }
                }
                if ($final === null && $gi->itemmodule === 'quiz' && !$istotal) {
                    $needs_q = $DB->record_exists_sql(
                        "SELECT 1
                           FROM {quiz_attempts} qza
                           JOIN {question_attempts} qatt ON qatt.questionusageid = qza.uniqueid
                           JOIN {question_attempt_steps} qs ON qs.questionattemptid = qatt.id
                          WHERE qza.quiz = :quiz AND qza.userid = :uid AND qza.state = 'finished' AND qs.state = 'needsgrading'
                            AND qs.sequencenumber = (SELECT MAX(x.sequencenumber) FROM {question_attempt_steps} x
                                                      WHERE x.questionattemptid = qatt.id)",
                        ['quiz' => $gi->iteminstance, 'uid' => $u->id]);
                    if ($needs_q) {
                        $submittedwaiting = true;
                        $needs++;
                        $qcm = get_coursemodule_from_instance('quiz', $gi->iteminstance, $course->id);
                        // Quiz essays: AI Essay Grader first, then Fast Grade, then Moodle's manual grading page.
                        if ($qcm && grading::aigrader()) {
                            $markurl = (new moodle_url(
                                '/mod/quiz/report.php',
                                ['id' => $qcm->id, 'mode' => 'aigrader', 'userid' => $u->id]
                            ))->out(false);
                        } else if ($qcm && grading::fastgrade('quiz') && has_capability('local/fastgrade:grade', $coursectx)) {
                            $markurl = (new moodle_url('/local/fastgrade/grade.php', ['id' => $qcm->id, 'userid' => $u->id]))->out(false);
                        } else {
                            $markurl = $qcm ? (new moodle_url('/mod/quiz/report.php', ['id' => $qcm->id, 'mode' => 'grading']))->out(false) : '';
                        }
                    }
                }
                $cells[] = [
                    'itemid' => (int)$gi->id,
                    'value' => $final !== null ? format_float($final, $gi->get_decimals(), true, true) : '',
                    'display' => $final !== null ? grade_format_gradevalue($final, $gi, true) : '',
                    'has' => $final !== null,
                    'pct' => $pct,
                    'band' => $pct === null ? 'none' : ($pct >= $passpct ? 'pass' : 'fail'),
                    'passpct' => $passpct,
                    'overridden' => $g && !empty($g->overridden),
                    'waiting' => $submittedwaiting,
                    'markurl' => $markurl,
                    'istotal' => $istotal,
                    'editable' => $canedit && !$istotal && $gi->gradetype == GRADE_TYPE_VALUE && !$gi->is_locked(),
                    'max' => $gi->gradetype == GRADE_TYPE_VALUE ? (float)$gi->grademax : 0,
                    'feedback' => $g && $g->feedback ? trim(html_to_text(format_text($g->feedback, $g->feedbackformat), 140, false)) : '',
                ];
            }
            $rows[] = [
                'id' => (int)$u->id,
                'name' => fullname($u),
                'email' => $u->email,
                'initials' => \core_text::strtoupper(\core_text::substr($u->firstname, 0, 1) . \core_text::substr($u->lastname, 0, 1)),
                'tint' => $u->id % 6,
                'needs' => $needs,
                'cells' => $cells,
                'reporturl' => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id, 'userid' => $u->id]))->out(false),
            ];
        }

        $avgs = [];
        foreach ($items as $gi) {
            $avgs[] = [
                'itemid' => (int)$gi->id,
                'avg' => !empty($counts[$gi->id]) ? (int)round($sums[$gi->id] / $counts[$gi->id]) : null,
                'n' => $counts[$gi->id] ?? 0,
            ];
        }
        $waiting = array_sum(array_column($rows, 'needs'));

        return [
            'courseid' => (int)$course->id,
            'cols' => $cols,
            'hascols' => (bool)$cols,
            'rows' => $rows,
            'hasrows' => (bool)$rows,
            'avgs' => $avgs,
            'waiting' => $waiting,
            'canedit' => $canedit,
            'scheme' => get_user_preferences('theme_lmslabs_gb_scheme', 'snys'),
            'passcolour' => self::colour('pass', '#24c45f'),
            'failcolour' => self::colour('fail', '#c8007f'),
            'learners' => count($rows),
            'classicurl' => router::classic('/grade/report/grader/index.php', ['id' => $course->id]),
            'setupurl' => (new moodle_url('/grade/edit/tree/index.php', ['id' => $course->id]))->out(false),
            'gradingurl' => (new moodle_url('/theme/lmslabs/grading.php', ['id' => $course->id]))->out(false),
        ];
    }

    /**
     * A saved cell colour for the current user.
     *
     * @param string $which pass or fail
     * @param string $default
     * @return string
     */
    protected static function colour(string $which, string $default): string {
        $v = get_user_preferences('theme_lmslabs_gb_' . $which, $default);
        return preg_match('/^#[0-9a-f]{6}$/i', (string)$v) ? $v : $default;
    }

    /**
     * Save one mark (or clear it).
     *
     * @param \stdClass $course
     * @param int $itemid
     * @param int $userid
     * @param string $value empty clears the override
     * @return array the cell as shown after saving
     */
    public static function save(\stdClass $course, int $itemid, int $userid, string $value): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/report/lib.php');
        $ctx = context_course::instance($course->id);
        require_capability('moodle/grade:edit', $ctx);
        $gi = \grade_item::fetch(['id' => $itemid, 'courseid' => $course->id]);
        if (!$gi || $gi->itemtype === 'course' || $gi->gradetype != GRADE_TYPE_VALUE) {
            throw new \invalid_parameter_exception('Item cannot be edited');
        }
        if ($gi->is_locked()) {
            throw new \moodle_exception('gb_locked', 'theme_lmslabs');
        }
        if (!array_key_exists($userid, \grade_report::get_gradable_users($course->id))) {
            throw new \invalid_parameter_exception('Not a learner in this course');
        }
        $value = trim(str_replace(',', '.', $value));
        if ($value === '') {
            $final = null;
        } else {
            if (!is_numeric($value)) {
                throw new \moodle_exception('gb_notnumber', 'theme_lmslabs');
            }
            $final = (float)$value;
            if ($final < $gi->grademin || $final > $gi->grademax) {
                throw new \moodle_exception(
                    'gb_range', 'theme_lmslabs', '',
                    format_float($gi->grademin, 0, true, true) . '–' . format_float($gi->grademax, 0, true, true)
                );
            }
        }
        if (!$gi->update_final_grade($userid, $final, 'gradebook')) {
            throw new \moodle_exception('gb_failed', 'theme_lmslabs');
        }
        $g = \grade_grade::fetch(['itemid' => $itemid, 'userid' => $userid]);
        $now = $g && $g->finalgrade !== null ? (float)$g->finalgrade : null;
        $pct = $now !== null && $gi->grademax > $gi->grademin
            ? (int)round(($now - $gi->grademin) / ($gi->grademax - $gi->grademin) * 100) : null;
        // The course total moves with it.
        $out = ['pct' => $pct, 'value' => $now !== null ? format_float($now, $gi->get_decimals(), true, true) : '',
            'total' => null];
        $ci = \grade_item::fetch_course_item($course->id);
        \grade_regrade_final_grades($course->id, $userid, $gi);
        $tg = \grade_grade::fetch(['itemid' => $ci->id, 'userid' => $userid]);
        if ($tg && $tg->finalgrade !== null) {
            $out['total'] = ['itemid' => (int)$ci->id, 'display' => grade_format_gradevalue((float)$tg->finalgrade, $ci, true),
                'pct' => $ci->grademax > $ci->grademin
                    ? (int)round(((float)$tg->finalgrade - $ci->grademin) / ($ci->grademax - $ci->grademin) * 100) : null];
        }
        return $out;
    }
}
