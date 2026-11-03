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
use context_module;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Overrides for assignments and quizzes: who gets different dates, time or attempts, set in one step.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overrides {
    /** @var string[] fields an assignment can override */
    const ASSIGN = ['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'timelimit'];
    /** @var string[] fields a quiz can override */
    const QUIZ = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    /**
     * Assignments and quizzes of a course the person may set overrides on, with how many each has.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function activities(\stdClass $course): array {
        global $DB;
        $modinfo = get_fast_modinfo($course);
        $counts = [
            'assign' => $DB->get_records_sql_menu('SELECT assignid, COUNT(1) FROM {assign_overrides} GROUP BY assignid'),
            'quiz' => $DB->get_records_sql_menu('SELECT quiz, COUNT(1) FROM {quiz_overrides} GROUP BY quiz'),
        ];
        $out = [];
        foreach (['assign', 'quiz'] as $mod) {
            foreach ($modinfo->get_instances_of($mod) as $cm) {
                if (!$cm->uservisible || !has_capability("mod/$mod:manageoverrides", $cm->context)) {
                    continue;
                }
                $out[] = ['cmid' => $cm->id, 'type' => $mod, 'name' => format_string($cm->name),
                    'count' => (int)($counts[$mod][$cm->instance] ?? 0),
                    'url' => (new moodle_url('/theme/lmslabs/overrides.php', ['cmid' => $cm->id]))->out(false)];
            }
        }
        usort(
            $out, function ($a, $b) {
            return $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']);
        }
        );
        return $out;
    }

    /**
     * The settings an activity can override.
     *
     * @param string $type assign|quiz
     * @return string[]
     */
    public static function fields(string $type): array {
        return $type === 'quiz' ? self::QUIZ : self::ASSIGN;
    }

    /**
     * The activity record with its course module id.
     *
     * @param cm_info $cm
     * @return \stdClass
     */
    public static function instance(cm_info $cm): \stdClass {
        global $DB;
        $r = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
        $r->cmid = $cm->id;
        return $r;
    }

    /**
     * A timestamp as a value for a datetime-local input.
     *
     * @param int|null $ts
     * @return string
     */
    public static function input(?int $ts): string {
        return $ts ? userdate($ts, '%Y-%m-%dT%H:%M', 99, false) : '';
    }

    /**
     * A datetime-local value (in the person's time zone) as a timestamp.
     *
     * @param string $v
     * @return int|null
     */
    public static function stamp(string $v): ?int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/', $v, $m)) {
            return null;
        }
        return make_timestamp((int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5], 0);
    }

    /**
     * Short date and time for display.
     *
     * @param int|null $ts
     * @return string
     */
    public static function show(?int $ts): string {
        return $ts ? userdate($ts, get_string('strftimedatetimeshort', 'langconfig')) : '';
    }

    /**
     * Existing overrides, one row each, with a readable summary of what differs.
     *
     * @param cm_info $cm
     * @return array
     */
    public static function rows(cm_info $cm): array {
        global $DB;
        $type = $cm->modname;
        $inst = self::instance($cm);
        $table = $type === 'quiz' ? 'quiz_overrides' : 'assign_overrides';
        $key = $type === 'quiz' ? 'quiz' : 'assignid';
        $out = [];
        foreach ($DB->get_records($table, [$key => $inst->id]) as $o) {
            $who = '';
            $initials = '';
            $isgroup = empty($o->userid);
            if (!$isgroup) {
                $u = \core_user::get_user($o->userid);
                $who = $u ? fullname($u) : '';
                $initials = $u ? \core_text::strtoupper(
                    \core_text::substr($u->firstname, 0, 1) .
                    \core_text::substr($u->lastname, 0, 1)
                ) : '';
            } else {
                $who = format_string(groups_get_group_name($o->groupid) ?: '');
            }
            $changes = [];
            foreach (self::fields($type) as $f) {
                if (!isset($o->$f) || $o->$f === null) {
                    continue;
                }
                $v = $o->$f;
                if (in_array($f, ['timelimit'])) {
                    $text = $v ? format_time($v) : get_string('cb_unlimited', 'theme_lmslabs');
                } else if ($f === 'attempts') {
                    $text = $v ? (string)$v : get_string('unlimited');
                } else if ($f === 'password') {
                    $text = '••••';
                } else {
                    $text = $v ? self::show((int)$v) : get_string('ov_nodate', 'theme_lmslabs');
                }
                $changes[] = ['label' => get_string('ov_f_' . $f, 'theme_lmslabs'), 'value' => $text, 'was' => self::was($inst, $f)];
            }
            $out[] = ['id' => (int)$o->id, 'who' => $who, 'initials' => $initials, 'isgroup' => $isgroup,
                'tint' => ((int)($o->userid ?: $o->groupid)) % 6, 'changes' => $changes,
                'userid' => (int)$o->userid, 'groupid' => (int)$o->groupid];
        }
        usort(
            $out, function ($a, $b) {
            return strcmp($a['who'], $b['who']);
        }
        );
        return $out;
    }

    /**
     * What the activity itself has for a setting, as text.
     *
     * @param \stdClass $inst
     * @param string $f
     * @return string
     */
    protected static function was(\stdClass $inst, string $f): string {
        $v = $inst->$f ?? 0;
        if ($f === 'timelimit') {
            return $v ? format_time($v) : get_string('cb_unlimited', 'theme_lmslabs');
        }
        if ($f === 'attempts') {
            return $v ? (string)$v : get_string('unlimited');
        }
        if ($f === 'password') {
            return $v ? '••••' : '';
        }
        return $v ? self::show((int)$v) : get_string('ov_nodate', 'theme_lmslabs');
    }

    /**
     * Save the same override for several people, or one group.
     *
     * @param cm_info $cm
     * @param int[] $userids
     * @param int $groupid
     * @param array $values field => value (already as timestamps/numbers); missing fields are left alone
     * @return int how many overrides were saved
     */
    public static function save(cm_info $cm, array $userids, int $groupid, array $values): int {
        global $DB;
        $type = $cm->modname;
        $inst = self::instance($cm);
        $fields = self::fields($type);
        $values = array_intersect_key($values, array_flip($fields));
        if (!$values) {
            throw new \invalid_parameter_exception(get_string('ov_nothing', 'theme_lmslabs'));
        }
        $targets = $groupid ? [['groupid' => $groupid]] : array_map(function ($u) {
            return ['userid' => (int)$u];
        }, $userids);
        $n = 0;
        foreach ($targets as $t) {
            $table = $type === 'quiz' ? 'quiz_overrides' : 'assign_overrides';
            $key = $type === 'quiz' ? 'quiz' : 'assignid';
            $existing = $DB->get_record(
                $table, [$key => $inst->id, 'userid' => $t['userid'] ?? null,
                'groupid' => $t['groupid'] ?? null]
            );
            $data = $values + $t;
            if ($existing) {
                $data['id'] = $existing->id;
            }
            if ($type === 'quiz') {
                $mgr = new \mod_quiz\local\override_manager($inst, $cm->context);
                $mgr->save_override($data);
            } else {
                self::save_assign($inst, $cm, $data);
            }
            $n++;
        }
        return $n;
    }

    /**
     * Save one assignment override, on any supported Moodle.
     *
     * @param \stdClass $assign
     * @param cm_info $cm
     * @param array $data
     */
    protected static function save_assign(\stdClass $assign, cm_info $cm, array $data): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        if (class_exists('\mod_assign\override_manager')) {
            (new \mod_assign\override_manager($assign, $cm->context))->save_overrides([$data]);
            return;
        }
        // Moodle 4.5 and 5.0 to 5.2: the same steps overrideedit.php takes.
        $rec = (object)$data;
        $rec->assignid = $assign->id;
        $group = !empty($rec->groupid);
        foreach (self::ASSIGN as $k) {
            if (!isset($rec->$k) || $rec->$k == $assign->$k) {
                $rec->$k = null;
            }
        }
        $rec->userid = $rec->userid ?? null;
        $rec->groupid = $rec->groupid ?? null;
        $params = ['context' => $cm->context, 'other' => ['assignid' => $assign->id]];
        if (!empty($rec->id)) {
            $DB->update_record('assign_overrides', $rec);
            $params['objectid'] = $rec->id;
            $event = $group ? \mod_assign\event\group_override_updated::class : \mod_assign\event\user_override_updated::class;
        } else {
            unset($rec->id);
            $rec->id = $DB->insert_record('assign_overrides', $rec);
            if ($group) {
                $rec->sortorder = 1;
                $DB->update_record('assign_overrides', $rec);
                reorder_group_overrides($assign->id);
            }
            $params['objectid'] = $rec->id;
            $event = $group ? \mod_assign\event\group_override_created::class : \mod_assign\event\user_override_created::class;
        }
        $cachekey = $group ? "{$assign->id}_g_{$rec->groupid}" : "{$assign->id}_u_{$rec->userid}";
        \cache::make('mod_assign', 'overrides')->delete($cachekey);
        if ($group) {
            $params['other']['groupid'] = $rec->groupid;
        } else {
            $params['relateduserid'] = $rec->userid;
        }
        $event::create($params)->trigger();
        assign_update_events(new \assign($cm->context, $cm, $cm->get_course()), $rec);
    }

    /**
     * Remove overrides.
     *
     * @param cm_info $cm
     * @param int[] $ids
     */
    public static function delete(cm_info $cm, array $ids): void {
        global $CFG;
        $inst = self::instance($cm);
        if ($cm->modname === 'quiz') {
            (new \mod_quiz\local\override_manager($inst, $cm->context))->delete_overrides_by_id($ids);
            return;
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        if (class_exists('\mod_assign\override_manager')) {
            (new \mod_assign\override_manager($inst, $cm->context))->delete_overrides_by_id($ids);
            return;
        }
        $assign = new \assign($cm->context, $cm, $cm->get_course());
        foreach ($ids as $id) {
            $assign->delete_override($id);
        }
    }
}
