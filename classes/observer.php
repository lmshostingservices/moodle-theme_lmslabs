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

namespace theme_lmslabs;

use theme_lmslabs\local\siteowner;

/**
 * Event observers.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** @var array notification ids already forwarded in this request */
    protected static $forwarded = [];

    /**
     * Copy notifications sent to a site administrator on to every Site Owner by email.
     *
     * @param \core\event\notification_sent $event
     */
    public static function notification_sent(\core\event\notification_sent $event): void {
        global $DB;
        if (get_config('theme_lmslabs', 'forwardadminmail') === '0') {
            return;
        }
        $recipientid = (int)$event->relateduserid;
        if (!$recipientid || !is_siteadmin($recipientid)) {
            return;
        }
        $notification = $DB->get_record('notifications', ['id' => $event->objectid]);
        if (!$notification) {
            return;
        }
        // The same notice usually goes to every admin; send each Site Owner one copy.
        $key = sha1($notification->component . '|' . $notification->eventtype . '|' . $notification->subject);
        if (isset(self::$forwarded[$key])) {
            return;
        }
        self::$forwarded[$key] = true;

        $owners = siteowner::users();
        if (!$owners) {
            return;
        }
        $from = \core_user::get_noreply_user();
        $subject = $notification->subject;
        $text = $notification->fullmessage ?: html_to_text((string)$notification->fullmessagehtml);
        $html = $notification->fullmessagehtml ?: '';
        foreach ($owners as $owner) {
            if ($owner->deleted || $owner->suspended || is_siteadmin($owner->id) || $owner->id == $recipientid) {
                continue;
            }
            $full = \core_user::get_user($owner->id);
            if ($full) {
                email_to_user($full, $from, $subject, $text, $html);
            }
        }
    }

    /**
     * Site administrators who chose Boost view keep it from one login to the next.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $SESSION;
        $userid = (int)$event->userid;
        if (is_siteadmin($userid) && get_user_preferences('theme_lmslabs_boostview', 0, $userid)) {
            $SESSION->theme = 'boost';
            $SESSION->theme_lmslabs_boostview = 1;
        }
        if (is_siteadmin($userid)) {
            \theme_lmslabs\local\aiformat::apply_if_pending();
        }
    }

    /**
     * New courses use the AI course format.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event): void {
        \theme_lmslabs\local\aiformat::convert((int)$event->courseid);
    }

    /**
     * Restored courses use the AI course format.
     *
     * @param \core\event\course_restored $event
     */
    public static function course_restored(\core\event\course_restored $event): void {
        \theme_lmslabs\local\aiformat::convert((int)$event->courseid);
    }
}
