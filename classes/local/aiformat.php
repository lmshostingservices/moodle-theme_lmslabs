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
 * Every course uses the LMS Labs AI course format, with its player course index switched on.
 *
 * Runs on install/upgrade, when the setting is switched on, when an admin logs in after the format was
 * installed later, and for each new course (created, uploaded or restored).
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiformat {
    /** The format component name. */
    const FORMAT = 'aicourse';

    /**
     * Whether the theme should put every course on the AI course format.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('theme_lmslabs', 'useaiformat');
        return ($value === false || (int)$value === 1) && self::installed();
    }

    /**
     * Whether format_aicourse is installed and enabled.
     *
     * @return bool
     */
    public static function installed(): bool {
        $formats = \core_plugin_manager::instance()->get_enabled_plugins('format');
        return isset($formats[self::FORMAT]) || (bool)\core_component::get_component_directory('format_' . self::FORMAT)
            && !get_config('format_' . self::FORMAT, 'disabled');
    }

    /**
     * Make the AI course format the default and move every existing course onto it.
     *
     * @return int number of courses moved
     */
    public static function apply(): int {
        global $DB, $CFG;
        if (!self::enabled()) {
            return 0;
        }
        set_config('format', self::FORMAT, 'moodlecourse');
        // The player course index ("fancy" index with progress) is on by default, and courses that were
        // explicitly set to the plain index follow the site default from now on.
        set_config('defaultplayerindex', 1, 'format_' . self::FORMAT);
        set_config('forceplayerindex', 1, 'format_' . self::FORMAT);
        $moved = 0;
        $ids = $DB->get_fieldset_select('course', 'id', 'id <> :site AND format <> :f', ['site' => SITEID, 'f' => self::FORMAT]);
        foreach ($ids as $id) {
            $moved += self::convert((int)$id) ? 1 : 0;
        }
        $DB->set_field_select(
            'course_format_options', 'value', -1, "format = :f AND name = 'playerindex' AND value = '0'",
            ['f' => self::FORMAT]
        );
        if ($moved || $ids) {
            require_once($CFG->dirroot . '/course/lib.php');
            rebuild_course_cache(0, true);
        }
        set_config('aiformatapplied', 1, 'theme_lmslabs');
        return $moved;
    }

    /**
     * Move one course onto the AI course format.
     *
     * @param int $courseid
     * @return bool true when it changed
     */
    public static function convert(int $courseid): bool {
        global $DB, $CFG;
        if ($courseid == SITEID || !self::enabled()) {
            return false;
        }
        $format = $DB->get_field('course', 'format', ['id' => $courseid]);
        if ($format === false || $format === self::FORMAT) {
            return false;
        }
        $DB->set_field('course', 'format', self::FORMAT, ['id' => $courseid]);
        require_once($CFG->dirroot . '/course/lib.php');
        rebuild_course_cache($courseid, true);
        return true;
    }

    /**
     * Run once after the format turns up on a site that already had the theme.
     */
    public static function apply_if_pending(): void {
        if (!get_config('theme_lmslabs', 'aiformatapplied') && self::enabled()) {
            self::apply();
        }
    }

    /**
     * Setting callback.
     */
    public static function setting_updated(): void {
        set_config('aiformatapplied', 0, 'theme_lmslabs');
        self::apply();
    }
}
