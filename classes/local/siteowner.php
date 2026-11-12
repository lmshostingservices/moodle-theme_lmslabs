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
 * The Site Owner role: runs the site day to day without site administrator access.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class siteowner {
    /** Role short name. */
    const SHORTNAME = 'siteowner';

    /** Capabilities added on top of the Manager defaults. */
    const EXTRA = [
        'theme/lmslabs:managebrand',
        'theme/lmslabs:manageprograms',
        'theme/lmslabs:manageshortcuts',
        'moodle/site:approvecourse',
    ];

    /** LMS Labs tool capabilities granted when the tool is installed. */
    const TOOLS = ['tool/speedup:view'];

    /**
     * Create the role if missing and make sure it carries the theme capabilities.
     *
     * @return int role id
     */
    public static function ensure_role(): int {
        global $DB;
        $syscontext = \context_system::instance();
        $role = $DB->get_record('role', ['shortname' => self::SHORTNAME]);
        if (!$role) {
            $roleid = create_role(
                get_string('siteowner', 'theme_lmslabs'),
                self::SHORTNAME,
                get_string('siteowner_desc', 'theme_lmslabs'),
                'manager'
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
            // Start from Manager's defaults.
            foreach (get_default_capabilities('manager') as $cap => $permission) {
                assign_capability($cap, $permission, $roleid, $syscontext->id, true);
            }
            // Site Owners can assign, override and switch to the same roles a Manager can.
            $manager = $DB->get_record('role', ['shortname' => 'manager']);
            if ($manager) {
                foreach (['assign', 'override', 'switch', 'view'] as $type) {
                    foreach (get_default_role_archetype_allows($type, 'manager') as $allowed) {
                        $fn = 'core_role_set_' . $type . '_allowed';
                        if (function_exists($fn)) {
                            $fn($roleid, $allowed);
                        }
                    }
                }
            }
        } else {
            $roleid = (int)$role->id;
        }
        // Capabilities may not be registered yet during install, so make sure they are.
        update_capabilities('theme_lmslabs');
        foreach (self::EXTRA as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        // LMS Labs admin tools the Site Owner may open (read-only views), when installed.
        foreach (self::TOOLS as $cap) {
            if (get_capability_info($cap)) {
                assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
            }
        }
        $syscontext->mark_dirty();
        return $roleid;
    }

    /**
     * Users holding the Site Owner role at site level.
     *
     * @return array of user records
     */
    public static function users(): array {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => self::SHORTNAME]);
        if (!$role) {
            return [];
        }
        return get_role_users(
            $role->id, \context_system::instance(), false,
            'u.id, u.email, u.deleted, u.suspended, u.auth, u.mailformat, u.username, u.firstname, u.lastname, ' .
            'u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.emailstop',
            null, false
        );
    }
}
