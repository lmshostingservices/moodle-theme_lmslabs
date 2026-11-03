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
 * Save the site accent colour from the one-click picker.
 *
 * The colour is applied through CSS variables in the page head, so no cache purge is needed.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login(null, false);
require_sesskey();
require_capability('theme/lmslabs:managebrand', context_system::instance());

$hex = theme_lmslabs_clean_hex(trim(required_param('hex', PARAM_TEXT)));
if ($hex === null) {
    throw new invalid_parameter_exception('Invalid colour');
}

$old = get_config('theme_lmslabs', 'brandcolor');
set_config('brandcolor', $hex, 'theme_lmslabs');
add_to_config_log('brandcolor', $old, $hex, 'theme_lmslabs');

echo json_encode(['ok' => true, 'hex' => $hex, 'css' => theme_lmslabs_accent_css($hex)]);
