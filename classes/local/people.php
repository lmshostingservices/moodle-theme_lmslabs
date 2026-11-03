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

use moodle_url;

/**
 * People: every user on the site, filtered by role, cohort, course, group, category, status,
 * last access and profile fields.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class people {
    /** Rows per page. */
    const PERPAGE = 50;

    /**
     * Filter pills, in the order people look for them.
     *
     * @param array $selectedcourses course ids picked in the Course pill (narrows Group)
     * @return array
     */
    public static function pills(array $selectedcourses = []): array {
        global $DB;
        $roles = [];
        foreach (role_fix_names(get_all_roles(), \context_system::instance(), ROLENAME_ORIGINAL) as $r) {
            if (in_array($r->archetype, ['guest', 'user', 'frontpage'], true)) {
                continue;
            }
            $roles[] = ['value' => $r->id, 'label' => $r->localname];
        }
        $cohorts = [];
        foreach ($DB->get_records('cohort', null, 'name ASC', 'id, name') as $c) {
            $cohorts[] = ['value' => $c->id, 'label' => format_string($c->name)];
        }
        $courses = [];
        $catnames = \core_course_category::make_categories_list();
        foreach ($DB->get_records_select('course', 'id <> ?', [SITEID], 'fullname ASC', 'id, fullname, category') as $c) {
            $courses[] = ['value' => $c->id, 'label' => format_string($c->fullname), 'hint' => $catnames[$c->category] ?? ''];
        }
        $groupsql = 'SELECT g.id, g.name, c.shortname FROM {groups} g JOIN {course} c ON c.id = g.courseid';
        $groupparams = [];
        if ($selectedcourses) {
            [$in, $groupparams] = $DB->get_in_or_equal(array_map('intval', $selectedcourses));
            $groupsql .= " WHERE g.courseid $in";
        }
        $groups = [];
        foreach ($DB->get_records_sql($groupsql . ' ORDER BY c.shortname, g.name', $groupparams, 0, 500) as $g) {
            $groups[] = ['value' => $g->id, 'label' => format_string($g->name), 'group' => format_string($g->shortname)];
        }
        $categories = [];
        foreach ($catnames as $id => $name) {
            $categories[] = ['value' => $id, 'label' => $name];
        }
        $str = function ($id) {
            return get_string($id, 'theme_lmslabs');
        };
        return [
            'cohort' => ['label' => get_string('cohort', 'cohort'), 'icon' => 'contact-round', 'options' => $cohorts],
            'category' => ['label' => get_string('category'), 'icon' => 'folder-tree', 'options' => $categories],
            'course' => ['label' => get_string('course'), 'icon' => 'book-open', 'options' => $courses],
            'group' => ['label' => get_string('group'), 'icon' => 'users-round', 'options' => $groups],
            'role' => ['label' => $str('fb_role'), 'icon' => 'shield-check', 'options' => $roles],
            'status' => ['label' => $str('fb_status'), 'icon' => 'user-check', 'multi' => false, 'options' => [
                ['value' => 'active', 'label' => $str('fb_active'), 'group' => $str('fb_account')],
                ['value' => 'suspended', 'label' => $str('fb_suspended'), 'group' => $str('fb_account')],
                ['value' => '7', 'label' => $str('fb_access7'), 'group' => $str('fb_lastaccess')],
                ['value' => '30', 'label' => $str('fb_access30'), 'group' => $str('fb_lastaccess')],
                ['value' => 'idle30', 'label' => $str('fb_idle30'), 'group' => $str('fb_lastaccess')],
                ['value' => 'idle90', 'label' => $str('fb_idle90'), 'group' => $str('fb_lastaccess')],
                ['value' => 'never', 'label' => $str('fb_never'), 'group' => $str('fb_lastaccess')],
            ]],
        ];
    }

    /**
     * Quick card filters: key => [sql fragment, params].
     *
     * @return array
     */
    protected static function quick_sql(): array {
        $role = function (array $archetypes) {
            $list = "'" . implode("','", $archetypes) . "'";
            return "EXISTS (SELECT 1 FROM {role_assignments} qra JOIN {role} qr ON qr.id = qra.roleid
                             WHERE qra.userid = u.id AND qr.archetype IN ($list))";
        };
        $idle = time() - 30 * DAYSECS;
        return [
            'learners' => [$role(['student']), []],
            'teachers' => [$role(['editingteacher', 'teacher']), []],
            'staff' => [$role(['manager', 'coursecreator']), []],
            'suspended' => ['u.suspended = 1', []],
            'never' => ['u.lastaccess = 0', []],
            'idle' => ['u.lastaccess > 0 AND u.lastaccess < :qidle', ['qidle' => $idle]],
        ];
    }

    /**
     * Quick cards with site-wide counts.
     *
     * @return array key => [label, icon, count]
     */
    public static function quick_cards(): array {
        global $DB;
        [$base, $params] = self::base_sql();
        $cards = ['all' => [get_string('fb_allpeople', 'theme_lmslabs'), 'users',
            $DB->count_records_sql("SELECT COUNT(1) FROM {user} u WHERE $base", $params)]];
        $meta = [
            'learners' => ['fb_learners', 'graduation-cap'],
            'teachers' => ['fb_teachers', 'briefcase'],
            'staff' => ['fb_staff', 'shield-check'],
            'suspended' => ['fb_suspended', 'user-x'],
            'never' => ['fb_neverloggedin', 'moon'],
            'idle' => ['fb_idle', 'clock-alert'],
        ];
        foreach (self::quick_sql() as $key => [$sql, $qparams]) {
            $cards[$key] = [get_string($meta[$key][0], 'theme_lmslabs'), $meta[$key][1],
                $DB->count_records_sql("SELECT COUNT(1) FROM {user} u WHERE $base AND $sql", $params + $qparams)];
        }
        return $cards;
    }

    /**
     * Everyone who counts as a person on the site.
     *
     * @return array [sql, params]
     */
    protected static function base_sql(): array {
        global $CFG;
        return ['u.deleted = 0 AND u.id <> :guestid', ['guestid' => $CFG->siteguest]];
    }

    /**
     * WHERE clause for the current filters.
     *
     * @param filterbar $fb
     * @return array [sql, params]
     */
    public static function where(filterbar $fb): array {
        global $DB;
        [$sql, $params] = self::base_sql();
        $n = 0;
        $in = function (array $values, string $prefix) use ($DB, &$n) {
            $n++;
            return $DB->get_in_or_equal(array_map('intval', $values), SQL_PARAMS_NAMED, $prefix . $n);
        };

        if (($q = $fb->q()) !== '') {
            $like = '%' . $DB->sql_like_escape($q) . '%';
            $full = $DB->sql_fullname('u.firstname', 'u.lastname');
            $sql .= ' AND (' . $DB->sql_like($full, ':q1', false, false) . ' OR ' . $DB->sql_like('u.email', ':q2', false, false) .
                ' OR ' . $DB->sql_like('u.username', ':q3', false, false) . ' OR ' .
                $DB->sql_like('u.idnumber', ':q4', false, false) . ')';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (($quick = $fb->quick()) !== '' && isset(self::quick_sql()[$quick])) {
            [$qsql, $qparams] = self::quick_sql()[$quick];
            $sql .= " AND $qsql";
            $params += $qparams;
        }
        if ($v = $fb->get('role')) {
            [$i, $p] = $in($v, 'rl');
            $sql .= " AND EXISTS (SELECT 1 FROM {role_assignments} fra WHERE fra.userid = u.id AND fra.roleid $i)";
            $params += $p;
        }
        if ($v = $fb->get('cohort')) {
            [$i, $p] = $in($v, 'ch');
            $sql .= " AND EXISTS (SELECT 1 FROM {cohort_members} fcm WHERE fcm.userid = u.id AND fcm.cohortid $i)";
            $params += $p;
        }
        if ($v = $fb->get('course')) {
            [$i, $p] = $in($v, 'co');
            $sql .= " AND EXISTS (SELECT 1 FROM {user_enrolments} fue JOIN {enrol} fe ON fe.id = fue.enrolid
                                   WHERE fue.userid = u.id AND fe.courseid $i)";
            $params += $p;
        }
        if ($v = $fb->get('group')) {
            [$i, $p] = $in($v, 'gr');
            $sql .= " AND EXISTS (SELECT 1 FROM {groups_members} fgm WHERE fgm.userid = u.id AND fgm.groupid $i)";
            $params += $p;
        }
        if ($v = $fb->get('category')) {
            // Include sub-categories.
            $ids = [];
            foreach ($v as $catid) {
                $cat = \core_course_category::get((int)$catid, IGNORE_MISSING, true);
                if ($cat) {
                    $ids = array_merge($ids, [$cat->id], $cat->get_all_children_ids());
                }
            }
            if ($ids) {
                [$i, $p] = $in($ids, 'ca');
                $sql .= " AND EXISTS (SELECT 1 FROM {user_enrolments} cue JOIN {enrol} ce ON ce.id = cue.enrolid
                                       JOIN {course} cc ON cc.id = ce.courseid
                                      WHERE cue.userid = u.id AND cc.category $i)";
                $params += $p;
            }
        }
        if ($v = $fb->get('status')) {
            $now = time();
            switch ($v[0]) {
                case 'active':
                    $sql .= ' AND u.suspended = 0';
                    break;
                case 'suspended':
                    $sql .= ' AND u.suspended = 1';
                    break;
                case '7':
                case '30':
                    $sql .= ' AND u.lastaccess >= :acc';
                    $params['acc'] = $now - (int)$v[0] * DAYSECS;
                    break;
                case 'idle30':
                case 'idle90':
                    $sql .= ' AND u.lastaccess > 0 AND u.lastaccess < :acc';
                    $params['acc'] = $now - ($v[0] === 'idle30' ? 30 : 90) * DAYSECS;
                    break;
                case 'never':
                    $sql .= ' AND u.lastaccess = 0';
                    break;
            }
        }
        if ($pf = $fb->profile()) {
            [$field, $value] = $pf;
            $like = '%' . $DB->sql_like_escape($value) . '%';
            if (strpos($field, 'profile_field_') === 0) {
                $sql .= ' AND EXISTS (SELECT 1 FROM {user_info_data} fd JOIN {user_info_field} ff ON ff.id = fd.fieldid
                                       WHERE fd.userid = u.id AND ff.shortname = :pfname AND ' .
                    $DB->sql_like('fd.data', ':pfval', false, false) . ')';
                $params += ['pfname' => substr($field, 14), 'pfval' => $like];
            } else if (in_array($field, audience::STANDARD_FIELDS, true)) {
                $sql .= ' AND ' . $DB->sql_like('u.' . $field, ':pfval', false, false);
                $params['pfval'] = $like;
            }
        }
        return [$sql, $params];
    }

    /**
     * Sort options.
     *
     * @return array
     */
    public static function sorts(): array {
        return [
            'name' => get_string('fb_sort_name', 'theme_lmslabs'),
            'recent' => get_string('fb_sort_recent', 'theme_lmslabs'),
            'newest' => get_string('fb_sort_newest', 'theme_lmslabs'),
        ];
    }

    /**
     * Every user id matching the current filters (for "select all matching").
     *
     * @param filterbar $fb
     * @return int[]
     */
    public static function ids(filterbar $fb): array {
        global $DB;
        [$where, $params] = self::where($fb);
        return array_map('intval', $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE $where", $params));
    }

    /**
     * One page of results.
     *
     * @param filterbar $fb
     * @param int $page
     * @return array ['rows' => [], 'total' => int]
     */
    public static function results(filterbar $fb, int $page): array {
        global $DB;
        [$where, $params] = self::where($fb);
        $order = [
            'name' => 'u.lastname ASC, u.firstname ASC',
            'recent' => 'u.lastaccess DESC, u.lastname ASC',
            'newest' => 'u.timecreated DESC',
        ][$fb->sort()] ?? 'u.lastname ASC, u.firstname ASC';
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {user} u WHERE $where", $params);
        $fields = implode(
            ', ', array_unique(
            array_merge(['u.id', 'u.email', 'u.username', 'u.suspended', 'u.lastaccess',
            'u.auth', 'u.timecreated', 'u.picture', 'u.imagealt'], array_map(function ($f) {
                return 'u.' . $f;
            }, \core_user\fields::get_name_fields()))
        )
        );
        $users = $DB->get_records_sql(
            "SELECT $fields FROM {user} u WHERE $where ORDER BY $order", $params,
            $page * self::PERPAGE, self::PERPAGE
        );

        // Cohorts and course counts for this page, in two queries.
        $cohorts = [];
        $coursecounts = [];
        if ($users) {
            [$in, $p] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED);
            foreach ($DB->get_recordset_sql("SELECT cm.id, cm.userid, c.name FROM {cohort_members} cm
                                               JOIN {cohort} c ON c.id = cm.cohortid
                                              WHERE cm.userid $in ORDER BY c.name", $p) as $r) {
                $cohorts[$r->userid][] = format_string($r->name);
            }
            foreach ($DB->get_records_sql("SELECT ue.userid, COUNT(DISTINCT e.courseid) AS n
                                             FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                                            WHERE ue.userid $in GROUP BY ue.userid", $p) as $r) {
                $coursecounts[$r->userid] = (int)$r->n;
            }
        }
        $rows = [];
        $now = time();
        foreach ($users as $u) {
            $usercohorts = $cohorts[$u->id] ?? [];
            $rows[] = [
                'id' => $u->id,
                'name' => fullname($u),
                'email' => $u->email,
                'initials' => \core_text::strtoupper(
                    \core_text::substr($u->firstname, 0, 1) .
                    \core_text::substr($u->lastname, 0, 1)
                ),
                'tint' => $u->id % 6,
                'suspended' => (bool)$u->suspended,
                'never' => !$u->lastaccess,
                'lastaccess' => $u->lastaccess ? get_string('ago', 'core_message', format_time($now - $u->lastaccess)) : '',
                'lastaccessfull' => $u->lastaccess ? userdate($u->lastaccess) : '',
                'courses' => $coursecounts[$u->id] ?? 0,
                'cohorts' => array_map(function ($c) {
                    return ['name' => $c];
                }, array_slice($usercohorts, 0, 2)),
                'morecohorts' => max(0, count($usercohorts) - 2),
                'hasmorecohorts' => count($usercohorts) > 2,
                'auth' => $u->auth !== 'manual' ? $u->auth : null,
                'profileurl' => (new moodle_url('/user/profile.php', ['id' => $u->id]))->out(false),
                'editurl' => (new moodle_url('/user/editadvanced.php', ['id' => $u->id, 'course' => SITEID]))->out(false),
            ];
        }
        return ['rows' => $rows, 'total' => $total];
    }
}
