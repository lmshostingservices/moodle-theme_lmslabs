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

namespace theme_lmslabs\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: saved list views and a few remembered choices (preferences).
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\user_preference_provider {

    /** Preferences the theme stores. */
    const PREFS = ['theme_lmslabs_adduser', 'theme_lmslabs_addcourse', 'theme_lmslabs_reminders', 'theme_lmslabs_remsent',
        'theme_lmslabs_boostview'];

    /**
     * Describe the data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'theme_lmslabs_view', [
            'userid' => 'privacy:metadata:view:userid',
            'page' => 'privacy:metadata:view:page',
            'name' => 'privacy:metadata:view:name',
            'params' => 'privacy:metadata:view:params',
            'timecreated' => 'privacy:metadata:view:timecreated',
        ], 'privacy:metadata:view'
        );
        foreach (self::PREFS as $pref) {
            $collection->add_user_preference($pref, 'privacy:metadata:pref:' . str_replace('theme_lmslabs_', '', $pref));
        }
        return $collection;
    }

    /**
     * Contexts with data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql(
            "SELECT ctx.id FROM {context} ctx WHERE ctx.contextlevel = :lvl AND ctx.instanceid = :uid
                              AND EXISTS (SELECT 1 FROM {theme_lmslabs_view} v WHERE v.userid = :uid2)",
            ['lvl' => CONTEXT_USER, 'uid' => $userid, 'uid2' => $userid]
        );
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $userlist->add_from_sql(
                'userid', 'SELECT userid FROM {theme_lmslabs_view} WHERE userid = :uid',
                ['uid' => $context->instanceid]
            );
        }
    }

    /**
     * Export saved views.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER || $context->instanceid != $userid) {
                continue;
            }
            $views = array_map(function ($v) {
                return ['page' => $v->page, 'name' => $v->name, 'filters' => $v->params,
                    'timecreated' => transform::datetime($v->timecreated)];
            }, array_values($DB->get_records('theme_lmslabs_view', ['userid' => $userid])));
            writer::with_context($context)->export_data([get_string('pluginname', 'theme_lmslabs')], (object)['views' => $views]);
        }
    }

    /**
     * Export preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        foreach (self::PREFS as $pref) {
            $value = get_user_preferences($pref, null, $userid);
            if ($value !== null) {
                writer::export_user_preference(
                    'theme_lmslabs', $pref, $value,
                    get_string('privacy:metadata:pref:' . str_replace('theme_lmslabs_', '', $pref), 'theme_lmslabs')
                );
            }
        }
    }

    /**
     * Delete everything in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('theme_lmslabs_view', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $DB->delete_records('theme_lmslabs_view', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Delete several users' data.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel == CONTEXT_USER) {
            $DB->delete_records_list('theme_lmslabs_view', 'userid', $userlist->get_userids());
        }
    }
}
