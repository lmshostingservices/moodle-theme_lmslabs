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

use cm_info;
use context;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Question bank data: the banks in a course, their categories and questions.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbank {
    /** @var string[] capabilities that let someone see a bank */
    const VIEW = ['moodle/question:viewall', 'moodle/question:useall', 'moodle/question:editall', 'moodle/question:viewmine',
        'moodle/question:usemine', 'moodle/question:editmine'];

    /**
     * Where questions live in a course: the course itself (Moodle 4.5) and bank or quiz activities (Moodle 5).
     *
     * @param \stdClass $course
     * @return array [['ctx' => context, 'name' => string, 'cmid' => int, 'kind' => string]]
     */
    public static function banks(\stdClass $course): array {
        global $DB;
        $out = [];
        $cctx = \context_course::instance($course->id);
        $has = $DB->record_exists_sql("SELECT 1 FROM {question_categories} WHERE contextid = ? AND parent <> 0", [$cctx->id]);
        if ($has && has_any_capability(self::VIEW, $cctx)) {
            $out[] = ['ctx' => $cctx, 'name' => get_string('qb_coursebank', 'theme_lmslabs'), 'cmid' => 0, 'kind' => 'course'];
        }
        $modinfo = get_fast_modinfo($course);
        foreach (['qbank', 'quiz'] as $mod) {
            foreach ($modinfo->get_instances_of($mod) as $cm) {
                if (!$cm->uservisible && $mod === 'qbank' && !has_capability('moodle/course:update', $cctx)) {
                    continue;
                }
                if (!has_any_capability(self::VIEW, $cm->context)) {
                    continue;
                }
                $out[] = ['ctx' => $cm->context, 'name' => format_string($cm->name), 'cmid' => (int)$cm->id,
                    'kind' => $mod === 'quiz' ? 'quiz' : 'bank'];
            }
        }
        return $out;
    }

    /**
     * Categories of a bank with how many questions each holds.
     *
     * @param context $ctx
     * @return array flat list with a depth
     */
    public static function categories(context $ctx): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT qc.id, qc.name, qc.parent, qc.sortorder,
                       (SELECT COUNT(1) FROM {question_bank_entries} e WHERE e.questioncategoryid = qc.id) AS n
                  FROM {question_categories} qc WHERE qc.contextid = :c ORDER BY qc.sortorder, qc.name", ['c' => $ctx->id]
        );
        $byparent = [];
        foreach ($rows as $r) {
            $byparent[(int)$r->parent][] = $r;
        }
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byparent) {
            foreach ($byparent[$parent] ?? [] as $r) {
                if ((int)$r->parent === 0) {
                    $walk((int)$r->id, 0);
                    continue;
                }
                $out[] = ['id' => (int)$r->id, 'name' => format_string($r->name), 'n' => (int)$r->n, 'depth' => $depth,
                    'indent' => $depth * 14];
                $walk((int)$r->id, $depth + 1);
            }
        };
        foreach ($byparent[0] ?? [] as $top) {
            $walk((int)$top->id, 0);
        }
        return $out;
    }

    /**
     * Questions in a bank (latest version of each), newest first.
     *
     * @param context $ctx
     * @param int $limit
     * @return array
     */
    public static function questions(context $ctx, int $limit = 600): array {
        global $DB;
        $rs = $DB->get_recordset_sql(
            "SELECT q.id, q.name, q.qtype, q.timemodified, e.id AS entryid, e.questioncategoryid AS cat,
                       v.status,
                       (SELECT COUNT(1) FROM {question_references} r WHERE r.questionbankentryid = e.id) AS used
                  FROM {question_bank_entries} e
                  JOIN {question_categories} qc ON qc.id = e.questioncategoryid AND qc.contextid = :c
                  JOIN {question_versions} v ON v.questionbankentryid = e.id
                  JOIN {question} q ON q.id = v.questionid
                 WHERE v.version = (SELECT MAX(v2.version) FROM {question_versions} v2
                                     WHERE v2.questionbankentryid = e.id AND v2.status <> :hidden)
                       AND v.status <> :hidden2
              ORDER BY q.timemodified DESC", ['c' => $ctx->id, 'hidden' => 'hidden', 'hidden2' => 'hidden'], 0, $limit
        );
        $out = [];
        foreach ($rs as $q) {
            $label = get_string_manager()->string_exists('pluginname', 'qtype_' . $q->qtype)
                ? get_string('pluginname', 'qtype_' . $q->qtype) : $q->qtype;
            $out[] = ['id' => (int)$q->id, 'name' => format_string($q->name), 'type' => $q->qtype, 'typelabel' => $label,
                'cat' => (int)$q->cat, 'when' => userdate($q->timemodified, get_string('strftimedatefullshort', 'langconfig')),
                'draft' => $q->status === 'draft', 'used' => (int)$q->used];
        }
        $rs->close();
        return $out;
    }

    /**
     * The parameter that tells Moodle's question screens which bank this is.
     *
     * @param array $bank
     * @param \stdClass $course
     * @return array
     */
    public static function target(array $bank, \stdClass $course): array {
        return $bank['cmid'] ? ['cmid' => $bank['cmid']] : ['courseid' => $course->id];
    }
}
