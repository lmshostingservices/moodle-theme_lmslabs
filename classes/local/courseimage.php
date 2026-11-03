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

use context_course;
use moodle_url;

/**
 * The picture on a course card.
 *
 * The course image when one is set; otherwise the AI course format's image for the first section (most
 * courses already have those, generated with one click), so no course card is ever blank.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseimage {
    /** @var array courseid => url|null */
    protected static $cache = [];

    /**
     * Image URL for a course card, or null when there's nothing to show.
     *
     * @param \stdClass $course needs id
     * @return string|null
     */
    public static function url(\stdClass $course): ?string {
        if (array_key_exists($course->id, self::$cache)) {
            return self::$cache[$course->id];
        }
        $url = self::own($course) ?? self::from_first_section($course);
        return self::$cache[$course->id] = $url;
    }

    /**
     * Whether the image comes from the course itself (not borrowed from a section).
     *
     * @param \stdClass $course
     * @return bool
     */
    public static function has_own(\stdClass $course): bool {
        return self::own($course) !== null;
    }

    /**
     * The course's own image.
     *
     * @param \stdClass $course
     * @return string|null
     */
    protected static function own(\stdClass $course): ?string {
        $ctx = context_course::instance($course->id);
        foreach (get_file_storage()->get_area_files($ctx->id, 'course', 'overviewfiles', 0, 'filename', false) as $file) {
            if ($file->is_valid_image()) {
                return moodle_url::make_pluginfile_url(
                    $ctx->id, 'course', 'overviewfiles', null, $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }
        return null;
    }

    /**
     * The AI course format's card image for the first section that has one.
     *
     * @param \stdClass $course
     * @return string|null
     */
    protected static function from_first_section(\stdClass $course): ?string {
        global $DB;
        if (!\core_component::get_component_directory('format_aicourse')) {
            return null;
        }
        $ctx = context_course::instance($course->id);
        $files = get_file_storage()->get_area_files(
            $ctx->id, 'format_aicourse', 'sectioncardimage', false,
            'itemid, timemodified DESC', false
        );
        if (!$files) {
            return null;
        }
        $bysection = [];
        foreach ($files as $file) {
            if ($file->is_valid_image() && !isset($bysection[$file->get_itemid()])) {
                $bysection[$file->get_itemid()] = $file;
            }
        }
        if (!$bysection) {
            return null;
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($bysection));
        $params[] = $course->id;
        $sections = $DB->get_records_select_menu(
            'course_sections', "id $in AND course = ?", $params, 'section ASC',
            'id, section'
        );
        // First real section; the general section only when it's the only one with a picture.
        $pick = null;
        foreach ($sections as $id => $num) {
            if ($num > 0) {
                $pick = $id;
                break;
            }
        }
        $pick = $pick ?? array_key_first($sections);
        if (!$pick) {
            return null;
        }
        $file = $bysection[$pick];
        return moodle_url::make_pluginfile_url(
            $ctx->id, 'format_aicourse', 'sectioncardimage', $file->get_itemid(),
            $file->get_filepath(), $file->get_filename()
        )->out(false);
    }

    /**
     * Replace the course image with an uploaded file.
     *
     * @param int $courseid
     * @param string $path temporary file
     * @param string $name original file name
     * @return string new URL
     */
    public static function store(int $courseid, string $path, string $name): string {
        $ctx = context_course::instance($courseid);
        $fs = get_file_storage();
        $fs->delete_area_files($ctx->id, 'course', 'overviewfiles');
        $fs->create_file_from_pathname(['contextid' => $ctx->id, 'component' => 'course', 'filearea' => 'overviewfiles',
            'itemid' => 0, 'filepath' => '/', 'filename' => clean_filename($name)], $path);
        \cache_helper::purge_by_event('changesincourse');
        unset(self::$cache[$courseid]);
        return self::url((object)['id' => $courseid]);
    }
}
