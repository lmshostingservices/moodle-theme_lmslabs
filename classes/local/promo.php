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
 * The LMS Labs footer bar. Always shown; there is deliberately no setting to turn it off.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class promo {
    /** LMS Labs website. */
    const LABS = 'https://lms-labs.com';
    /** Hosting website. */
    const HOSTING = 'https://lmshostingservices.com';
    /** YouTube channel. */
    const YOUTUBE = 'https://www.youtube.com/@LMSHostingServices';
    /** LMS Labs AI credits promo code: 10% more credits, applied automatically by the link. */
    const PROMOCODE = 'JANPRF2CE10';
    /** Pricing page with the promo code already filled in. */
    const PROMOURL = 'https://lms-labs.com/pricing?ref=JANPRF2CE10';
    /** Support email. */
    const SUPPORT = 'support@lmshostingservices.com';

    /**
     * Template context for the bar.
     *
     * @return array|null null when the hosting provider has switched the bar off
     */
    public static function export(): ?array {
        global $PAGE;
        if (self::hidden()) {
            return null;
        }
        // Learners get a clean course: the banner stays out of the way while they study.
        if (isset($PAGE) && !empty($PAGE->course->id) && $PAGE->course->id != SITEID && isloggedin() &&
                !has_capability('moodle/course:update', \context_course::instance($PAGE->course->id))) {
            return null;
        }
        return [
            'labsurl' => self::LABS,
            'links' => [
                ['label' => 'lms-labs.com', 'url' => self::LABS, 'class' => 'is-labs',
                    'icon' => icons::svg('globe'), 'external' => true],
                ['label' => get_string('promo_youtube', 'theme_lmslabs'), 'url' => self::YOUTUBE, 'class' => 'is-youtube',
                    'icon' => self::youtube(), 'external' => true],
                ['label' => 'lmshostingservices.com', 'url' => self::HOSTING, 'class' => 'is-hosting',
                    'icon' => icons::svg('server'), 'external' => true],
                ['label' => self::SUPPORT, 'url' => 'mailto:' . self::SUPPORT, 'class' => 'is-support',
                    'icon' => icons::svg('life-buoy'), 'external' => false],
            ],
            'logo' => self::logo_url(),
            'mark' => self::mark_url(),
            'deal' => [
                'url' => self::PROMOURL,
                'code' => self::PROMOCODE,
                'gift' => icons::svg('gift'),
            ],
        ];
    }

    /**
     * Has the hosting provider switched the bar off for this site?
     *
     * @return bool
     */
    public static function hidden(): bool {
        return (bool)get_config('theme_lmslabs', 'hidepromo');
    }

    /**
     * The YouTube mark in its own colours.
     *
     * @return string
     */
    protected static function youtube(): string {
        return '<svg class="ll-ico ll-ico-yt" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">' .
            '<path fill="#ff0000" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1c.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8z"/>' .
            '<path fill="#fff" d="M9.6 15.6V8.4l6.2 3.6z"/></svg>';
    }

    /**
     * The full LMS Labs logo.
     *
     * @return string
     */
    protected static function logo_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('lmslabs-logo', 'theme_lmslabs')->out(false);
    }

    /**
     * The LMS Labs flask mark.
     *
     * @return string
     */
    protected static function mark_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('lmslabs-mark', 'theme_lmslabs')->out(false);
    }
}
