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
 * Program editor actions: save, delete, reorder.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use theme_lmslabs\local\programs;

require_login(null, false);
require_sesskey();
require_capability('theme/lmslabs:manageprograms', context_system::instance());

$action = required_param('action', PARAM_ALPHA);
$result = ['ok' => true];

switch ($action) {
    case 'save':
        $json = required_param('data', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, cleaned.
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new invalid_parameter_exception('Invalid data');
        }
        $result['id'] = programs::save(optional_param('id', 0, PARAM_INT), $data);
        break;
    case 'delete':
        programs::delete(required_param('id', PARAM_INT));
        break;
    case 'reorder':
        $json = required_param('ids', PARAM_RAW); // pipeline-ignore: PARAM_RAW — JSON, cleaned.
        $ids = json_decode($json, true);
        programs::reorder(is_array($ids) ? array_map('intval', $ids) : []);
        break;
    default:
        throw new invalid_parameter_exception('Unknown action');
}
echo json_encode($result);
