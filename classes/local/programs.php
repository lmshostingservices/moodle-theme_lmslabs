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
use stdClass;

/**
 * Programs on My courses: each program has an audience, headings, an order of courses and unlock rules.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class programs {
    /** No prerequisite. */
    const REQUIRE_NONE = 0;
    /** Every assessment in the previous course submitted (feedback may still be pending). */
    const REQUIRE_SUBMITTED = 1;
    /** Previous course completed. */
    const REQUIRE_COMPLETED = 2;

    /** @var array|null request cache: all programs */
    protected static $programs = null;
    /** @var array request cache: program id => ordered pcourse rows */
    protected static $rows = [];
    /** @var array request cache: user id => matched program ids */
    protected static $matched = [];

    /**
     * Forget request caches (after a save).
     */
    public static function reset_caches(): void {
        self::$programs = null;
        self::$rows = [];
        self::$matched = [];
    }

    /**
     * All programs in order.
     *
     * @return array
     */
    public static function all(): array {
        global $DB;
        if (self::$programs === null) {
            self::$programs = $DB->get_records('theme_lmslabs_program', null, 'sortorder ASC, id ASC');
        }
        return self::$programs;
    }

    /**
     * A program's course rows in order, keyed by course id.
     *
     * @param int $programid
     * @return array
     */
    public static function rows(int $programid): array {
        global $DB;
        if (!isset(self::$rows[$programid])) {
            $rows = $DB->get_records('theme_lmslabs_pcourse', ['programid' => $programid], 'sortorder ASC', '*');
            $bycourse = [];
            foreach ($rows as $r) {
                $bycourse[$r->courseid] = $r;
            }
            self::$rows[$programid] = $bycourse;
        }
        return self::$rows[$programid];
    }

    /**
     * Program ids this user belongs to, in program order.
     *
     * @param int $userid
     * @return int[]
     */
    public static function matched(int $userid): array {
        if (!isset(self::$matched[$userid])) {
            $ids = [];
            foreach (self::all() as $p) {
                if (audience::matches(audience::decode($p->audience), $userid)) {
                    $ids[] = (int)$p->id;
                }
            }
            self::$matched[$userid] = $ids;
        }
        return self::$matched[$userid];
    }

    /**
     * When did this user's enrolment in the course start?
     *
     * @param int $courseid
     * @param int $userid
     * @return int|null
     */
    public static function enrol_start(int $courseid, int $userid): ?int {
        global $DB;
        $sql = "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.userid = :userid AND ue.status = 0 AND e.status = 0";
        $start = $DB->get_field_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        return $start ? (int)$start : null;
    }

    /**
     * Has the user submitted every assessment in the course? Waiting for feedback still counts.
     *
     * @param stdClass $course
     * @param int $userid
     * @return bool
     */
    public static function all_submitted(stdClass $course, int $userid): bool {
        global $DB;
        $modinfo = get_fast_modinfo($course, $userid);
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }
            if ($cm->modname === 'assign') {
                $ok = $DB->record_exists_select(
                    'assign_submission',
                    "assignment = :a AND userid = :u AND latest = 1 AND status = 'submitted'",
                    ['a' => $cm->instance, 'u' => $userid]
                ) ||
                    $DB->record_exists_select(
                        'assign_grades', 'assignment = :a AND userid = :u AND grade >= 0',
                    ['a' => $cm->instance, 'u' => $userid]
                    );
                if (!$ok) {
                    return false;
                }
            } else if ($cm->modname === 'quiz') {
                if (!$DB->record_exists('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $userid, 'state' => 'finished'])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Course progress for a user, or null when completion tracking is off.
     *
     * @param stdClass $course
     * @param int $userid
     * @return int|null
     */
    public static function progress(stdClass $course, int $userid): ?int {
        $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        return $percent === null ? null : (int)round($percent);
    }

    /**
     * Is the course complete for the user?
     *
     * @param stdClass $course
     * @param int $userid
     * @return bool
     */
    public static function completed(stdClass $course, int $userid): bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $info = new \completion_info($course);
        return $info->is_enabled() && $info->is_course_complete($userid);
    }

    /**
     * Can this user skip the rules for a course (teachers, managers)?
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function bypass(int $courseid, int $userid): bool {
        global $USER;
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return true;
        }
        // A trainer previewing in Student view keeps access, so they can still click Return to my view.
        if ($userid == $USER->id && is_role_switched($courseid)) {
            return true;
        }
        return has_any_capability(['moodle/course:update', 'moodle/course:viewhiddencourses'], $context, $userid);
    }

    /**
     * The user's visible enrolled courses, keyed by id.
     *
     * @param int $userid
     * @return array
     */
    public static function enrolled(int $userid): array {
        $courses = enrol_get_all_users_courses($userid, true, 'summary, summaryformat, enablecompletion, startdate');
        $out = [];
        foreach ($courses as $c) {
            $out[$c->id] = $c;
        }
        return $out;
    }

    /**
     * Why is a course locked for the user? Null when it is open.
     * The first program the learner belongs to that contains the course sets the rules.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array|null ['reason' => string, 'until' => int|null]
     */
    public static function lock(stdClass $course, int $userid): ?array {
        global $DB;
        if (!$DB->record_exists('theme_lmslabs_pcourse', ['courseid' => $course->id]) || self::bypass($course->id, $userid)) {
            return null;
        }
        foreach (self::matched($userid) as $pid) {
            $rows = self::rows($pid);
            if (!isset($rows[$course->id])) {
                continue;
            }
            return self::lock_in_program($course, $userid, $rows, self::enrolled($userid));
        }
        return null;
    }

    /**
     * Lock check within one program.
     *
     * @param stdClass $course
     * @param int $userid
     * @param array $rows the program's rows keyed by course id, in order
     * @param array $enrolled the user's courses keyed by id
     * @return array|null
     */
    protected static function lock_in_program(stdClass $course, int $userid, array $rows, array $enrolled): ?array {
        $rule = $rows[$course->id];
        if ($rule->delay) {
            $start = self::enrol_start($course->id, $userid);
            $opens = $start ? $start + (int)$rule->delay : null;
            if ($opens && $opens > time()) {
                return [
                    'reason' => get_string('locked_until', 'theme_lmslabs', userdate($opens, get_string('strftimedatetimeshort'))),
                    'until' => $opens,
                ];
            }
        }
        if ($rule->requireprev) {
            $previous = null;
            foreach ($rows as $cid => $r) {
                if ($cid == $course->id) {
                    break;
                }
                if (isset($enrolled[$cid]) && !$r->hidden) {
                    $previous = $enrolled[$cid];
                }
            }
            if ($previous) {
                $name = format_string($previous->fullname, true, ['context' => context_course::instance($previous->id)]);
                if (!self::completed($previous, $userid)) {
                    // A "completed" rule on a course without completion tracking could never be met, so it
                    // falls back to "everything submitted" rather than locking the learner out for good.
                    global $CFG;
                    require_once($CFG->libdir . '/completionlib.php');
                    $tracked = (new \completion_info($previous))->is_enabled();
                    if ($rule->requireprev == self::REQUIRE_COMPLETED && $tracked) {
                        return ['reason' => get_string('locked_complete', 'theme_lmslabs', $name), 'until' => null];
                    }
                    if (!self::all_submitted($previous, $userid)) {
                        return ['reason' => get_string('locked_submit', 'theme_lmslabs', $name), 'until' => null];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Course image URL from the course overview files, or null.
     *
     * @param stdClass $course
     * @return string|null
     */
    public static function image(stdClass $course): ?string {
        return courseimage::url($course);
    }

    /**
     * Card data for one course.
     *
     * @param stdClass $course
     * @param int $userid
     * @param int $step
     * @param array|null $lock
     * @return array
     */
    protected static function card(stdClass $course, int $userid, int $step, ?array $lock): array {
        $context = context_course::instance($course->id);
        $progress = self::progress($course, $userid);
        $complete = self::completed($course, $userid);
        $percent = $complete ? 100 : ($progress ?? 0);
        $status = $lock ? 'locked' : ($complete ? 'complete' : ($progress ? 'inprogress' : 'notstarted'));
        $canimage = $GLOBALS['PAGE']->user_is_editing() && has_capability('moodle/course:update', $context);
        return [
            'canimage' => $canimage,
            'id' => $course->id,
            'step' => $step,
            'hasstep' => $step > 0,
            'fullname' => format_string($course->fullname, true, ['context' => $context]),
            'shortname' => format_string($course->shortname, true, ['context' => $context]),
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => self::image($course),
            'tint' => $course->id % 6,
            'initial' => \core_text::strtoupper(\core_text::substr(trim($course->shortname), 0, 2)),
            'percent' => $percent,
            'dash' => round(150.8 * (1 - $percent / 100), 2),
            'locked' => (bool)$lock,
            'lockreason' => $lock['reason'] ?? '',
            'complete' => $status === 'complete',
            'inprogress' => $status === 'inprogress',
            'notstarted' => $status === 'notstarted',
            'iscomplete' => $complete,
            'status' => $status,
            'category' => self::category_name((int)$course->category),
            'categoryid' => (int)$course->category,
            'lastaccess' => self::last_access((int)$course->id, $userid),
            'lastaccesslabel' => ($la = self::last_access((int)$course->id, $userid)) ?
                get_string('ago', 'core_message', format_time(time() - $la)) : get_string('never'),
            'search' => \core_text::strtolower($course->fullname . ' ' . $course->shortname),
        ];
    }

    /** @var array category names cache */
    protected static $catnames = [];
    /** @var array last access cache [userid][courseid] */
    protected static $access = [];

    /**
     * Category name for a course card.
     *
     * @param int $id
     * @return string
     */
    protected static function category_name(int $id): string {
        if (!array_key_exists($id, self::$catnames)) {
            $cat = \core_course_category::get($id, IGNORE_MISSING, true);
            self::$catnames[$id] = $cat ? $cat->get_formatted_name() : '';
        }
        return self::$catnames[$id];
    }

    /**
     * When the user last opened the course (0 = never).
     *
     * @param int $courseid
     * @param int $userid
     * @return int
     */
    protected static function last_access(int $courseid, int $userid): int {
        global $DB;
        if (!isset(self::$access[$userid])) {
            self::$access[$userid] = $DB->get_records_menu('user_lastaccess', ['userid' => $userid], '', 'courseid, timeaccess');
        }
        return (int)(self::$access[$userid][$courseid] ?? 0);
    }

    /**
     * Template data for a learner's My courses page.
     *
     * @param int $userid
     * @return array
     */
    public static function export_for_user(int $userid): array {
        global $DB;
        $enrolled = self::enrolled($userid);
        $placed = [];
        $programs = [];
        $total = 0;
        $done = 0;
        $all = self::all();

        foreach (self::matched($userid) as $pid) {
            $program = $all[$pid];
            $rows = self::rows($pid);
            $headings = $DB->get_records('theme_lmslabs_pgroup', ['programid' => $pid], 'sortorder ASC, id ASC');
            $sections = [];
            $step = 0;
            foreach ($rows as $cid => $row) {
                if ($row->hidden || !isset($enrolled[$cid])) {
                    continue;
                }
                $course = $enrolled[$cid];
                $lock = self::bypass($cid, $userid) ? null : self::lock_in_program($course, $userid, $rows, $enrolled);
                $card = self::card($course, $userid, ++$step, $lock);
                $gid = isset($headings[$row->groupid]) ? (int)$row->groupid : 0;
                if (!isset($sections[$gid])) {
                    $sections[$gid] = [
                        'name' => $gid ? format_string($headings[$gid]->name) : '',
                        'hasname' => (bool)$gid,
                        'order' => $gid ? (int)$headings[$gid]->sortorder : -1,
                        'courses' => [],
                    ];
                }
                $sections[$gid]['courses'][] = $card;
                if (!isset($placed[$cid])) {
                    $placed[$cid] = true;
                    $total++;
                    $done += $card['iscomplete'] ? 1 : 0;
                }
            }
            if (!$sections) {
                continue;
            }
            usort(
                $sections, function ($a, $b) {
                return $a['order'] <=> $b['order'];
            }
            );
            $count = 0;
            $completed = 0;
            foreach ($sections as $s) {
                foreach ($s['courses'] as $c) {
                    $count++;
                    $completed += $c['iscomplete'] ? 1 : 0;
                }
            }
            $programs[] = [
                'name' => format_string($program->name),
                'description' => format_text((string)$program->description, FORMAT_PLAIN),
                'hasdescription' => trim((string)$program->description) !== '',
                'count' => $count,
                'completed' => $completed,
                'percent' => $count ? (int)round($completed / $count * 100) : 0,
                'sections' => array_values($sections),
            ];
        }

        // Everything else they're enrolled in.
        $others = [];
        $rest = array_diff_key($enrolled, $placed);
        uasort(
            $rest, function ($a, $b) {
            return strcasecmp($a->fullname, $b->fullname);
        }
        );
        foreach ($rest as $course) {
            $card = self::card($course, $userid, 0, null);
            $others[] = $card;
            $total++;
            $done += $card['iscomplete'] ? 1 : 0;
        }
        if ($others) {
            $programs[] = [
                'name' => $programs ? get_string('othercourses', 'theme_lmslabs') : '',
                'isothers' => true,
                'count' => count($others),
                'sections' => [['hasname' => false, 'courses' => $others]],
            ];
        }
        // One flat, de-duplicated list (program order first) for the Cards and List views and the filters.
        $flat = [];
        $order = 0;
        foreach ($programs as $p) {
            foreach ($p['sections'] as $sct) {
                foreach ($sct['courses'] as $c) {
                    if (!isset($flat[$c['id']])) {
                        $flat[$c['id']] = $c + ['order' => $order++];
                    }
                }
            }
        }
        $counts = ['inprogress' => 0, 'notstarted' => 0, 'complete' => 0, 'locked' => 0];
        $cats = [];
        foreach ($flat as $c) {
            $counts[$c['status']]++;
            if ($c['category'] !== '') {
                $cats[$c['categoryid']] = $c['category'];
            }
        }
        asort($cats);
        // Continue where you left off: most recently opened unlocked course that isn't complete.
        $resume = null;
        foreach ($flat as $c) {
            if (!$c['locked'] && !$c['complete'] && $c['lastaccess'] && (!$resume || $c['lastaccess'] > $resume['lastaccess'])) {
                $resume = $c;
            }
        }
        if ($resume) {
            $resume = array_merge($resume, self::resume_activities($resume['id'], $userid, $resume['percent']));
        }
        return [
            'programs' => $programs,
            'hascourses' => $total > 0,
            'total' => $total,
            'done' => $done,
            'all' => array_values($flat),
            'counts' => $counts,
            'overall' => $total ? (int)round(array_sum(array_column(array_values($flat), 'percent')) / count($flat)) : 0,
            'categories' => array_map(function ($id, $name) {
                return ['id' => $id, 'name' => $name];
            }, array_keys($cats), $cats),
            'hascategories' => count($cats) > 1,
            'resume' => $resume,
            'hasprograms' => count($programs) > 1 || ($programs && empty($programs[0]['isothers'])),
        ];
    }

    /**
     * The activities shown as clickable circles on the "continue" card.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $percent Course progress percentage.
     * @return array
     */
    protected static function resume_activities(int $courseid, int $userid, int $percent): array {
        $out = [
            'acts' => [],
            'hasacts' => false,
            'ringdash' => round(150.8 * (1 - max(0, min(100, $percent)) / 100), 1),
            'actsdone' => 0,
            'actstotal' => 0,
            'nexturl' => '',
            'more' => 0,
        ];
        try {
            $course = get_course($courseid);
            $info = new \completion_info($course);
            if (!$info->is_enabled()) {
                return $out;
            }
            $modinfo = get_fast_modinfo($course, $userid);
            $n = 0;
            $next = null;
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || $cm->deletioninprogress || !$cm->url || $info->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                $data = $info->get_data($cm, false, $userid);
                $done = in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                $n++;
                $out['actstotal']++;
                if ($done) {
                    $out['actsdone']++;
                }
                $isnext = !$done && $next === null;
                if ($isnext) {
                    $next = $cm->url->out(false);
                }
                if ($n <= 24) {
                    $out['acts'][] = [
                        'num' => $n,
                        'url' => $cm->url->out(false),
                        'name' => $cm->get_formatted_name(),
                        'done' => $done,
                        'next' => $isnext,
                        'state' => get_string($done ? 'mc_act_done' : ($isnext ? 'mc_act_next' : 'mc_act_todo'), 'theme_lmslabs'),
                    ];
                }
            }
            $out['hasacts'] = !empty($out['acts']);
            $out['nexturl'] = $next ?? '';
            $out['more'] = max(0, $out['actstotal'] - 24);
        } catch (\Throwable $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * Template data for the list of programs.
     *
     * @return array
     */
    public static function export_list(): array {
        global $DB;
        $list = [];
        foreach (self::all() as $p) {
            $rules = audience::decode($p->audience);
            $list[] = [
                'id' => $p->id,
                'name' => format_string($p->name),
                'audience' => audience::summary($rules),
                'everyone' => $rules['everyone'],
                'courses' => $DB->count_records('theme_lmslabs_pcourse', ['programid' => $p->id]),
                'editurl' => (new moodle_url('/theme/lmslabs/program.php', ['id' => $p->id]))->out(false),
            ];
        }
        return ['programs' => $list, 'hasprograms' => (bool)$list];
    }

    /**
     * Template data for editing one program (id 0 = new).
     *
     * @param int $id
     * @return array
     */
    public static function export_editor(int $id): array {
        global $DB;
        $program = $id ? $DB->get_record('theme_lmslabs_program', ['id' => $id], '*', MUST_EXIST) : null;
        $rules = audience::decode($program->audience ?? null);

        // Courses in the program, grouped under headings.
        $headings = $id ? $DB->get_records('theme_lmslabs_pgroup', ['programid' => $id], 'sortorder ASC, id ASC') : [];
        $sections = [0 => ['id' => 0, 'name' => '', 'isdefault' => true, 'courses' => []]];
        foreach ($headings as $h) {
            $sections[$h->id] = ['id' => $h->id, 'name' => $h->name, 'isdefault' => false, 'courses' => []];
        }
        $incourses = [];
        if ($id) {
            foreach (self::rows($id) as $cid => $row) {
                $course = $DB->get_record('course', ['id' => $cid], 'id, fullname, shortname, visible');
                if (!$course) {
                    continue;
                }
                $incourses[$cid] = true;
                $gid = isset($sections[$row->groupid]) ? (int)$row->groupid : 0;
                $sections[$gid]['courses'][] = self::editor_row($course, $row);
            }
        }
        $default = $sections[0];
        unset($sections[0]);

        // Picker: every course with its category.
        $picker = [];
        $courses = $DB->get_records_select('course', 'id <> ?', [SITEID], 'fullname ASC', 'id, fullname, shortname, category, visible');
        $cats = \core_course_category::make_categories_list();
        foreach ($courses as $c) {
            $picker[] = [
                'id' => $c->id,
                'fullname' => format_string($c->fullname),
                'shortname' => format_string($c->shortname),
                'category' => $cats[$c->category] ?? '',
                'added' => isset($incourses[$c->id]),
                'rowhtml' => '',
            ];
        }

        $cohorts = [];
        foreach ($DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name, idnumber') as $c) {
            $cohorts[] = ['id' => $c->id, 'name' => format_string($c->name), 'selected' => in_array($c->id, $rules['cohorts'])];
        }
        $audcourses = [];
        foreach ($courses as $c) {
            $audcourses[] = ['id' => $c->id, 'name' => format_string($c->fullname) . ' (' . format_string($c->shortname) . ')',
                'selected' => in_array($c->id, $rules['courses'])];
        }
        $categories = [];
        foreach ($cats as $catid => $name) {
            $categories[] = ['id' => $catid, 'name' => $name, 'selected' => in_array($catid, $rules['categories'])];
        }
        $fieldoptions = audience::field_options();
        $fields = [];
        foreach ($rules['fields'] as $f) {
            $fields[] = [
                'value' => $f['value'],
                'options' => array_map(function ($o) use ($f) {
                    return $o + ['selected' => $o['value'] === $f['field']];
                }, $fieldoptions),
                'is' => $f['op'] === 'is', 'isnot' => $f['op'] === 'isnot',
                'contains' => $f['op'] === 'contains', 'starts' => $f['op'] === 'starts',
            ];
        }

        return [
            'id' => $id,
            'isnew' => !$id,
            'name' => $program ? $program->name : '',
            'description' => $program ? $program->description : '',
            'everyone' => $rules['everyone'],
            'specific' => !$rules['everyone'],
            'matchall' => $rules['match'] === 'all',
            'matchany' => $rules['match'] !== 'all',
            'cohorts' => $cohorts,
            'hascohorts' => (bool)$cohorts,
            'categories' => $categories,
            'audcourses' => $audcourses,
            'groups' => array_map(function ($g) {
                return ['name' => $g];
            }, $rules['groups']),
            'fields' => $fields,
            'fieldoptions' => $fieldoptions,
            'summary' => audience::summary($rules),
            'default' => $default,
            'groupsections' => array_values($sections),
            'picker' => $picker,
        ];
    }

    /**
     * Editor row data.
     *
     * @param stdClass $course
     * @param stdClass|null $row
     * @return array
     */
    public static function editor_row(stdClass $course, ?stdClass $row = null): array {
        $delay = (int)($row->delay ?? 0);
        $req = (int)($row->requireprev ?? 0);
        return [
            'id' => $course->id,
            'fullname' => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'hiddenoncourses' => !empty($row->hidden),
            'coursehidden' => empty($course->visible),
            'days' => intdiv($delay, DAYSECS),
            'hours' => intdiv($delay % DAYSECS, HOURSECS),
            'req0' => $req === self::REQUIRE_NONE,
            'req1' => $req === self::REQUIRE_SUBMITTED,
            'req2' => $req === self::REQUIRE_COMPLETED,
        ];
    }

    /**
     * Save one program from the editor.
     *
     * @param int $id 0 for new
     * @param array $data
     * @return int program id
     */
    public static function save(int $id, array $data): int {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $name = trim(clean_param($data['name'] ?? '', PARAM_TEXT));
        if ($name === '') {
            $name = get_string('untitledprogram', 'theme_lmslabs');
        }
        $audience = json_encode(audience::decode(json_encode($data['audience'] ?? ['everyone' => true])));
        $record = (object)[
            'name' => $name,
            'description' => clean_param($data['description'] ?? '', PARAM_TEXT),
            'audience' => $audience,
            'timemodified' => $now,
        ];
        if ($id && $DB->record_exists('theme_lmslabs_program', ['id' => $id])) {
            $record->id = $id;
            $DB->update_record('theme_lmslabs_program', $record);
        } else {
            $record->sortorder = (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {theme_lmslabs_program}') + 1;
            $id = $DB->insert_record('theme_lmslabs_program', $record);
        }

        $keepgroups = [];
        $keepcourses = [];
        $order = 0;
        $allgroups = array_merge([['id' => 0, 'courses' => $data['default'] ?? []]], $data['groups'] ?? []);
        foreach ($allgroups as $gi => $group) {
            $gid = 0;
            if ($gi > 0) {
                $gname = trim(clean_param($group['name'] ?? '', PARAM_TEXT));
                if ($gname === '') {
                    $gname = get_string('untitledgroup', 'theme_lmslabs');
                }
                $gid = (int)($group['id'] ?? 0);
                if ($gid && $DB->record_exists('theme_lmslabs_pgroup', ['id' => $gid, 'programid' => $id])) {
                    $DB->update_record(
                        'theme_lmslabs_pgroup', (object)['id' => $gid, 'name' => $gname, 'sortorder' => $gi,
                        'timemodified' => $now]
                    );
                } else {
                    $gid = $DB->insert_record(
                        'theme_lmslabs_pgroup', (object)['programid' => $id, 'name' => $gname,
                        'sortorder' => $gi, 'timemodified' => $now]
                    );
                }
                $keepgroups[] = $gid;
            }
            foreach ($group['courses'] ?? [] as $c) {
                $courseid = (int)($c['id'] ?? 0);
                if (!$courseid || $courseid == SITEID || isset($keepcourses[$courseid]) ||
                        !$DB->record_exists('course', ['id' => $courseid])) {
                    continue;
                }
                $keepcourses[$courseid] = true;
                $days = max(0, min(3650, (int)($c['days'] ?? 0)));
                $hours = max(0, min(23, (int)($c['hours'] ?? 0)));
                $req = (int)($c['req'] ?? 0);
                $row = (object)[
                    'programid' => $id,
                    'courseid' => $courseid,
                    'groupid' => $gid,
                    'sortorder' => ++$order,
                    'hidden' => empty($c['hidden']) ? 0 : 1,
                    'delay' => $days * DAYSECS + $hours * HOURSECS,
                    'requireprev' => in_array($req, [0, 1, 2]) ? $req : 0,
                    'timemodified' => $now,
                ];
                if ($rid = $DB->get_field('theme_lmslabs_pcourse', 'id', ['programid' => $id, 'courseid' => $courseid])) {
                    $row->id = $rid;
                    $DB->update_record('theme_lmslabs_pcourse', $row);
                } else {
                    $DB->insert_record('theme_lmslabs_pcourse', $row);
                }
            }
        }
        // Remove headings and courses taken out of this program.
        $select = 'programid = :pid';
        $params = ['pid' => $id];
        if ($keepgroups) {
            [$notin, $p] = $DB->get_in_or_equal($keepgroups, SQL_PARAMS_NAMED, 'g', false);
            $DB->delete_records_select('theme_lmslabs_pgroup', "$select AND id $notin", $params + $p);
        } else {
            $DB->delete_records_select('theme_lmslabs_pgroup', $select, $params);
        }
        if ($keepcourses) {
            [$notin, $p] = $DB->get_in_or_equal(array_keys($keepcourses), SQL_PARAMS_NAMED, 'c', false);
            $DB->delete_records_select('theme_lmslabs_pcourse', "$select AND courseid $notin", $params + $p);
        } else {
            $DB->delete_records_select('theme_lmslabs_pcourse', $select, $params);
        }
        $transaction->allow_commit();
        self::reset_caches();
        return $id;
    }

    /**
     * Delete a program and everything in it.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('theme_lmslabs_pcourse', ['programid' => $id]);
        $DB->delete_records('theme_lmslabs_pgroup', ['programid' => $id]);
        $DB->delete_records('theme_lmslabs_program', ['id' => $id]);
        self::reset_caches();
    }

    /**
     * Set program order.
     *
     * @param int[] $ids
     */
    public static function reorder(array $ids): void {
        global $DB;
        foreach (array_values($ids) as $i => $id) {
            $DB->set_field('theme_lmslabs_program', 'sortorder', $i + 1, ['id' => (int)$id]);
        }
        self::reset_caches();
    }
}
