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
 * Members of a group or cohort: search, add, remove.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

require_login(null, false);
require_sesskey();

$type = required_param('type', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$members = new \theme_lmslabs\local\members($type, $id);

$result = ['ok' => true];
switch ($action) {
    case 'list':
        $q = optional_param('q', '', PARAM_TEXT);
        $which = optional_param('which', 'both', PARAM_ALPHA);
        if ($which !== 'candidates') {
            $result['members'] = $members->members($which === 'both' ? '' : $q);
        }
        if ($which !== 'members') {
            $result['candidates'] = $members->candidates($which === 'both' ? '' : $q);
        }
        break;
    case 'add':
        $json = required_param('users', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, cleaned.
        $result['count'] = $members->add(json_decode($json, true) ?: []);
        break;
    case 'remove':
        $json = required_param('users', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, cleaned.
        $result['count'] = $members->remove(json_decode($json, true) ?: []);
        break;
    default:
        throw new invalid_parameter_exception('Unknown action');
}
echo json_encode($result);
