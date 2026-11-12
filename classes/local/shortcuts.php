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

use moodle_page;
use moodle_url;

/**
 * Personal shortcut icons in the top bar.
 *
 * Each person pins the pages they use most, with their own icon and tooltip. Only the menu item's key is stored,
 * never a URL: every page load builds the link again from the role-aware menus, so a shortcut only shows when the
 * person can open that page here (a course tool shows inside courses, and disappears in Student view).
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortcuts {
    /** User preference holding the shortcuts as JSON. */
    const PREF = 'theme_lmslabs_shortcuts';

    /** Most shortcuts one person can pin. */
    const MAX = 8;

    /** Longest tooltip, in characters. */
    const TIPMAX = 40;

    /** Icons offered in the picker (all in icons::PATHS). */
    const ICONS = ['layout-dashboard', 'book-open', 'library', 'graduation-cap', 'layers', 'calendar', 'calendar-days',
        'calendar-clock', 'clock', 'history', 'chart-column', 'chart-line', 'chart-pie', 'gauge', 'trending-up', 'target',
        'award', 'trophy', 'medal', 'star', 'bookmark', 'check-check', 'circle-check-big', 'list-checks', 'file-text',
        'file-question', 'notebook', 'folder-open', 'folder-tree', 'message-square', 'mail', 'bell', 'inbox', 'users',
        'users-round', 'user-round', 'user-plus', 'user-check', 'contact-round', 'building-2', 'briefcase', 'route',
        'layout-grid', 'sparkles', 'wand', 'zap', 'lightbulb', 'video', 'play', 'upload', 'download',
        'hard-drive-download', 'archive-restore', 'rotate-ccw', 'settings', 'sliders-horizontal', 'palette',
        'shield-check', 'key-round', 'lock-keyhole', 'server', 'globe', 'life-buoy', 'link'];

    /**
     * Clean a stored list.
     *
     * @param string|null $raw JSON
     * @return array list of ['k' => key, 'i' => icon, 't' => tooltip, 'c' => 1 for a course tool]
     */
    protected static function clean(?string $raw): array {
        $list = $raw ? json_decode($raw, true) : [];
        $out = [];
        foreach (is_array($list) ? $list : [] as $s) {
            if (!is_array($s) || empty($s['k']) || !is_string($s['k'])) {
                continue;
            }
            $out[] = [
                'k' => clean_param($s['k'], PARAM_ALPHANUMEXT),
                'i' => in_array($s['i'] ?? '', self::ICONS, true) ? $s['i'] : 'link',
                't' => clean_param(\core_text::substr((string)($s['t'] ?? ''), 0, self::TIPMAX), PARAM_TEXT),
                'c' => empty($s['c']) ? 0 : 1,
            ];
            if (count($out) >= self::MAX) {
                break;
            }
        }
        return $out;
    }

    /**
     * The person's own shortcuts, or null when they have never made their own set.
     *
     * @param int|null $userid
     * @return array|null
     */
    public static function own(?int $userid = null): ?array {
        global $USER;
        $raw = get_user_preferences(self::PREF, null, $userid ?? $USER->id);
        return $raw === null ? null : self::clean($raw);
    }

    /**
     * The default set a Site Owner made for teachers or students.
     *
     * @param string $who teacher or student
     * @return array
     */
    public static function defaults(string $who): array {
        return self::clean(get_config('theme_lmslabs', 'shortcuts_' . ($who === 'teacher' ? 'teacher' : 'student')) ?: null);
    }

    /**
     * Which default set applies to the signed-in person: teachers (anyone who can edit a course) or students.
     *
     * @return string teacher or student
     */
    public static function audience(): string {
        static $cache = null;
        if ($cache === null) {
            $teacher = has_capability('moodle/course:update', \context_system::instance())
                || (bool)get_user_capability_course('moodle/course:manageactivities', null, false, '', '', 1);
            $cache = $teacher ? 'teacher' : 'student';
        }
        return $cache;
    }

    /**
     * The shortcuts in use: the person's own set, or their role's default when they have not made one.
     *
     * @return array
     */
    public static function saved(): array {
        $own = self::own();
        return $own ?? self::defaults(self::audience());
    }

    /**
     * May this person set the defaults for teachers and students?
     *
     * @return bool
     */
    public static function can_manage(): bool {
        return has_capability('theme/lmslabs:manageshortcuts', \context_system::instance());
    }

    /**
     * Go back to the role default.
     */
    public static function reset(): void {
        unset_user_preference(self::PREF);
    }

    /**
     * Save shortcuts. A new key must be in the person's own menus on the page they saved from; shortcuts already
     * pinned that they cannot open on this page are kept as they were.
     *
     * @param array $keys
     * @param array $icons
     * @param array $tips
     * @param array $catalogue from nav::catalogue() for the page the person is on
     * @param string $target me, teacher or student (the last two set a default and need the manage capability)
     * @return array the saved list
     */
    public static function save(array $keys, array $icons, array $tips, array $catalogue, string $target = 'me'): array {
        $previous = [];
        foreach ($target === 'me' ? self::saved() : self::defaults($target) as $s) {
            $previous[$s['k']] = $s;
        }
        $out = [];
        $seen = [];
        foreach (array_values($keys) as $n => $key) {
            $key = clean_param($key, PARAM_ALPHANUMEXT);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            if (isset($catalogue[$key])) {
                $course = $catalogue[$key]['scope'] === 'c' ? 1 : 0;
                $name = $catalogue[$key]['name'];
            } else if (isset($previous[$key])) {
                // Already pinned but not open to them on this page (a course tool outside a course, or while in
                // Student view): keep it as it was. It shows again wherever they can use it.
                $course = $previous[$key]['c'];
                $name = $previous[$key]['t'];
            } else {
                continue;
            }
            $tip = trim(clean_param((string)($tips[$n] ?? ''), PARAM_TEXT));
            $icon = (string)($icons[$n] ?? '');
            $out[] = [
                'k' => $key,
                'i' => in_array($icon, self::ICONS, true) ? $icon : 'link',
                't' => \core_text::substr($tip !== '' ? $tip : $name, 0, self::TIPMAX),
                'c' => $course,
            ];
            $seen[$key] = true;
            if (count($out) >= self::MAX) {
                break;
            }
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        if ($target === 'me') {
            // An empty list is kept too: it means "no shortcuts", not "use the default".
            set_user_preference(self::PREF, $json);
        } else {
            set_config('shortcuts_' . $target, $out ? $json : '', 'theme_lmslabs');
        }
        return $out;
    }

    /**
     * The shortcuts that open on this page, ready for the top bar.
     *
     * @param moodle_page $page
     * @param array|null $catalogue reuse a catalogue already built for this page
     * @return array
     */
    public static function bar(moodle_page $page, ?array $catalogue = null): array {
        $saved = self::saved();
        if (!$saved) {
            return [];
        }
        $catalogue = $catalogue ?? (new nav($page))->catalogue();
        $out = [];
        foreach ($saved as $s) {
            if (!isset($catalogue[$s['k']])) {
                continue;
            }
            $item = $catalogue[$s['k']];
            $url = new moodle_url($item['url']);
            $active = $url->get_param('sesskey') === null && $page->url->compare($url, URL_MATCH_PARAMS);
            $out[] = [
                'key' => $s['k'],
                'url' => $item['url'],
                'tip' => $s['t'] !== '' ? $s['t'] : $item['name'],
                'icon' => icons::svg($s['i']),
                'active' => $active,
            ];
        }
        return $out;
    }

    /**
     * Everything the picker needs, for the page the person is on.
     *
     * @param moodle_page $page
     * @return array
     */
    public static function picker(moodle_page $page): array {
        $catalogue = (new nav($page))->catalogue();
        $items = [];
        foreach ($catalogue as $item) {
            $items[] = ['k' => $item['key'], 'n' => $item['name'], 'g' => $item['group'], 'i' => $item['icon'],
                's' => icons::svg($item['icon']), 'c' => $item['scope'] === 'c' ? 1 : 0];
        }
        $icons = [];
        foreach (self::ICONS as $name) {
            $icons[$name] = icons::svg($name);
        }
        $mark = function (array $list) use ($catalogue): array {
            foreach ($list as $n => $s) {
                $list[$n]['here'] = isset($catalogue[$s['k']]) ? 1 : 0;
            }
            return $list;
        };
        $saved = $mark(self::saved());
        $audience = self::audience();
        $canmanage = self::can_manage();
        $incourse = $page->course && $page->course->id != SITEID;
        return [
            'items' => $items,
            'icons' => $icons,
            'saved' => $saved,
            'max' => self::MAX,
            'tipmax' => self::TIPMAX,
            'incourse' => $incourse ? 1 : 0,
            'ui' => ['x' => icons::svg('x'), 'plus' => icons::svg('plus')],
            'usingdefault' => self::own() === null && $saved ? 1 : 0,
            'hasdefault' => self::defaults($audience) ? 1 : 0,
            'ownset' => self::own() !== null ? 1 : 0,
            'canmanage' => $canmanage ? 1 : 0,
            'defaults' => $canmanage ? ['teacher' => $mark(self::defaults('teacher')), 'student' => $mark(self::defaults('student'))] : null,
            'bar' => self::bar($page, $catalogue),
        ];
    }
}
