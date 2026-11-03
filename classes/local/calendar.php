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
 * Calendar events for the current user, shaped for the Premium SaaS calendar.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar {
    /**
     * The user's courses, each with a stable colour.
     *
     * @param int|null $userid
     * @return array courseid => ['id', 'name', 'colour']
     */
    public static function courses(?int $userid = null): array {
        global $USER;
        $out = [];
        $i = 0;
        foreach (enrol_get_all_users_courses($userid ?? $USER->id, true, 'id, fullname, shortname', 'fullname ASC') as $c) {
            $out[$c->id] = ['id' => (int)$c->id, 'name' => format_string($c->fullname), 'colour' => $i++ % 8];
        }
        return $out;
    }

    /**
     * Events between two timestamps.
     *
     * @param int $from
     * @param int $to
     * @param int|null $userid defaults to the current user
     * @return array
     */
    public static function events(int $from, int $to, ?int $userid = null): array {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $user = $userid ? \core_user::get_user($userid) : $USER;
        $courses = self::courses($user->id);
        $courseobjs = [];
        foreach (array_keys($courses) as $id) {
            $courseobjs[$id] = get_course($id);
        }
        $courseobjs[SITEID] = get_site();
        [$c, $g, $u] = calendar_set_filters($courseobjs, false, $user);
        $events = calendar_get_legacy_events($from, $to, $u, $g, $c, true, true);
        $out = [];
        $now = time();
        foreach ($events as $e) {
            $cm = null;
            $course = $courses[$e->courseid] ?? null;
            $url = null;
            $icon = null;
            if (!empty($e->modulename) && !empty($e->instance)) {
                $cm = get_coursemodule_from_instance($e->modulename, $e->instance, $e->courseid, false, IGNORE_MISSING);
                if ($cm) {
                    $url = (new moodle_url('/mod/' . $e->modulename . '/view.php', ['id' => $cm->id]))->out(false);
                }
                $icon = $e->modulename;
            } else if ($course) {
                $url = (new moodle_url('/course/view.php', ['id' => $e->courseid]))->out(false);
            }
            $isdue = in_array($e->eventtype, ['due', 'close', 'expectcompletionon', 'gradingdue']) ||
                (!empty($e->modulename) && $e->eventtype !== 'open');
            $kind = $e->eventtype === 'user' ? 'personal' : ($e->eventtype === 'site' ? 'site' :
                ($isdue ? 'due' : 'course'));
            $done = false;
            if ($isdue && !empty($e->modulename) && !empty($cm)) {
                $done = (bool)$DB->get_field(
                    'course_modules_completion', 'completionstate',
                    ['coursemoduleid' => $cm->id, 'userid' => $user->id]
                );
            }
            $out[] = [
                'id' => (int)$e->id,
                'name' => format_string($e->name, true, ['escape' => false]),
                'description' => trim(html_to_text(format_text($e->description, $e->format), 300, false)),
                'start' => (int)$e->timestart,
                'duration' => (int)$e->timeduration,
                'kind' => $kind,
                'module' => $icon,
                'course' => $course ? html_entity_decode($course['name'], ENT_QUOTES) : ($e->eventtype === 'site' ?
                    format_string(get_site()->fullname, true, ['escape' => false]) : ''),
                'courseid' => (int)$e->courseid,
                'colour' => $course ? $course['colour'] : ($kind === 'personal' ? 8 : 9),
                'url' => $url,
                'overdue' => $isdue && !$done && $e->timestart < $now,
                'done' => $done,
                'mine' => $e->eventtype === 'user' && $e->userid == $user->id,
            ];
        }
        usort(
            $out, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        }
        );
        return $out;
    }

    /**
     * Calendar feed links for "Subscribe".
     *
     * @return array
     */
    public static function feeds(): array {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $ics = new moodle_url(
            '/calendar/export_execute.php', [
            'userid' => $USER->id,
            'authtoken' => calendar_get_export_token($USER),
            'preset_what' => 'all',
            'preset_time' => 'recentupcoming',
        ]
        );
        $url = $ics->out(false);
        $webcal = preg_replace('#^https?://#', 'webcal://', $url);
        return [
            'ics' => $url,
            'google' => 'https://calendar.google.com/calendar/render?cid=' . rawurlencode($webcal),
            'outlook' => 'https://outlook.live.com/calendar/0/addfromweb?url=' . rawurlencode($url) . '&name=' .
                rawurlencode(format_string(get_site()->fullname)),
            'apple' => $webcal,
        ];
    }
}
