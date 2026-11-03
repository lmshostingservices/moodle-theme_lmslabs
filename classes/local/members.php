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

use context;
use context_course;
use moodle_exception;

/**
 * Members of a course group or a cohort: list, search, add and remove, using Moodle's own APIs.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class members {
    /** Most rows returned per list. */
    const LIMIT = 200;

    /** @var string group|cohort */
    protected $type;
    /** @var \stdClass the group or cohort */
    protected $record;
    /** @var context */
    protected $context;

    /**
     * Load and check permission.
     *
     * @param string $type group|cohort
     * @param int $id
     */
    public function __construct(string $type, int $id) {
        global $CFG, $DB;
        $this->type = $type;
        if ($type === 'group') {
            require_once($CFG->dirroot . '/group/lib.php');
            $this->record = $DB->get_record('groups', ['id' => $id], '*', MUST_EXIST);
            $this->context = context_course::instance($this->record->courseid);
            require_capability('moodle/course:managegroups', $this->context);
        } else if ($type === 'cohort') {
            require_once($CFG->dirroot . '/cohort/lib.php');
            $this->record = $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
            $this->context = context::instance_by_id($this->record->contextid);
            require_capability('moodle/cohort:assign', $this->context);
            if (!empty($this->record->component)) {
                throw new moodle_exception('cohortmanagedbyplugin', 'theme_lmslabs');
            }
        } else {
            throw new moodle_exception('invalidparameter');
        }
    }

    /**
     * The group or cohort record.
     *
     * @return \stdClass
     */
    public function record(): \stdClass {
        return $this->record;
    }

    /**
     * Search condition on name and email.
     *
     * @param string $q
     * @return array [sql, params]
     */
    protected static function search_sql(string $q): array {
        global $DB;
        $q = trim($q);
        if ($q === '') {
            return ['', []];
        }
        $full = $DB->sql_fullname('u.firstname', 'u.lastname');
        $like1 = $DB->sql_like($full, ':q1', false, false);
        $like2 = $DB->sql_like('u.email', ':q2', false, false);
        $like3 = $DB->sql_like('u.idnumber', ':q3', false, false);
        $param = '%' . $DB->sql_like_escape($q) . '%';
        return [" AND ($like1 OR $like2 OR $like3)", ['q1' => $param, 'q2' => $param, 'q3' => $param]];
    }

    /**
     * Shape a user row for the client.
     *
     * @param \stdClass $u
     * @return array
     */
    protected static function shape(\stdClass $u): array {
        $name = fullname($u);
        $initials = \core_text::strtoupper(\core_text::substr($u->firstname, 0, 1) . \core_text::substr($u->lastname, 0, 1));
        return ['id' => (int)$u->id, 'name' => $name, 'email' => $u->email, 'initials' => $initials,
            'tint' => $u->id % 6];
    }

    /**
     * Current members.
     *
     * @param string $q
     * @return array ['users' => [], 'total' => int]
     */
    public function members(string $q = ''): array {
        global $DB;
        [$search, $params] = self::search_sql($q);
        $fields = 'u.id, u.firstname, u.lastname, u.email, ' . implode(
            ', ', array_map(function ($f) {
            return 'u.' . $f;
        }, \core_user\fields::get_name_fields())
        );
        $fields = implode(', ', array_unique(array_map('trim', explode(',', $fields))));
        if ($this->type === 'group') {
            $from = "FROM {groups_members} m JOIN {user} u ON u.id = m.userid WHERE m.groupid = :id AND u.deleted = 0";
        } else {
            $from = "FROM {cohort_members} m JOIN {user} u ON u.id = m.userid WHERE m.cohortid = :id AND u.deleted = 0";
        }
        $params['id'] = $this->record->id;
        $total = $DB->count_records_sql("SELECT COUNT(1) $from $search", $params);
        $users = $DB->get_records_sql("SELECT $fields $from $search ORDER BY u.lastname, u.firstname", $params, 0, self::LIMIT);
        return ['users' => array_values(array_map([self::class, 'shape'], $users)), 'total' => $total];
    }

    /**
     * People who could be added: course participants for a group, any active user for a cohort.
     *
     * @param string $q
     * @return array ['users' => [], 'total' => int]
     */
    public function candidates(string $q = ''): array {
        global $DB, $CFG;
        [$search, $params] = self::search_sql($q);
        $namefields = \core_user\fields::get_name_fields();
        $fields = implode(
            ', ', array_unique(
            array_merge(['u.id', 'u.email'], array_map(function ($f) {
            return 'u.' . $f;
        }, $namefields))
        )
        );
        if ($this->type === 'group') {
            [$esql, $eparams] = get_enrolled_sql($this->context, '', 0, true);
            $from = "FROM {user} u JOIN ($esql) je ON je.id = u.id
                    WHERE u.deleted = 0
                      AND NOT EXISTS (SELECT 1 FROM {groups_members} gm WHERE gm.groupid = :id AND gm.userid = u.id)";
            $params += $eparams;
        } else {
            $from = "FROM {user} u
                    WHERE u.deleted = 0 AND u.confirmed = 1 AND u.id <> :guest
                      AND NOT EXISTS (SELECT 1 FROM {cohort_members} cm WHERE cm.cohortid = :id AND cm.userid = u.id)";
            $params['guest'] = $CFG->siteguest;
        }
        $params['id'] = $this->record->id;
        $total = $DB->count_records_sql("SELECT COUNT(1) $from $search", $params);
        $users = $DB->get_records_sql("SELECT $fields $from $search ORDER BY u.lastname, u.firstname", $params, 0, self::LIMIT);
        return ['users' => array_values(array_map([self::class, 'shape'], $users)), 'total' => $total];
    }

    /**
     * Add users.
     *
     * @param int[] $userids
     * @return int how many were added
     */
    public function add(array $userids): int {
        global $DB;
        $done = 0;
        foreach (array_unique(array_map('intval', $userids)) as $uid) {
            if ($this->type === 'group') {
                if (is_enrolled($this->context, $uid) && groups_add_member($this->record, $uid)) {
                    $done++;
                }
            } else if ($DB->record_exists('user', ['id' => $uid, 'deleted' => 0])) {
                cohort_add_member($this->record->id, $uid);
                $done++;
            }
        }
        return $done;
    }

    /**
     * Remove users.
     *
     * @param int[] $userids
     * @return int how many were removed
     */
    public function remove(array $userids): int {
        $done = 0;
        foreach (array_unique(array_map('intval', $userids)) as $uid) {
            if ($this->type === 'group') {
                if (groups_remove_member_allowed($this->record, $uid) && groups_remove_member($this->record, $uid)) {
                    $done++;
                }
            } else {
                cohort_remove_member($this->record->id, $uid);
                $done++;
            }
        }
        return $done;
    }
}
