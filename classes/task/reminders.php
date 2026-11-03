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

namespace theme_lmslabs\task;

/**
 * Emails people who switched on calendar reminders about what's due in the next 24 hours.
 *
 * Runs hourly; each event is reminded once per person.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminders extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_reminders', 'theme_lmslabs');
    }

    /**
     * Send the reminders.
     */
    public function execute() {
        global $DB;
        $userids = $DB->get_fieldset_select('user_preferences', 'userid', "name = 'theme_lmslabs_reminders' AND value = '1'");
        $now = time();
        foreach ($userids as $userid) {
            $user = \core_user::get_user($userid);
            if (!$user || $user->deleted || $user->suspended) {
                continue;
            }
            \core\cron::setup_user($user);
            $sent = json_decode((string)get_user_preferences('theme_lmslabs_remsent', '{}', $user), true) ?: [];
            // Forget reminders for events that are long past.
            $sent = array_filter(
                $sent, function ($ts) use ($now) {
                return $ts > $now - 2 * DAYSECS;
            }
            );
            $due = [];
            foreach (\theme_lmslabs\local\calendar::events($now, $now + DAYSECS, $user->id) as $e) {
                if ($e['kind'] !== 'due' || $e['done'] || isset($sent[$e['id']])) {
                    continue;
                }
                $due[] = $e;
                $sent[$e['id']] = $e['start'];
            }
            if ($due) {
                $list = implode(
                    "\n", array_map(function ($e) use ($user) {
                    return '- ' . $e['name'] . ($e['course'] ? ' (' . $e['course'] . ')' : '') . ', ' .
                        userdate($e['start'], get_string('strftimedaydatetime', 'langconfig'), $user->timezone) .
                        ($e['url'] ? "\n  " . $e['url'] : '');
                }, $due)
                );
                $message = new \core\message\message();
                $message->component = 'moodle';
                $message->name = 'instantmessage';
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = $user;
                $message->subject = get_string(
                    'cal_reminder_subject', 'theme_lmslabs', $due[0]['name'] .
                    (count($due) > 1 ? ' +' . (count($due) - 1) : '')
                );
                $message->fullmessage = get_string(
                    'cal_reminder_body', 'theme_lmslabs', (object)[
                    'name' => $user->firstname, 'list' => $list,
                    'url' => (new \moodle_url('/theme/lmslabs/calendar.php'))->out(false),
                ]
                );
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = nl2br(s($message->fullmessage));
                $message->smallmessage = $message->subject;
                $message->notification = 1;
                $message->contexturl = (new \moodle_url('/theme/lmslabs/calendar.php'))->out(false);
                $message->contexturlname = get_string('calendar', 'calendar');
                message_send($message);
                set_user_preference('theme_lmslabs_remsent', json_encode($sent), $user);
            }
        }
        \core\cron::setup_user();
    }
}
