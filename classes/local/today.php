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
 * The "Today" panel at the top of the Dashboard: carry on, what's due, what needs you.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class today {
    /**
     * Panel data for the current user.
     *
     * @return array|null
     */
    public static function export(): ?array {
        global $USER, $CFG, $DB;
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        // Kept for a minute per person: the panel runs progress, marking and calendar queries.
        $cache = \cache::make('theme_lmslabs', 'today');
        $key = (int)$USER->id . '_' . current_language() . '_' . (int)$GLOBALS['PAGE']->user_is_editing() .
            '_' . (empty($USER->access['rsw']) ? 0 : 1);
        $hit = $cache->get($key);
        if (is_array($hit)) {
            return $hit;
        }
        $hour = (int)userdate(time(), '%H');
        $greet = $hour < 12 ? 'td_morning' : ($hour < 18 ? 'td_afternoon' : 'td_evening');
        $tiles = [];

        // Carry on where you left off.
        $resume = null;
        try {
            $resume = programs::export_for_user($USER->id)['resume'] ?? null;
        } catch (\Throwable $e) {
            $resume = null;
        }

        // Due in the next 7 days (and overdue).
        $due = [];
        foreach (calendar::events(time() - 7 * DAYSECS, time() + 7 * DAYSECS) as $e) {
            if ($e['kind'] !== 'due' || $e['done'] || (!$e['overdue'] && $e['start'] < time())) {
                continue;
            }
            $due[] = [
                'name' => $e['name'],
                'course' => $e['course'],
                'url' => $e['url'],
                'when' => userdate($e['start'], get_string('strftimedaydatetime', 'langconfig')),
                'overdue' => $e['overdue'],
                'colour' => $e['colour'],
            ];
        }
        $due = array_slice($due, 0, 4);

        // Unread messages.
        $unread = 0;
        if (!empty($CFG->messaging)) {
            try {
                $unread = \core_message\api::count_unread_conversations($USER);
            } catch (\Throwable $e) {
                $unread = 0;
            }
        }
        $tiles[] = ['icon' => icons::svg('message-square'), 'value' => $unread, 'label' => get_string('td_unread', 'theme_lmslabs'),
            'url' => (new moodle_url('/theme/lmslabs/messages.php'))->out(false), 'hot' => $unread > 0];

        // Teachers: submissions waiting.
        $tomark = 0;
        $teaching = 0;
        foreach (enrol_get_all_users_courses($USER->id, true, 'id') as $c) {
            $ctx = \context_course::instance($c->id);
            if (has_any_capability(['mod/assign:grade', 'mod/quiz:grade'], $ctx)) {
                $teaching++;
                $tomark += grading::count_waiting((int)$c->id);
            }
        }
        if ($teaching) {
            $tiles[] = ['icon' => icons::svg('check-check'), 'value' => $tomark, 'label' => get_string('td_tomark', 'theme_lmslabs'),
                'url' => (new moodle_url('/theme/lmslabs/mygrades.php'))->out(false), 'hot' => $tomark > 0];
        }

        // Managers: who's around.
        $sys = \context_system::instance();
        if (has_capability('moodle/site:viewreports', $sys) || has_capability('theme/lmslabs:managebrand', $sys)) {
            $active = $DB->count_records_select('user', 'deleted = 0 AND lastaccess > ?', [time() - DAYSECS]);
            $tiles[] = ['icon' => icons::svg('users'), 'value' => $active, 'label' => get_string('td_active', 'theme_lmslabs'),
                'url' => (new moodle_url('/theme/lmslabs/people.php', ['quick' => 'recent']))->out(false), 'hot' => false];
            $new = $DB->count_records_select('user', 'deleted = 0 AND timecreated > ?', [time() - 7 * DAYSECS]);
            $tiles[] = ['icon' => icons::svg('user-plus'), 'value' => $new, 'label' => get_string('td_new', 'theme_lmslabs'),
                'url' => (new moodle_url('/theme/lmslabs/people.php', ['sort' => 'newest']))->out(false), 'hot' => false];
        }

        $data = [
            'greeting' => get_string($greet, 'theme_lmslabs', s($USER->firstname)),
            'date' => userdate(time(), get_string('strftimedaydate', 'langconfig')),
            'resume' => $resume,
            'due' => $due,
            'hasdue' => (bool)$due,
            'tiles' => $tiles,
            'calendarurl' => (new moodle_url('/theme/lmslabs/calendar.php'))->out(false),
            'mycoursesurl' => (new moodle_url('/theme/lmslabs/courses.php'))->out(false),
            'i' => ['play' => icons::svg('play'), 'calendar' => icons::svg('calendar-days'), 'arrow' => icons::svg('arrow-up-right'),
                'check' => icons::svg('circle-check')],
        ];
        $cache->set($key, $data);
        return $data;
    }
}
