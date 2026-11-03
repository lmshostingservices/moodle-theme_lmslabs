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

/**
 * Who a program is for: everyone, or learners matching cohorts, course categories,
 * course groups and profile fields (standard or custom).
 *
 * Stored as JSON: {"everyone":bool, "match":"any"|"all", "cohorts":[ids], "categories":[ids],
 * "groups":["name"], "fields":[{"field":"department","op":"is"|"contains"|"starts","value":"x"}]}
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience {
    /** Standard user fields that can be matched. */
    const STANDARD_FIELDS = ['institution', 'department', 'city', 'country', 'idnumber', 'auth', 'lang'];

    /** @var array user id => profile values cache */
    protected static $profiles = [];

    /**
     * Decode and normalise stored rules.
     *
     * @param string|null $json
     * @return array
     */
    public static function decode(?string $json): array {
        $a = json_decode((string)$json, true);
        if (!is_array($a)) {
            $a = ['everyone' => true];
        }
        return [
            'everyone' => !empty($a['everyone']),
            'match' => ($a['match'] ?? 'any') === 'all' ? 'all' : 'any',
            'cohorts' => array_values(array_filter(array_map('intval', $a['cohorts'] ?? []))),
            'categories' => array_values(array_filter(array_map('intval', $a['categories'] ?? []))),
            'courses' => array_values(array_filter(array_map('intval', $a['courses'] ?? []))),
            'groups' => array_values(
                array_filter(
                array_map(function ($g) {
                return trim(clean_param((string)$g, PARAM_TEXT));
            }, $a['groups'] ?? []), 'strlen'
            )
            ),
            'fields' => array_values(
                array_filter(
                array_map(function ($f) {
                $field = clean_param($f['field'] ?? '', PARAM_ALPHANUMEXT);
                $op = in_array($f['op'] ?? 'is', ['is', 'contains', 'starts', 'isnot']) ? $f['op'] : 'is';
                $value = trim(clean_param((string)($f['value'] ?? ''), PARAM_TEXT));
                return ($field !== '') ? ['field' => $field, 'op' => $op, 'value' => $value] : null;
            }, $a['fields'] ?? [])
            )
            ),
        ];
    }

    /**
     * Does a user belong to this audience?
     *
     * @param array $rules decoded rules
     * @param int $userid
     * @return bool
     */
    public static function matches(array $rules, int $userid): bool {
        if ($rules['everyone']) {
            return true;
        }
        $checks = [];
        if ($rules['cohorts']) {
            $checks[] = function () use ($rules, $userid) {
                global $DB;
                [$in, $params] = $DB->get_in_or_equal($rules['cohorts'], SQL_PARAMS_NAMED);
                $params['userid'] = $userid;
                return $DB->record_exists_select('cohort_members', "userid = :userid AND cohortid $in", $params);
            };
        }
        if ($rules['categories']) {
            $checks[] = function () use ($rules, $userid) {
                foreach (enrol_get_all_users_courses($userid, true, 'category') as $course) {
                    $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
                    if (!$cat) {
                        continue;
                    }
                    $path = array_map('intval', explode('/', trim($cat->path, '/')));
                    if (array_intersect($path, $rules['categories'])) {
                        return true;
                    }
                }
                return false;
            };
        }
        if ($rules['courses']) {
            $checks[] = function () use ($rules, $userid) {
                foreach ($rules['courses'] as $cid) {
                    $ctx = \context_course::instance($cid, IGNORE_MISSING);
                    if ($ctx && is_enrolled($ctx, $userid, '', true)) {
                        return true;
                    }
                }
                return false;
            };
        }
        if ($rules['groups']) {
            $checks[] = function () use ($rules, $userid) {
                global $DB;
                $names = $DB->get_fieldset_sql(
                    'SELECT DISTINCT LOWER(g.name) FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
                      WHERE gm.userid = ?', [$userid]);
                foreach ($rules['groups'] as $want) {
                    if (in_array(\core_text::strtolower($want), $names, true)) {
                        return true;
                    }
                }
                return false;
            };
        }
        foreach ($rules['fields'] as $f) {
            $checks[] = function () use ($f, $userid) {
                $value = \core_text::strtolower((string)(self::profile($userid)[$f['field']] ?? ''));
                $want = \core_text::strtolower($f['value']);
                switch ($f['op']) {
                    case 'contains':
                        return $want !== '' && strpos($value, $want) !== false;
                    case 'starts':
                        return $want !== '' && strpos($value, $want) === 0;
                    case 'isnot':
                        return $value !== $want;
                    default:
                        return $value === $want;
                }
            };
        }
        if (!$checks) {
            return false;
        }
        foreach ($checks as $check) {
            $ok = $check();
            if ($ok && $rules['match'] === 'any') {
                return true;
            }
            if (!$ok && $rules['match'] === 'all') {
                return false;
            }
        }
        return $rules['match'] === 'all';
    }

    /**
     * A user's standard and custom profile values, keyed by field name (custom as profile_field_x).
     *
     * @param int $userid
     * @return array
     */
    public static function profile(int $userid): array {
        global $CFG, $DB;
        if (!isset(self::$profiles[$userid])) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            $user = $DB->get_record('user', ['id' => $userid], implode(',', array_merge(['id'], self::STANDARD_FIELDS)));
            $values = $user ? (array)$user : [];
            foreach ((array)profile_user_record($userid, false) as $short => $value) {
                $values['profile_field_' . $short] = is_scalar($value) ? (string)$value : '';
            }
            self::$profiles[$userid] = $values;
        }
        return self::$profiles[$userid];
    }

    /**
     * Fields offered in the audience builder.
     *
     * @return array [['value' => key, 'label' => text]]
     */
    public static function field_options(): array {
        global $DB;
        $opts = [];
        foreach (self::STANDARD_FIELDS as $f) {
            $opts[] = ['value' => $f, 'label' => get_string($f === 'auth' ? 'authentication' : ($f === 'lang' ? 'language' : $f))];
        }
        foreach ($DB->get_records('user_info_field', null, 'sortorder', 'id, shortname, name') as $f) {
            $opts[] = ['value' => 'profile_field_' . $f->shortname, 'label' => format_string($f->name)];
        }
        return $opts;
    }

    /**
     * One-line plain summary of who the program is for.
     *
     * @param array $rules
     * @return string
     */
    public static function summary(array $rules): string {
        global $DB;
        if ($rules['everyone']) {
            return get_string('aud_everyone', 'theme_lmslabs');
        }
        $parts = [];
        if ($rules['cohorts']) {
            $names = $DB->get_fieldset_select('cohort', 'name', 'id IN (' . implode(',', $rules['cohorts']) . ')');
            $parts[] = get_string('aud_sum_cohorts', 'theme_lmslabs', implode(', ', array_map('format_string', $names)));
        }
        if ($rules['categories']) {
            $names = $DB->get_fieldset_select(
                'course_categories', 'name',
                'id IN (' . implode(',', $rules['categories']) . ')'
            );
            $parts[] = get_string('aud_sum_categories', 'theme_lmslabs', implode(', ', array_map('format_string', $names)));
        }
        if ($rules['courses']) {
            $names = $DB->get_fieldset_select('course', 'shortname', 'id IN (' . implode(',', $rules['courses']) . ')');
            $parts[] = get_string('aud_sum_courses', 'theme_lmslabs', implode(', ', array_map('format_string', $names)));
        }
        if ($rules['groups']) {
            $parts[] = get_string('aud_sum_groups', 'theme_lmslabs', implode(', ', $rules['groups']));
        }
        $labels = array_column(self::field_options(), 'label', 'value');
        foreach ($rules['fields'] as $f) {
            $parts[] = get_string(
                'aud_sum_field_' . $f['op'], 'theme_lmslabs',
                ['field' => $labels[$f['field']] ?? $f['field'], 'value' => $f['value']]
            );
        }
        if (!$parts) {
            return get_string('aud_nobody', 'theme_lmslabs');
        }
        return implode(
            $rules['match'] === 'all' ? get_string('aud_and', 'theme_lmslabs') : get_string('aud_or', 'theme_lmslabs'),
            $parts
        );
    }
}
