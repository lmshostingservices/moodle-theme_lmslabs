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
 * Quick start checklist for Site Owners. Each step ticks itself off from the site's real state.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /** User preference: the owner hid the sidebar card. */
    const PREF_HIDDEN = 'theme_lmslabs_setuphidden';

    /**
     * Can this user see the guide?
     *
     * @return bool
     */
    public static function applies(): bool {
        return isloggedin() && !isguestuser() &&
            has_capability('theme/lmslabs:managebrand', \context_system::instance());
    }

    /**
     * The steps, in order.
     *
     * @return array
     */
    public static function steps(): array {
        global $DB, $CFG, $SITE;
        $settings = function (string $anchor): string {
            return (new moodle_url('/admin/settings.php', ['section' => 'themesettinglmslabs'], 'admin-' . $anchor))->out(false);
        };
        $people = $DB->count_records_select(
            'user', 'deleted = 0 AND id <> :guest AND username <> :admin',
            ['guest' => $CFG->siteguest, 'admin' => 'admin']
        );
        $category = \core_course_category::get_default();

        $steps = [
            ['key' => 'logo', 'icon' => 'image', 'url' => $settings('logo'),
                'done' => !empty(get_config('theme_lmslabs', 'logo'))],
            ['key' => 'site', 'icon' => 'panel-top', 'url' => $settings('summary'),
                'done' => trim(strip_tags((string)$SITE->summary)) !== ''],
            ['key' => 'contact', 'icon' => 'life-buoy', 'url' => $settings('contactemail'),
                'done' => trim((string)get_config('theme_lmslabs', 'contactemail')) !== ''],
            ['key' => 'email', 'icon' => 'mail', 'url' => $settings('smtphosts'),
                'done' => trim((string)($CFG->smtphosts ?? '')) !== ''],
            ['key' => 'people', 'icon' => 'user-plus', 'url' => (new moodle_url('/admin/tool/uploaduser/index.php'))->out(false),
                'done' => $people > 1],
            ['key' => 'course', 'icon' => 'square-plus',
                'url' => (new moodle_url('/course/edit.php', ['category' => $category ? $category->id : 1]))->out(false),
                'done' => $DB->count_records_select('course', 'id <> ?', [SITEID]) > 0],
            ['key' => 'program', 'icon' => 'layers', 'url' => (new moodle_url('/theme/lmslabs/programs.php'))->out(false),
                'done' => $DB->record_exists('theme_lmslabs_program', [])],
        ];
        foreach ($steps as $i => &$step) {
            $step['number'] = $i + 1;
            $step['title'] = get_string('setup_' . $step['key'], 'theme_lmslabs');
            $step['desc'] = get_string('setup_' . $step['key'] . '_desc', 'theme_lmslabs');
            $step['cta'] = get_string('setup_' . $step['key'] . '_cta', 'theme_lmslabs');
            $step['ico'] = icons::svg($step['icon']);
            $step['done'] = (bool)$step['done'];
        }
        return $steps;
    }

    /**
     * Progress summary.
     *
     * @param array|null $steps
     * @return array ['done' => int, 'total' => int, 'percent' => int, 'complete' => bool]
     */
    public static function progress(?array $steps = null): array {
        $steps = $steps ?? self::steps();
        $done = count(
            array_filter(
            $steps, function ($s) {
            return $s['done'];
        }
        )
        );
        $total = count($steps);
        return ['done' => $done, 'total' => $total, 'percent' => (int)round($done / max(1, $total) * 100),
            'complete' => $done === $total];
    }

    /**
     * Sidebar card data, or null when it should not show.
     *
     * @return array|null
     */
    public static function card(): ?array {
        if (!self::applies() || get_user_preferences(self::PREF_HIDDEN, 0)) {
            return null;
        }
        $progress = self::progress();
        if ($progress['complete']) {
            return null;
        }
        return $progress + [
            'url' => (new moodle_url('/theme/lmslabs/getstarted.php'))->out(false),
            'hideurl' => (new moodle_url('/theme/lmslabs/getstarted.php', ['hide' => 1, 'sesskey' => sesskey()]))->out(false),
        ];
    }
}
