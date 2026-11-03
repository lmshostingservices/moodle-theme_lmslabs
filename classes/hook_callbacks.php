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

namespace theme_lmslabs;

use theme_lmslabs\local\request;

use moodle_url;

/**
 * Hook callbacks.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Send /my/courses.php to the program page before Moodle's own redirects run
     * (Moodle 5.x sends it to the dashboard when its My courses page is switched off).
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG, $SESSION, $PAGE;
        $script = request::script();
        // One-hop view switch: the link goes straight to the destination page with ?lmsw=boost|lmslabs, so there is
        // no separate switch request and redirect before the page loads.
        if (!during_initial_install() && !CLI_SCRIPT && !AJAX_SCRIPT && ($CFG->theme ?? '') === 'lmslabs' &&
                isset($SESSION) && request::get('lmsw') !== null && isloggedin() && !isguestuser() &&
                request::get('sesskey') !== null && confirm_sesskey()) {
            if (request::get('lmsw') === 'boost' && is_siteadmin()) {
                $SESSION->theme = 'boost';
                $SESSION->theme_lmslabs_boostview = 1;
                set_user_preference('theme_lmslabs_boostview', 1);
            } else if (request::get('lmsw') === 'lmslabs') {
                unset($SESSION->theme);
                unset($SESSION->theme_lmslabs_boostview);
                unset_user_preference('theme_lmslabs_boostview');
            }
        }
        // Boost view is a session theme. Premium SaaS pages only make sense in the Premium SaaS theme, so for
        // those pages (only) the session theme is set aside for the request; the next request puts it back from
        // the Boost view flag. (Forcing the theme instead breaks pages that set their course afterwards.)
        if (!during_initial_install() && !CLI_SCRIPT && ($CFG->theme ?? '') === 'lmslabs' && isset($SESSION)) {
            if (!empty($SESSION->theme_lmslabs_boostview) && empty($SESSION->theme)) {
                $SESSION->theme = 'boost';
            }
            if (!empty($SESSION->theme) && strpos($script, '/theme/lmslabs/') !== false &&
                    strpos($script, '/theme/lmslabs/switch.php') === false && strpos($script, '/theme/lmslabs/ajax/') === false) {
                unset($SESSION->theme);
                return;
            }
        }
        if (during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || ($CFG->theme ?? '') !== 'lmslabs' ||
                !empty($SESSION->theme)) {
            return;
        }
        if (substr($script, -strlen('/my/courses.php')) === '/my/courses.php') {
            redirect(new moodle_url('/theme/lmslabs/courses.php'));
        }
    }

    /**
     * In this theme: My courses opens the program page, and locked courses send learners back to it.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $USER;
        if (during_initial_install() || !isloggedin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT) {
            return;
        }
        if ($PAGE->theme->name !== 'lmslabs') {
            return;
        }
        $path = $PAGE->url->get_path(false);
        if ($path === '/my/courses.php') {
            redirect(new moodle_url('/theme/lmslabs/courses.php'));
        }
        // Open the Premium SaaS version of a Moodle screen (append ?classic=1 for Moodle's own).
        if (request::is_get() && !optional_param('classic', 0, PARAM_BOOL)) {
            $premium = \theme_lmslabs\local\router::premium($path, request::query());
            if ($premium) {
                redirect($premium);
            }
        }
        $course = $PAGE->course;
        if (!$course || $course->id == SITEID) {
            return;
        }
        if (strpos($path, '/course/view.php') !== 0 && strpos($path, '/course/section.php') !== 0 &&
                strpos($path, '/mod/') !== 0) {
            return;
        }
        $lock = \theme_lmslabs\local\programs::lock($course, (int)$USER->id);
        if ($lock) {
            redirect(
                new moodle_url('/theme/lmslabs/courses.php'), $lock['reason'], null,
                \core\output\notification::NOTIFY_INFO
            );
        }
    }

    /**
     * While an admin is in Boost view, show a floating button back to the LMS Labs view.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $SESSION, $PAGE;
        if (empty($SESSION->theme_lmslabs_boostview) || during_initial_install() || !isloggedin()) {
            return;
        }
        if ($PAGE->theme->name === 'lmslabs' || in_array($PAGE->pagelayout, ['embedded', 'popup', 'redirect', 'print'])) {
            return;
        }
        // The Moodle site administration tree only makes sense in the Boost view, so going back from it lands on the
        // dashboard rather than on a half-styled copy of that page.
        $path = $PAGE->url->get_path(false);
        $back = in_array($path, ['/admin/search.php', '/admin/index.php', '/admin/category.php'], true) ?
            new moodle_url('/my/') : $PAGE->url;
        $url = new moodle_url($back, ['lmsw' => 'lmslabs', 'sesskey' => sesskey()]);
        $label = get_string('returntolmslabs', 'theme_lmslabs');
        $hook->add_html(
            '<a href="' . s($url->out(false)) . '" class="theme-lmslabs-return" onclick="this.style.opacity=.7;this.lastChild.nodeValue=\'…\'" style="position:fixed;z-index:1100;left:20px;' .
            'bottom:20px;display:inline-flex;align-items:center;gap:8px;padding:10px 16px;' .
            'border-radius:999px;background:#111827;color:#fff;font:500 13px/1 system-ui,sans-serif;text-decoration:none;' .
            'box-shadow:0 10px 30px rgba(17,24,39,.25)">' .
            '<span style="width:8px;height:8px;border-radius:50%;background:#22c55e"></span>' . s($label) . '</a>'
        );
    }
}
