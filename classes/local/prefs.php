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
 * The preferences people actually change, each saved the moment it's changed.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prefs {
    /**
     * Allowed settings: key => [kind (pref|user), allowed values].
     *
     * @return array
     */
    public static function allowed(): array {
        $editors = array_merge([''], array_keys(editors_get_enabled()));
        return [
            'lang' => ['user', array_keys(get_string_manager()->get_list_of_translations())],
            'timezone' => ['user', array_merge(['99'], array_keys(\core_date::get_list_of_timezones()))],
            'emailstop' => ['user', ['0', '1']],
            'maildigest' => ['user', ['0', '1', '2']],
            'autosubscribe' => ['user', ['0', '1']],
            'trackforums' => ['user', ['0', '1']],
            'message_blocknoncontacts' => ['pref', ['0', '1', '2']],
            'message_entertosend' => ['pref', ['0', '1']],
            'calendar_startwday' => ['pref', ['0', '1', '6']],
            'calendar_timeformat' => ['pref', ['', '%I:%M %p', '%H:%M']],
            'theme_lmslabs_reminders' => ['pref', ['0', '1']],
            'htmleditor' => ['pref', $editors],
        ];
    }

    /**
     * Save one setting for the current user.
     *
     * @param string $key
     * @param string $value
     */
    public static function save(string $key, string $value): void {
        global $USER, $CFG;
        $allowed = self::allowed();
        if (!isset($allowed[$key]) || !in_array($value, array_map('strval', $allowed[$key][1]), true)) {
            throw new \invalid_parameter_exception('Not allowed');
        }
        if ($allowed[$key][0] === 'pref') {
            set_user_preference($key, $value === '' ? null : $value);
            return;
        }
        require_once($CFG->dirroot . '/user/lib.php');
        $change = (object)['id' => $USER->id, $key => $value];
        if (method_exists('\core\user', 'update_user')) {
            \core\user::update_user($change, false, true);
        } else {
            user_update_user($change, false, true);
        }
        $USER->$key = $value;
        if ($key === 'lang') {
            force_current_language($value);
        }
    }
}
