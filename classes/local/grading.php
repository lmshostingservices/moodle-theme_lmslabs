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

use context_module;
use moodle_url;

/**
 * Course "Grading" inbox: everything waiting to be marked, with one click into Fast Grade
 * (assignments) or AI Essay Grader (quiz essays) when those LMS Labs plugins are installed.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading {
    /**
     * Is Fast Grade installed and switched on for this activity type?
     *
     * @param string $modname
     * @return bool
     */
    public static function fastgrade(string $modname): bool {
        return class_exists('\\local_fastgrade\\local\\source') && \local_fastgrade\local\source::is_enabled_for($modname);
    }

    /**
     * Is AI Essay Grader installed?
     *
     * @return bool
     */
    public static function aigrader(): bool {
        return (bool)\core_component::get_component_directory('quiz_aigrader');
    }

    /**
     * May this user see the Grading page for the course?
     *
     * @param \context_course $context
     * @return bool
     */
    public static function applies(\context_course $context): bool {
        return has_any_capability(['mod/assign:grade', 'mod/quiz:grade'], $context);
    }

    /**
     * How many submissions are waiting to be marked in a course.
     *
     * @param int $courseid
     * @return int
     */
    public static function count_waiting(int $courseid): int {
        return (int)(self::export(get_course($courseid))['waiting'] ?? 0);
    }

    /**
     * Rows for the page.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function export(\stdClass $course): array {
        global $DB;
        $modinfo = get_fast_modinfo($course);
        $rows = [];
        $waiting = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!in_array($cm->modname, ['assign', 'quiz']) || $cm->deletioninprogress || !$cm->uservisible) {
                continue;
            }
            $ctx = context_module::instance($cm->id);
            if (!has_capability('mod/' . $cm->modname . ':grade', $ctx)) {
                continue;
            }
            if ($cm->modname === 'assign') {
                [$submitted, $todo] = self::assign_counts((int)$cm->instance);
                $tool = self::fastgrade('assign') && has_capability('local/fastgrade:grade', $ctx) ? 'fastgrade' : null;
                $primary = $tool ? new moodle_url('/local/fastgrade/grade.php', ['id' => $cm->id]) :
                    new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grader']);
                $secondary = null;
            } else {
                [$submitted, $todo, $essays] = self::quiz_counts((int)$cm->instance);
                if (!$essays) {
                    continue;
                }
                $tool = self::aigrader() ? 'aigrader' : null;
                $primary = $tool ? new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'aigrader']) :
                    new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'grading']);
                $secondary = null;
            }
            $waiting += $todo;
            $rows[] = [
                'name' => $cm->get_formatted_name(),
                'type' => get_string($cm->modname === 'assign' ? 'gr_assignment' : 'gr_essayquiz', 'theme_lmslabs'),
                'icon' => $cm->get_icon_url()->out(false),
                'section' => get_section_name($course, $cm->sectionnum),
                'submitted' => $submitted,
                'todo' => $todo,
                'hastodo' => $todo > 0,
                'done' => $todo === 0 && $submitted > 0,
                'viewurl' => $cm->url ? $cm->url->out(false) : null,
                'primaryurl' => $primary->out(false),
                'primarylabel' => get_string($tool ? 'gr_open_' . $tool : 'gr_open_' . $cm->modname, 'theme_lmslabs'),
                'isfastgrade' => $tool === 'fastgrade',
                'isaigrader' => $tool === 'aigrader',
                'secondaryurl' => $secondary ? $secondary->out(false) : null,
                'reporturl' => $tool === 'fastgrade' && has_capability('local/fastgrade:viewreport', $ctx) ?
                    (new moodle_url('/local/fastgrade/report.php', ['id' => $cm->id]))->out(false) : null,
            ];
        }
        usort(
            $rows, function ($a, $b) {
            return $b['todo'] <=> $a['todo'];
        }
        );
        return [
            'rows' => $rows,
            'hasrows' => (bool)$rows,
            'waiting' => $waiting,
            'activities' => count($rows),
            'hasfastgrade' => self::fastgrade('assign'),
            'hasaigrader' => self::aigrader(),
        ];
    }

    /**
     * Submitted and waiting-for-marking counts for an assignment.
     *
     * @param int $assignid
     * @return int[]
     */
    protected static function assign_counts(int $assignid): array {
        global $DB;
        $submitted = $DB->count_records_select(
            'assign_submission',
            "assignment = ? AND latest = 1 AND status = 'submitted' AND userid > 0", [$assignid]
        );
        $todo = (int)$DB->count_records_sql(
            "SELECT COUNT(s.id)
               FROM {assign_submission} s
          LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                     AND g.attemptnumber = s.attemptnumber
              WHERE s.assignment = ? AND s.latest = 1 AND s.status = 'submitted' AND s.userid > 0
                AND (g.id IS NULL OR g.grade IS NULL OR g.grade < 0 OR s.timemodified >= g.timemodified)", [$assignid]);
        return [$submitted, $todo];
    }

    /**
     * Finished attempts, essays waiting for a mark, and total essay responses for a quiz.
     *
     * @param int $quizid
     * @return int[]
     */
    protected static function quiz_counts(int $quizid): array {
        global $DB;
        $submitted = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'state' => 'finished', 'preview' => 0]);
        $sql = "SELECT COUNT(qa.id) AS essays,
                       SUM(CASE WHEN qas.state = 'needsgrading' THEN 1 ELSE 0 END) AS todo
                  FROM {quiz_attempts} quiza
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                  JOIN {question} q ON q.id = qa.questionid AND q.qtype = 'essay'
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                   AND qas.sequencenumber = (SELECT MAX(s2.sequencenumber) FROM {question_attempt_steps} s2
                                              WHERE s2.questionattemptid = qa.id)
                 WHERE quiza.quiz = ? AND quiza.state = 'finished' AND quiza.preview = 0";
        $r = $DB->get_record_sql($sql, [$quizid]);
        $essays = (int)($r->essays ?? 0);
        if (!$essays) {
            // A quiz with essay questions but no attempts yet still belongs on the list.
            $essays = (int)$DB->record_exists_sql(
                "SELECT 1 FROM {quiz_slots} qs
                  JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                  JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
                  JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'essay'
                 WHERE qs.quizid = ?", [$quizid]
            );
        }
        return [$submitted, (int)($r->todo ?? 0), $essays];
    }
}
