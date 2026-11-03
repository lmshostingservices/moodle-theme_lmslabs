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
 * One-click switch to Boost (full site administration) for this session, and back.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
require_sesskey();

$to = required_param('to', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '/', PARAM_LOCALURL);

if ($to === 'boost') {
    if (!is_siteadmin()) {
        throw new required_capability_exception(context_system::instance(), 'moodle/site:config', 'nopermissions', '');
    }
    $SESSION->theme = 'boost';
    $SESSION->theme_lmslabs_boostview = 1;
    set_user_preference('theme_lmslabs_boostview', 1);
} else {
    unset($SESSION->theme);
    unset($SESSION->theme_lmslabs_boostview);
    unset_user_preference('theme_lmslabs_boostview');
}

redirect(new moodle_url($returnurl));
