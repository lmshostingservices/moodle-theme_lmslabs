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

/**
 * Split-screen sign-in layout.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$sitecontext = context_course::instance(SITEID);
$sitename = format_string($SITE->fullname, true, ['context' => $sitecontext, 'escape' => false]);
$summary = trim(strip_tags(format_text($SITE->summary ?? '', FORMAT_HTML, ['context' => $sitecontext])));

$templatecontext = [
    'sitename' => $sitename,
    'siteinitial' => core_text::strtoupper(core_text::substr($sitename, 0, 1)),
    'summary' => $summary !== '' ? shorten_text($summary, 220) : null,
    'logourl' => theme_lmslabs_logo_url(160),
    'loginimage' => theme_lmslabs_login_image_url(),
    'homeurl' => (new moodle_url('/'))->out(false),
    'contact' => theme_lmslabs_contact(),
    'output' => $OUTPUT,
    'accentcss' => theme_lmslabs_accent_css(),
    'fontcss' => theme_lmslabs_font()[1],
    'bodyattributes' => $OUTPUT->body_attributes(['ll-login']),
    'year' => userdate(time(), '%Y'),
];

echo $OUTPUT->render_from_template('theme_lmslabs/login', $templatecontext);
