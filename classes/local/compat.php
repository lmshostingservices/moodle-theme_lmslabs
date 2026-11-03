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
 * Calls whose home moved between Moodle 4.5 and 5.3.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compat {
    /**
     * Profile details the current user may see.
     *
     * @param \stdClass $user
     * @param array $fields
     * @return array|null
     */
    public static function user_details(\stdClass $user, array $fields = []): ?array {
        global $CFG;
        if (method_exists('\core\user', 'get_user_details')) {
            return \core\user::get_user_details($user, null, $fields);
        }
        require_once($CFG->dirroot . '/user/lib.php');
        return user_get_user_details($user, null, $fields);
    }

    /**
     * Whether the current user may view this profile.
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function can_view_profile(\stdClass $user): bool {
        global $CFG;
        if (method_exists('\core\user', 'can_view_profile')) {
            return \core\user::can_view_profile($user);
        }
        require_once($CFG->dirroot . '/user/lib.php');
        return user_can_view_profile($user);
    }
}
