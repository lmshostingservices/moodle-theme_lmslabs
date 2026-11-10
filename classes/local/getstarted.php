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

use context_system;
use moodle_url;

/**
 * Get started: the Site Owner's self-ticking quick start checklist and its sidebar card.
 *
 * Named getstarted (not setup) on purpose: some web servers and packaging rules block or strip
 * any file whose name ends in setup.php.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class getstarted {
    /** User preference set when the person hides the sidebar card. */
    const PREF_HIDDEN = 'theme_lmslabs_setuphidden';

    /** Capability needed to see the checklist and the card. */
    const CAP = 'theme/lmslabs:managebrand';

    /** @var array|null Steps worked out once per request. */
    protected static $cache = null;

    /**
     * The checklist steps this person can act on, in order, each with its done state.
     *
     * @return array List of steps: key, number, title, desc, cta, url, ico, done, isnext.
     */
    public static function steps(): array {
        global $CFG, $DB;

        if (self::$cache !== null) {
            return self::$cache;
        }

        $system = context_system::instance();
        $site = get_site();
        $appearance = '/theme/lmslabs/appearance.php';

        // Each definition: key, icon, page, anchor, capability, done check.
        $defs = [
            ['site', 'globe', $appearance, 'admin-fullname', self::CAP, function () use ($site) {
                return trim((string)$site->fullname) !== '' && trim(strip_tags((string)$site->summary)) !== '';
            }],
            ['logo', 'image', $appearance, 'admin-logo', self::CAP, function () {
                return trim((string)get_config('theme_lmslabs', 'logo')) !== '';
            }],
            ['contact', 'phone', $appearance, 'admin-contactemail', self::CAP, function () {
                return trim((string)get_config('theme_lmslabs', 'contactemail')) !== ''
                    || trim((string)get_config('theme_lmslabs', 'contactphone')) !== '';
            }],
            ['email', 'mail', $appearance, 'admin-smtphosts', self::CAP, function () use ($CFG) {
                return trim((string)($CFG->smtphosts ?? '')) !== '';
            }],
            ['people', 'users', '/theme/lmslabs/uploadusers.php', '', 'moodle/site:uploadusers', function () use ($DB, $CFG) {
                // Anyone other than the guest account and the site administrators.
                $exclude = array_filter(array_map('intval', explode(',', (string)($CFG->siteadmins ?? ''))));
                $exclude[] = (int)$CFG->siteguest;
                [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);
                return $DB->record_exists_select('user', "deleted = 0 AND id $notin", $params);
            }],
            ['course', 'book-open', '/theme/lmslabs/addcourse.php', '', 'moodle/course:create', function () use ($DB) {
                return $DB->record_exists_select('course', 'id <> :siteid', ['siteid' => SITEID]);
            }],
            ['program', 'route', '/theme/lmslabs/program.php', '', 'theme/lmslabs:manageprograms', function () use ($DB) {
                return $DB->record_exists('theme_lmslabs_program', []);
            }],
        ];

        $steps = [];
        foreach ($defs as [$key, $ico, $path, $anchor, $cap, $check]) {
            if (!get_capability_info($cap) || !has_capability($cap, $system)) {
                continue;
            }
            try {
                $done = (bool)$check();
            } catch (\Throwable $e) {
                $done = false;
            }
            $url = new moodle_url($path);
            if ($anchor !== '') {
                $url->set_anchor($anchor);
            }
            $steps[] = [
                'key' => $key,
                'number' => count($steps) + 1,
                'title' => get_string('setup_' . $key, 'theme_lmslabs'),
                'desc' => get_string('setup_' . $key . '_desc', 'theme_lmslabs'),
                'cta' => get_string('setup_' . $key . '_cta', 'theme_lmslabs'),
                'url' => $url->out(false),
                'ico' => icons::svg($ico),
                'done' => $done,
                'isnext' => false,
            ];
        }

        self::$cache = $steps;
        return $steps;
    }

    /**
     * Progress across a set of steps.
     *
     * @param array|null $steps Steps from steps(), or null to work them out.
     * @return array done, total, percent (0 to 100), complete.
     */
    public static function progress(?array $steps = null): array {
        $steps = $steps ?? self::steps();
        $total = count($steps);
        $done = count(array_filter($steps, function ($step) {
            return !empty($step['done']);
        }));
        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total ? (int)round($done / $total * 100) : 100,
            'complete' => $total > 0 && $done >= $total,
        ];
    }

    /**
     * Data for the Get started card at the top of the sidebar, or false when it should not show.
     *
     * Hidden for guests, for people without the brand capability, once every step is done,
     * and when the person has closed it. Never throws, so it cannot break page rendering.
     *
     * @return array|false
     */
    public static function card() {
        try {
            if (during_initial_install() || !isloggedin() || isguestuser()) {
                return false;
            }
            if (!get_capability_info(self::CAP) || !has_capability(self::CAP, context_system::instance())) {
                return false;
            }
            if (get_user_preferences(self::PREF_HIDDEN, 0)) {
                return false;
            }
            $steps = self::steps();
            $progress = self::progress($steps);
            if (!$progress['total'] || $progress['complete']) {
                return false;
            }
            return $progress + [
                'url' => (new moodle_url('/theme/lmslabs/getstarted.php'))->out(false),
                'hideurl' => (new moodle_url('/theme/lmslabs/getstarted.php', ['hide' => 1, 'sesskey' => sesskey()]))->out(false),
            ];
        } catch (\Throwable $e) {
            debugging('theme_lmslabs Get started card: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Forget the steps worked out for this request (after a setting changes, and in tests).
     */
    public static function reset_cache(): void {
        self::$cache = null;
    }
}
