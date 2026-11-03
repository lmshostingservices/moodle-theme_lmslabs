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
 * Purge all caches (available to Site Owners).
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
require_sesskey();
require_capability('theme/lmslabs:managebrand', context_system::instance());

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
if ($returnurl === '') {
    $ref = get_local_referer(false);
    if ($ref !== '' && strpos($ref, '/theme/lmslabs/purge.php') === false) {
        $returnurl = $ref;
    }
}
purge_caches();
redirect(
    new moodle_url($returnurl ?: '/my/'), get_string('cachespurged', 'theme_lmslabs'), null,
    \core\output\notification::NOTIFY_SUCCESS
);
