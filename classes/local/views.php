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
 * Saved filter views, private to each user, one optional default per page.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class views {
    /** Pages that support saved views. */
    const PAGES = ['people', 'courses', 'cohorts'];

    /**
     * A user's views for a page, as template data.
     *
     * @param int $userid
     * @param string $page
     * @param moodle_url $base
     * @param string $currentquery
     * @return array ['views' => [...]]
     */
    public static function for_user(int $userid, string $page, moodle_url $base, string $currentquery): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('theme_lmslabs_view', ['userid' => $userid, 'page' => $page], 'name ASC') as $v) {
            $out[] = [
                'id' => $v->id,
                'name' => format_string($v->name),
                'url' => $base->out_omit_querystring() . '?' . $v->params,
                'isdefault' => (bool)$v->isdefault,
                'current' => $v->params === $currentquery,
            ];
        }
        return ['views' => $out];
    }

    /**
     * The default view's query string, if any.
     *
     * @param int $userid
     * @param string $page
     * @return string|null
     */
    public static function default_query(int $userid, string $page): ?string {
        global $DB;
        $params = $DB->get_field(
            'theme_lmslabs_view', 'params', ['userid' => $userid, 'page' => $page, 'isdefault' => 1],
            IGNORE_MULTIPLE
        );
        return $params === false ? null : $params;
    }

    /**
     * On a bare visit, jump to the user's default view.
     *
     * @param string $page
     * @param moodle_url $base
     */
    public static function apply_default(string $page, moodle_url $base): void {
        global $USER;
        if (!empty(request::query()) || !request::is_get()) {
            return;
        }
        $query = self::default_query($USER->id, $page);
        if ($query !== null && $query !== '') {
            redirect($base->out_omit_querystring() . '?' . $query);
        }
    }

    /**
     * Save a view.
     *
     * @param int $userid
     * @param string $page
     * @param string $name
     * @param string $query
     * @return int
     */
    public static function save(int $userid, string $page, string $name, string $query): int {
        global $DB;
        // Keep only filter parameters; never paging or the "show all" switch.
        parse_str($query, $params);
        unset($params['page'], $params['all'], $params['sesskey']);
        return $DB->insert_record(
            'theme_lmslabs_view', (object)[
            'userid' => $userid, 'page' => $page, 'name' => $name,
            'params' => http_build_query($params, '', '&'), 'isdefault' => 0, 'timecreated' => time(),
        ]
        );
    }

    /**
     * Delete a view (own only).
     *
     * @param int $id
     * @param int $userid
     */
    public static function delete(int $id, int $userid): void {
        global $DB;
        $DB->delete_records('theme_lmslabs_view', ['id' => $id, 'userid' => $userid]);
    }

    /**
     * Make a view the default (or clear it if it already is).
     *
     * @param int $id
     * @param int $userid
     */
    public static function toggle_default(int $id, int $userid): void {
        global $DB;
        $view = $DB->get_record('theme_lmslabs_view', ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
        $DB->set_field('theme_lmslabs_view', 'isdefault', 0, ['userid' => $userid, 'page' => $view->page]);
        if (!$view->isdefault) {
            $DB->set_field('theme_lmslabs_view', 'isdefault', 1, ['id' => $id]);
        }
    }
}
