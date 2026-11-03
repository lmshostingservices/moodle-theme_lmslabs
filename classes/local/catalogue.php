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
use context_coursecat;
use core_course_category;
use moodle_url;

/**
 * Data for the Courses and categories page.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {
    /** Tints shared with the rest of the theme (see .ll-tint-N). */
    const TINTS = 6;

    /**
     * Everything the page needs.
     *
     * @return array
     */
    public static function export(): array {
        global $DB;
        $cats = core_course_category::make_categories_list('moodle/category:viewcourselist');
        $courses = $DB->get_records_select(
            'course', 'id <> :site', ['site' => SITEID], 'category, sortorder',
            'id, category, fullname, shortname, visible, sortorder, summary, summaryformat, startdate'
        );
        $learners = self::learner_counts(array_keys($courses));
        $teachers = self::teachers(array_keys($courses));
        $inprograms = self::programs();
        $canprograms = has_capability('theme/lmslabs:manageprograms', \context_system::instance());

        $bycat = [];
        foreach ($courses as $c) {
            $bycat[$c->category][] = $c;
        }
        $sections = [];
        $total = $visible = $hidden = 0;
        $default = 0;
        foreach ($cats as $catid => $path) {
            $cat = core_course_category::get($catid, IGNORE_MISSING);
            if (!$cat) {
                continue;
            }
            $catctx = context_coursecat::instance($catid);
            if (!$default && has_capability('moodle/course:create', $catctx)) {
                $default = $catid;
            }
            $canorder = has_capability('moodle/category:manage', $catctx);
            $cards = [];
            foreach ($bycat[$catid] ?? [] as $i => $c) {
                $cctx = context_course::instance($c->id);
                $n = (int)($learners[$c->id] ?? 0);
                $cards[] = [
                    'id' => $c->id,
                    'name' => format_string($c->fullname, true, ['context' => $cctx]),
                    'code' => format_string($c->shortname, true, ['context' => $cctx]),
                    'initials' => \core_text::strtoupper(\core_text::substr(preg_replace('/[^\pL\pN]/u', '', $c->shortname), 0, 2)),
                    'tint' => $c->id % self::TINTS,
                    'image' => self::image($c),
                    'visible' => (bool)$c->visible,
                    'learners' => $n,
                    'nolearners' => $n === 0,
                    'learnerslabel' => get_string($n === 1 ? 'cm_learner' : 'cm_learners', 'theme_lmslabs', $n),
                    'teachers' => $teachers[$c->id] ?? [],
                    'viewurl' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                    'editurl' => has_capability('moodle/course:update', $cctx) ?
                        (new moodle_url('/course/edit.php', ['id' => $c->id]))->out(false) : null,
                    'canvisibility' => has_capability('moodle/course:visibility', $cctx),
                    'canorder' => $canorder,
                    'search' => \core_text::strtolower($c->fullname . ' ' . $c->shortname),
                    'programs' => $inprograms[$c->id] ?? [],
                    'hasprograms' => !empty($inprograms[$c->id]),
                ];
                $total++;
                $c->visible ? $visible++ : $hidden++;
            }
            $depth = substr_count($cat->path, '/') - 1;
            $parts = explode(' / ', $path);
            array_pop($parts);
            $children = $cat->get_children_count();
            $sections[] = [
                'id' => $catid,
                'name' => format_string($cat->name, true, ['context' => $catctx]),
                'parent' => implode(' / ', $parts),
                'hasparent' => (bool)$parts,
                'depth' => min($depth, 3),
                'visible' => (bool)$cat->visible,
                'count' => count($cards),
                'courses' => $cards,
                'hascourses' => (bool)$cards,
                'meta' => $children ? get_string($children === 1 ? 'cm_subcat' : 'cm_subcats', 'theme_lmslabs', $children) : null,
                'tint' => $catid % self::TINTS,
                'addurl' => has_capability('moodle/course:create', $catctx) ?
                    (new moodle_url('/course/edit.php', ['category' => $catid]))->out(false) : null,
                'editurl' => $canorder ? (new moodle_url('/course/editcategory.php', ['id' => $catid]))->out(false) : null,
                'subcaturl' => $canorder ? (new moodle_url('/course/editcategory.php', ['parent' => $catid]))->out(false) : null,
                'canorder' => $canorder,
                'addlabel' => get_string('cm_addto', 'theme_lmslabs', $cat->get_formatted_name()),
                'programurl' => $canprograms && $cards ? (new moodle_url(
                    '/theme/lmslabs/program_from_category.php',
                    ['category' => $catid, 'sesskey' => sesskey()]
                ))->out(false) : null,
            ];
        }
        return [
            'sections' => $sections,
            'hassections' => (bool)$sections,
            'stats' => [
                ['value' => count($sections), 'label' => get_string('categories')],
                ['value' => $total, 'label' => get_string('courses')],
                ['value' => $visible, 'label' => get_string('cm_visiblelearners', 'theme_lmslabs'), 'dot' => 'on'],
                ['value' => $hidden, 'label' => get_string('cm_hidden', 'theme_lmslabs'), 'dot' => 'off'],
            ],
            'counts' => ['all' => $total, 'visible' => $visible, 'hidden' => $hidden],
            'defaultcategory' => $default ?: (int)(core_course_category::get_default()->id ?? 1),
        ];
    }

    /**
     * Which programs each course is in, with its step and any unlock rule, for the card badges.
     *
     * @return array courseid => [['name', 'url', 'rule']]
     */
    protected static function programs(): array {
        global $DB;
        $sql = "SELECT pc.id, pc.courseid, pc.sortorder, pc.delay, pc.requireprev, pc.hidden, p.id AS programid, p.name
                  FROM {theme_lmslabs_pcourse} pc
                  JOIN {theme_lmslabs_program} p ON p.id = pc.programid
              ORDER BY p.sortorder, pc.sortorder";
        $out = [];
        $steps = [];
        foreach ($DB->get_records_sql($sql) as $r) {
            $steps[$r->programid] = ($steps[$r->programid] ?? 0) + ($r->hidden ? 0 : 1);
            $rule = null;
            if ($r->hidden) {
                $rule = get_string('cm_rulehidden', 'theme_lmslabs');
            } else if ($r->requireprev == programs::REQUIRE_COMPLETED) {
                $rule = get_string('cm_rulecompleted', 'theme_lmslabs');
            } else if ($r->requireprev == programs::REQUIRE_SUBMITTED) {
                $rule = get_string('cm_rulesubmitted', 'theme_lmslabs');
            } else if ($r->delay) {
                $rule = get_string('cm_ruledelay', 'theme_lmslabs', format_time((int)$r->delay));
            }
            $out[$r->courseid][] = [
                'name' => format_string($r->name),
                'step' => $steps[$r->programid],
                'url' => (new moodle_url('/theme/lmslabs/program.php', ['id' => $r->programid]))->out(false),
                'rule' => $rule,
                'hasrule' => (bool)$rule,
            ];
        }
        return $out;
    }

    /**
     * Active learners (student archetype) per course.
     *
     * @param int[] $ids
     * @return array courseid => count
     */
    protected static function learner_counts(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $sql = "SELECT ctx.instanceid AS courseid, COUNT(DISTINCT ra.userid) AS n
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
                 WHERE ctx.instanceid $in
              GROUP BY ctx.instanceid";
        return $DB->get_records_sql_menu($sql, $params + ['lvl' => CONTEXT_COURSE]);
    }

    /**
     * Up to three teachers per course, as initials for the avatar stack.
     *
     * @param int[] $ids
     * @return array courseid => [['initials', 'name', 'tint']]
     */
    protected static function teachers(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $names = implode(
            ', ', array_map(function ($f) {
            return 'u.' . $f;
        }, \core_user\fields::get_name_fields())
        );
        $sql = "SELECT ra.id, ctx.instanceid AS courseid, u.id AS userid, $names
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid AND r.archetype IN ('editingteacher', 'teacher')
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE ctx.instanceid $in
              ORDER BY ctx.instanceid, u.lastname";
        $out = [];
        foreach ($DB->get_records_sql($sql, $params + ['lvl' => CONTEXT_COURSE]) as $r) {
            if (count($out[$r->courseid] ?? []) >= 3 || isset($out[$r->courseid][$r->userid])) {
                continue;
            }
            $out[$r->courseid][$r->userid] = [
                'initials' => \core_text::strtoupper(\core_text::substr($r->firstname, 0, 1) . \core_text::substr($r->lastname, 0, 1)),
                'name' => fullname($r),
                'tint' => $r->userid % self::TINTS,
            ];
        }
        return array_map('array_values', $out);
    }

    /**
     * Course image URL, if the course has one.
     *
     * @param \stdClass $course
     * @return string|null
     */
    protected static function image(\stdClass $course): ?string {
        return courseimage::url($course);
    }
}
