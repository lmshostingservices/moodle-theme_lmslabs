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
 * Hosting provider settings: the two switches only a site administrator can change.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$PAGE->set_context($context);
$url = new moodle_url('/theme/lmslabs/hosting.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('hostingsettings', 'theme_lmslabs'));
$PAGE->set_heading(get_string('hostingsettings', 'theme_lmslabs'));

if (data_submitted() && confirm_sesskey()) {
    set_config('hidepromo', optional_param('hidepromo', 0, PARAM_BOOL) ? 1 : 0, 'theme_lmslabs');
    $ai = optional_param('useaiformat', 0, PARAM_BOOL) ? 1 : 0;
    $changed = (int)get_config('theme_lmslabs', 'useaiformat') !== $ai;
    set_config('useaiformat', $ai, 'theme_lmslabs');
    if ($changed) {
        \theme_lmslabs\local\aiformat::setting_updated();
    }
    theme_reset_all_caches();
    redirect($url, get_string('hs_saved', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$ai = get_config('theme_lmslabs', 'useaiformat');
$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'hidepromo' => (bool)get_config('theme_lmslabs', 'hidepromo'),
    'useaiformat' => $ai === false ? true : (bool)$ai,
    'classicurl' => \theme_lmslabs\local\router::classic('/admin/settings.php', ['section' => 'themesettinglmslabshosting']),
    'i' => ['server' => icons::svg('server'), 'check' => icons::svg('check')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/hosting', $data);
echo $OUTPUT->footer();
