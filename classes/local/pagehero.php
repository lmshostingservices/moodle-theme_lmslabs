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
 * A short header for Moodle's own screens: an icon, what the page is for, and the next best click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pagehero {
    /** pagetype => [icon, string key]. */
    const PAGES = [
        'course-edit' => ['settings', 'ph_courseedit'],
        'course-completion' => ['circle-check-big', 'ph_completion'],
        'backup-backup' => ['hard-drive-download', 'ph_backup'],
        'backup-restorefile' => ['archive-restore', 'ph_restore'],
        'backup-restore' => ['archive-restore', 'ph_restore'],
        'backup-import' => ['import', 'ph_import'],
        'course-reset' => ['rotate-ccw', 'ph_reset'],
        'enrol-instances' => ['door-open', 'ph_enrol'],
        'enrol-editinstance' => ['door-open', 'ph_enrol'],
        'admin-tool-uploaduser-index' => ['upload', 'ph_uploadusers'],
        'admin-tool-uploadcourse-index' => ['file-up', 'ph_uploadcourses'],
        'grade-report-grader-index' => ['chart-column', 'ph_grader'],
        'grade-report-user-index' => ['chart-column', 'ph_usergrades'],
        'question-banks' => ['file-question', 'ph_qbank'],
        'question-edit' => ['file-question', 'ph_qbank'],
        'badges-index' => ['award', 'ph_badges'],
        'badges-newbadge' => ['award', 'ph_badges'],
        'admin-setting-themesettinglmslabs' => ['palette', 'ph_themesettings'],
        'admin-setting-themesettinglmslabshosting' => ['server', 'ph_hosting'],
        'user-edit' => ['user-round', 'ph_editprofile'],
        'user-editadvanced' => ['user-round', 'ph_editprofile'],
        'login-change_password' => ['key', 'ph_password'],
        'course-editcategory' => ['folder', 'ph_category'],
        'group-group' => ['users-round', 'ph_group'],
        'cohort-edit' => ['contact-round', 'ph_cohort'],
        'report-log-index' => ['scroll-text', 'ph_logs'],
        'report-outline-index' => ['activity', 'ph_outline'],
        'report-participation-index' => ['users', 'ph_participation'],
        'report-progress-index' => ['circle-check-big', 'ph_progress'],
        'report-completion-index' => ['circle-check-big', 'ph_coursecompletion'],
    ];

    /**
     * Hero for the current page, or null.
     *
     * @param \moodle_page $page
     * @return array|null
     */
    public static function for_page(\moodle_page $page): ?array {
        $def = self::PAGES[$page->pagetype] ?? null;
        if (!$def) {
            return null;
        }
        return [
            'icon' => icons::svg($def[0]),
            'text' => get_string($def[1], 'theme_lmslabs'),
        ];
    }
}
