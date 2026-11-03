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
 * Which Moodle screens have a Premium SaaS version, and where it lives.
 *
 * Plain GET visits to a Moodle screen open the Premium SaaS page instead. Adding ?classic=1 keeps Moodle's own
 * screen, and that screen then shows a button back to the Premium SaaS page.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class router {
    /**
     * The Premium SaaS page for a Moodle URL, or null when there isn't one.
     *
     * @param string $path URL path such as /admin/user.php
     * @param array $get query parameters (classic is ignored)
     * @return moodle_url|null
     */
    public static function premium(string $path, array $get): ?moodle_url {
        global $DB, $CFG;
        unset($get['classic']);
        $keys = array_keys($get);
        $only = function (array $allowed) use ($keys) {
            return !array_diff($keys, $allowed);
        };
        $int = function (string $key) use ($get) {
            return isset($get[$key]) ? (int)$get[$key] : 0;
        };
        $url = null;
        switch ($path) {
            case '/admin/user.php':
                $url = $only([]) ? new moodle_url('/theme/lmslabs/people.php') : null;
                break;
            case '/admin/user/user_bulk.php':
                $url = $only([]) ? new moodle_url('/theme/lmslabs/bulk.php') : null;
                break;
            case '/user/editadvanced.php':
                $url = $only(['id', 'returnto']) && $int('id') === -1 ? new moodle_url('/theme/lmslabs/adduser.php') : null;
                break;
            case '/cohort/index.php':
                $url = $only(['contextid']) && (!$int('contextid') || $int('contextid') == SYSCONTEXTID)
                    ? new moodle_url('/theme/lmslabs/cohorts.php') : null;
                break;
            case '/cohort/assign.php':
                if ($int('id') && $only(['id', 'returnurl']) && !$DB->get_field('cohort', 'component', ['id' => $int('id')])) {
                    $url = new moodle_url('/theme/lmslabs/members.php', ['type' => 'cohort', 'id' => $int('id')]);
                }
                break;
            case '/course/management.php':
                $url = $only(['categoryid']) ? new moodle_url('/theme/lmslabs/manage.php') : null;
                break;
            case '/course/edit.php':
                if ($int('id') && $only(['id', 'returnto', 'returnurl'])) {
                    $url = new moodle_url('/theme/lmslabs/addcourse.php', ['id' => $int('id')]);
                } else if ($only(['category', 'returnto']) && $int('category')) {
                    $url = new moodle_url('/theme/lmslabs/addcourse.php', ['category' => $int('category')]);
                }
                break;
            case '/user/index.php':
                $url = $int('id') && $int('id') != SITEID && $only(['id'])
                    ? new moodle_url('/theme/lmslabs/participants.php', ['id' => $int('id')]) : null;
                break;
            case '/report/view.php':
                $url = $int('courseid') && $int('courseid') != SITEID && $only(['courseid'])
                    ? new moodle_url('/theme/lmslabs/reports.php', ['id' => $int('courseid')]) : null;
                break;
            case '/enrol/instances.php':
                $url = $int('id') && $only(['id']) ? new moodle_url('/theme/lmslabs/enrolmethods.php', ['id' => $int('id')]) : null;
                break;
            case '/course/completion.php':
                $url = $int('id') && $only(['id']) ? new moodle_url('/theme/lmslabs/completion.php', ['id' => $int('id')]) : null;
                break;
            case '/backup/backup.php':
                $url = $int('id') && $only(['id']) ? new moodle_url('/theme/lmslabs/backup.php', ['id' => $int('id')]) : null;
                break;
            case '/backup/restorefile.php':
                $cid = $int('contextid');
                $c = $cid ? \context::instance_by_id($cid, IGNORE_MISSING) : null;
                if ($c && $only(['contextid']) && $c->contextlevel == CONTEXT_COURSE) {
                    $url = new moodle_url('/theme/lmslabs/restore.php', ['id' => $c->instanceid]);
                } else if ($c && $only(['contextid']) && $c->contextlevel == CONTEXT_SYSTEM) {
                    $url = new moodle_url('/theme/lmslabs/restore.php');
                }
                break;
            case '/backup/import.php':
                $url = $int('id') && $only(['id']) ? new moodle_url('/theme/lmslabs/import.php', ['id' => $int('id')]) : null;
                break;
            case '/admin/tool/uploaduser/index.php':
                $url = $only([]) ? new moodle_url('/theme/lmslabs/uploadusers.php') : null;
                break;
            case '/admin/tool/uploadcourse/index.php':
                $url = $only([]) ? new moodle_url('/theme/lmslabs/uploadcourses.php') : null;
                break;
            case '/admin/settings.php':
                $section = $get['section'] ?? '';
                $url = $only(['section']) && $section === 'themesettinglmslabs'
                    ? new moodle_url('/theme/lmslabs/appearance.php')
                    : ($only(['section']) && $section === 'themesettinglmslabshosting'
                        ? new moodle_url('/theme/lmslabs/hosting.php') : null);
                break;
            case '/course/reset.php':
                $url = $int('id') && $only(['id']) ? new moodle_url('/theme/lmslabs/reset.php', ['id' => $int('id')]) : null;
                break;
            case '/group/index.php':
                $url = $int('id') ? new moodle_url('/theme/lmslabs/groups.php', ['id' => $int('id')]) : null;
                break;
            case '/group/members.php':
                $url = $int('group') ? new moodle_url('/theme/lmslabs/members.php', ['type' => 'group', 'id' => $int('group')])
                    : null;
                break;
            case '/calendar/view.php':
                $url = $only(['view', 'course', 'time']) && (!$int('course') || $int('course') == SITEID)
                    ? new moodle_url('/theme/lmslabs/calendar.php') : null;
                break;
            case '/grade/report/grader/index.php':
                $url = $only(['id']) && $int('id') && $int('id') != SITEID
                    ? new moodle_url('/theme/lmslabs/gradebook.php', ['id' => $int('id')]) : null;
                break;
            case '/grade/report/overview/index.php':
                $url = $only(['id']) && (!$int('id') || $int('id') == SITEID)
                    ? new moodle_url('/theme/lmslabs/mygrades.php') : null;
                break;
            case '/message/index.php':
                $url = $only(['id', 'convid']) ? new moodle_url(
                    '/theme/lmslabs/messages.php',
                    array_filter(['id' => $int('id'), 'convid' => $int('convid')])
                ) : null;
                break;
            case '/user/preferences.php':
                global $USER;
                $url = $only(['userid']) && (!$int('userid') || $int('userid') == $USER->id)
                    ? new moodle_url('/theme/lmslabs/prefs.php') : null;
                break;
            case '/message/output/popup/notifications.php':
                $url = new moodle_url('/theme/lmslabs/notifications.php');
                break;
            case '/message/notificationpreferences.php':
                global $USER;
                $url = $only(['userid']) && (!$int('userid') || $int('userid') == $USER->id)
                    ? new moodle_url('/theme/lmslabs/notifprefs.php') : null;
                break;
            case '/user/profile.php':
                $url = $only(['id']) ? new moodle_url('/theme/lmslabs/profile.php', $int('id') ? ['id' => $int('id')] : [])
                    : null;
                break;
            case '/mod/assign/overrides.php':
            case '/mod/quiz/overrides.php':
                $url = $int('cmid') && $only(['cmid', 'mode'])
                    ? new moodle_url('/theme/lmslabs/overrides.php', ['cmid' => $int('cmid')]) : null;
                break;
            case '/question/banks.php':
            case '/question/edit.php':
                $url = $int('courseid') && $only(['courseid']) ? new moodle_url(
                    '/theme/lmslabs/questionbank.php',
                    ['id' => $int('courseid')]
                ) : null;
                break;
            case '/badges/index.php':
                $url = $int('id') && ($get['type'] ?? '') == 2 && $only(['type', 'id'])
                    ? new moodle_url('/theme/lmslabs/coursebadges.php', ['id' => $int('id')]) : null;
                break;
            case '/badges/mybadges.php':
                $url = $only([]) ? new moodle_url('/theme/lmslabs/mybadges.php') : null;
                break;
        }
        if ($url && !file_exists($CFG->dirroot . $url->get_path(false))) {
            return null;
        }
        return $url;
    }

    /**
     * Moodle's own screen for a Premium SaaS page (with classic=1 so it isn't redirected back).
     *
     * @param string $path Moodle path
     * @param array $params
     * @return string
     */
    public static function classic(string $path, array $params = []): string {
        return (new moodle_url($path, $params + ['classic' => 1]))->out(false);
    }
}
