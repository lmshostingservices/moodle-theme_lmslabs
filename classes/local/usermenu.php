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
 * The avatar menu: everything personal in one place, in sections, like any SaaS account menu.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usermenu {
    /**
     * Template context.
     *
     * @param array $core Moodle's own user menu export (for avatar, role-switch notices and extra items)
     * @return array
     */
    public static function export(array $core): array {
        global $USER, $CFG, $PAGE;
        // Moodle mixes arrays and objects in this export; normalise it.
        $core = json_decode(json_encode($core), true) ?: [];
        $item = function (string $key, string $label, string $path, array $params, string $icon, $badge = null) use ($PAGE) {
            $url = new moodle_url($path, $params);
            return [
                'key' => $key,
                'label' => $label,
                'url' => $url->out(false),
                'icon' => icons::svg($icon),
                'badge' => $badge ?: null,
                'active' => $PAGE->url->compare($url, URL_MATCH_BASE),
            ];
        };
        $unread = 0;
        if (!empty($CFG->messaging)) {
            try {
                $unread = \core_message\api::count_unread_conversations($USER);
            } catch (\Throwable $e) {
                $unread = 0;
            }
        }
        $learning = array_values(
            array_filter([
            $item('mycourses', get_string('mycourses'), '/theme/lmslabs/courses.php', [], 'book-open'),
            $item('calendar', get_string('calendar', 'calendar'), '/theme/lmslabs/calendar.php', [], 'calendar'),
            $item('grades', get_string('grades', 'grades'), '/theme/lmslabs/mygrades.php', [], 'chart-column'),
            !empty($CFG->enablebadges) ? $item(
                'badges', get_string('mybadges', 'theme_lmslabs'), '/theme/lmslabs/mybadges.php', [],
                'award'
            ) : null,
        ])
        );
        $inbox = array_values(
            array_filter([
            !empty($CFG->messaging) ? $item(
                'messages', get_string('messages', 'message'), '/theme/lmslabs/messages.php', [],
                'message-square', $unread
            ) : null,
            $item('notifications', get_string('notifications', 'message'), '/theme/lmslabs/notifications.php', [], 'bell'),
        ])
        );
        $account = [
            $item('profile', get_string('profile'), '/theme/lmslabs/profile.php', [], 'user-round'),
            $item('preferences', get_string('preferences'), '/theme/lmslabs/prefs.php', [], 'settings'),
        ];

        // Anything else Moodle or the site admin put in the user menu (switch role, custom links...).
        $ours = ['/user/profile.php', '/grade/report/overview/index.php', '/calendar/view.php', '/user/files.php',
            '/reportbuilder/index.php', '/user/preferences.php', '/login/logout.php', '/my/courses.php', '/badges/mybadges.php',
            '/message/index.php', '/user/index.php', '/user/contentbank.php'];
        $more = [];
        $logout = (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);
        foreach ($core['items'] ?? [] as $i) {
            if (($i['itemtype'] ?? '') !== 'link' || empty($i['link']['url'])) {
                continue;
            }
            $u = new moodle_url($i['link']['url']);
            $path = str_replace((new moodle_url('/'))->get_path(false), '/', $u->get_path(false));
            if ($path === '/login/logout.php') {
                $logout = $u->out(false);
                continue;
            }
            if (in_array($path, $ours, true)) {
                continue;
            }
            $more[] = ['label' => $i['link']['title'], 'url' => $u->out(false), 'icon' => icons::svg('arrow-up-right'),
                'active' => false];
        }
        return [
            'avatar' => $core['avatardata'][0]['content'] ?? '',
            'fullname' => fullname($USER),
            'email' => $USER->email,
            'metadata' => $core['metadata'] ?? [],
            'sections' => array_values(
                array_filter([
                ['title' => get_string('um_learning', 'theme_lmslabs'), 'items' => $learning],
                ['title' => get_string('um_inbox', 'theme_lmslabs'), 'items' => $inbox],
                ['title' => get_string('um_account', 'theme_lmslabs'), 'items' => $account],
                $more ? ['title' => get_string('um_more', 'theme_lmslabs'), 'items' => $more] : null,
            ])
            ),
            'unread' => $unread ?: null,
            'logouturl' => $logout,
            'logouticon' => icons::svg('log-out'),
        ];
    }
}
