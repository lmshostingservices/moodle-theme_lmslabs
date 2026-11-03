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
 * Course participants: who's in, their roles, groups, progress and last visit; enrol and manage in one place.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants {
    /** Most rows a page shows (filtering happens in the browser). */
    const LIMIT = 1000;

    /**
     * Everyone enrolled, ready for the template.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function rows(\stdClass $course): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $ctx = context_course::instance($course->id);
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, $fields, u.email, u.picture, u.imagealt, u.lastaccess AS sitelast,
                                              u.suspended AS usersuspended
                                         FROM {user} u
                                         JOIN {user_enrolments} ue ON ue.userid = u.id
                                         JOIN {enrol} e ON e.id = ue.enrolid
                                        WHERE e.courseid = :c AND u.deleted = 0
                                     ORDER BY u.lastname, u.firstname", ['c' => $course->id], 0, self::LIMIT
        );
        if (!$users) {
            return [];
        }
        [$in, $p] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED);
        $last = $DB->get_records_sql_menu(
            "SELECT userid, timeaccess FROM {user_lastaccess} WHERE courseid = :c AND userid $in",
            $p + ['c' => $course->id]
        );
        $enrol = [];
        foreach ($DB->get_recordset_sql("SELECT ue.id, ue.userid, ue.status, ue.timestart, ue.timeend, e.enrol, e.status AS estatus
                                           FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                                          WHERE e.courseid = :c AND ue.userid $in", $p + ['c' => $course->id]) as $r) {
            $enrol[$r->userid][] = $r;
        }
        $roles = [];
        $rolenames = role_fix_names(get_all_roles($ctx), $ctx, ROLENAME_ALIAS, true);
        foreach ($DB->get_recordset_sql("SELECT ra.id, ra.userid, ra.roleid FROM {role_assignments} ra
                                          WHERE ra.contextid = :ctx AND ra.userid $in", $p + ['ctx' => $ctx->id]) as $r) {
            $roles[$r->userid][$r->roleid] = $rolenames[$r->roleid] ?? '';
        }
        $groups = [];
        foreach ($DB->get_recordset_sql("SELECT gm.id, gm.userid, g.id AS gid, g.name FROM {groups_members} gm
                                           JOIN {groups} g ON g.id = gm.groupid
                                          WHERE g.courseid = :c AND gm.userid $in", $p + ['c' => $course->id]) as $r) {
            $groups[$r->userid][$r->gid] = format_string($r->name);
        }
        $completion = new \completion_info($course);
        $tracked = $completion->is_enabled();
        $now = time();
        $out = [];
        foreach ($users as $u) {
            $active = false;
            foreach ($enrol[$u->id] ?? [] as $e) {
                if ($e->status == ENROL_USER_ACTIVE && $e->estatus == ENROL_INSTANCE_ENABLED &&
                        (!$e->timestart || $e->timestart <= $now) && (!$e->timeend || $e->timeend > $now)) {
                    $active = true;
                }
            }
            $active = $active && !$u->usersuspended;
            $lastaccess = (int)($last[$u->id] ?? 0);
            $pct = $tracked ? \core_completion\progress::get_course_progress_percentage($course, $u->id) : null;
            $userroles = $roles[$u->id] ?? [];
            $islearner = (bool)array_intersect(array_keys($userroles), array_keys(get_archetype_roles('student')));
            $status = !$active ? 'suspended' : (!$lastaccess ? 'never' : ($lastaccess < $now - 14 * DAYSECS ? 'idle' : 'active'));
            $out[] = [
                'id' => (int)$u->id,
                'name' => fullname($u),
                'email' => $u->email,
                'initials' => \core_text::strtoupper(\core_text::substr($u->firstname, 0, 1) . \core_text::substr($u->lastname, 0, 1)),
                'tint' => $u->id % 6,
                'roles' => array_map(function ($n) {
                    return ['name' => $n];
                }, array_values($userroles)),
                'roleids' => implode(' ', array_keys($userroles)),
                'learner' => $islearner,
                'groups' => array_map(function ($n) {
                    return ['name' => $n];
                }, array_values($groups[$u->id] ?? [])),
                'groupids' => implode(' ', array_keys($groups[$u->id] ?? [])),
                'progress' => $pct === null || !$islearner ? null : (int)round($pct),
                'last' => $lastaccess ? self::ago($lastaccess) : get_string('never'),
                'lastts' => $lastaccess,
                'status' => $status,
                'statuslabel' => get_string('pt_status_' . $status, 'theme_lmslabs'),
                'methods' => implode(
                    ', ', array_unique(
                    array_map(function ($e) {
                    return get_string('pluginname', 'enrol_' . $e->enrol);
                }, $enrol[$u->id] ?? [])
                )
                ),
                'profileurl' => (new moodle_url('/theme/lmslabs/profile.php', ['id' => $u->id]))->out(false),
                'gradesurl' => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id, 'userid' => $u->id]))->out(false),
                'messageurl' => (new moodle_url('/theme/lmslabs/messages.php', ['id' => $u->id]))->out(false),
            ];
        }
        return $out;
    }

    /**
     * "3 days ago" in short form.
     *
     * @param int $ts
     * @return string
     */
    public static function ago(int $ts): string {
        $parts = explode(' ', format_time(time() - $ts));
        return get_string('ago', 'message', implode(' ', array_slice($parts, 0, 2)));
    }

    /**
     * People who could be enrolled, matching a search.
     *
     * @param \stdClass $course
     * @param string $term
     * @return array
     */
    public static function search(\stdClass $course, string $term): array {
        global $DB, $CFG;
        $term = trim($term);
        if (\core_text::strlen($term) < 2) {
            return [];
        }
        $like1 = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':t1', false);
        $like2 = $DB->sql_like('u.email', ':t2', false);
        $like3 = $DB->sql_like('u.username', ':t3', false);
        $t = '%' . $DB->sql_like_escape($term) . '%';
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rs = $DB->get_records_sql(
            "SELECT u.id, $fields, u.email FROM {user} u
                                     WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id <> :guest
                                           AND ($like1 OR $like2 OR $like3)
                                           AND NOT EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                                                            WHERE e.courseid = :c AND ue.userid = u.id)
                                  ORDER BY u.lastname, u.firstname",
            ['t1' => $t, 't2' => $t, 't3' => $t, 'c' => $course->id, 'guest' => $CFG->siteguest], 0, 20
        );
        return array_values(
            array_map(function ($u) {
            return ['id' => (int)$u->id, 'name' => fullname($u), 'email' => $u->email,
                'initials' => \core_text::strtoupper(\core_text::substr($u->firstname, 0, 1) . \core_text::substr($u->lastname, 0, 1)),
                'tint' => $u->id % 6];
        }, $rs)
        );
    }

    /**
     * Roles a manager may give in this course (manual enrolment).
     *
     * @param \stdClass $course
     * @return array
     */
    public static function assignable_roles(\stdClass $course): array {
        $ctx = context_course::instance($course->id);
        $out = [];
        foreach (get_assignable_roles($ctx, ROLENAME_ALIAS, false) as $id => $name) {
            $out[] = ['id' => $id, 'name' => $name];
        }
        return $out;
    }
}
