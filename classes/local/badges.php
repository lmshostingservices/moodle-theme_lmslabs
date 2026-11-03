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

use badge;
use context_course;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/badgeslib.php');

/**
 * Course badges: list, create in one step, switch on or off, award by hand.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badges {
    /**
     * Badges of a course, ready for the template.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function cards(\stdClass $course): array {
        global $DB;
        $ctx = context_course::instance($course->id);
        $out = [];
        foreach ($DB->get_records('badge', ['type' => BADGE_TYPE_COURSE, 'courseid' => $course->id], 'name ASC') as $r) {
            if ($r->status == BADGE_STATUS_ARCHIVED) {
                continue;
            }
            $b = new badge($r->id);
            $criteria = [];
            foreach ($b->get_criteria() as $c) {
                if ($c->criteriatype != BADGE_CRITERIA_TYPE_OVERALL) {
                    $criteria[] = ['text' => trim(strip_tags($c->get_title()))];
                }
            }
            $manual = $b->has_manual_award_criteria();
            $out[] = [
                'id' => (int)$b->id,
                'name' => format_string($b->name, true, ['context' => $ctx]),
                'description' => trim(html_to_text(format_text($b->description, FORMAT_HTML), 160, false)),
                'image' => moodle_url::make_pluginfile_url($ctx->id, 'badges', 'badgeimage', $b->id, '/', 'f3', false)->out(false),
                'active' => $b->is_active(),
                'locked' => $b->is_locked(),
                'awarded' => count($b->get_awards()),
                'criteria' => $criteria,
                'hascriteria' => (bool)$criteria,
                'manual' => $manual,
                'auto' => $b->has_criteria() && !$manual,
                'classicurl' => (new moodle_url('/badges/overview.php', ['id' => $b->id]))->out(false),
                'editurl' => (new moodle_url('/badges/edit.php', ['id' => $b->id, 'action' => 'details']))->out(false),
            ];
        }
        return $out;
    }

    /**
     * A plain default badge picture in the accent colour, used when none is uploaded.
     *
     * @return string path of a temporary PNG
     */
    public static function default_image(): string {
        $size = 512;
        $im = imagecreatetruecolor($size, $size);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        $hex = ltrim((string)get_config('theme_lmslabs', 'brandcolor') ?: '#2563eb', '#');
        $rgb = array_map('hexdec', str_split(strlen($hex) === 3 ? preg_replace('/./', '$0$0', $hex) : $hex, 2));
        imagefilledellipse($im, 256, 256, 480, 480, imagecolorallocate($im, ...$rgb));
        imagefilledellipse($im, 256, 256, 400, 400, imagecolorallocate($im, 255, 255, 255));
        imagefilledellipse($im, 256, 256, 380, 380, imagecolorallocate($im, ...$rgb));
        // A five point star.
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $r = $i % 2 ? 70 : 150;
            $a = -M_PI / 2 + $i * M_PI / 5;
            $pts[] = 256 + $r * cos($a);
            $pts[] = 262 + $r * sin($a);
        }
        imagefilledpolygon($im, $pts, imagecolorallocate($im, 255, 255, 255));
        $tmp = make_request_directory() . '/default.png';
        imagepng($im, $tmp);
        imagedestroy($im);
        return $tmp;
    }
}
