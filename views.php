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
 * Save, delete or set default for a saved filter view.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\views;

require_login(null, false);
require_sesskey();

$action = required_param('action', PARAM_ALPHA);
$return = new moodle_url(required_param('returnurl', PARAM_LOCALURL));

switch ($action) {
    case 'save':
        $page = required_param('page', PARAM_ALPHA);
        if (!in_array($page, views::PAGES, true)) {
            throw new invalid_parameter_exception('Unknown page');
        }
        $name = trim(required_param('name', PARAM_TEXT));
        if ($name === '') {
            $name = get_string('fb_untitledview', 'theme_lmslabs');
        }
        views::save($USER->id, $page, $name, $return->get_query_string(false));
        redirect($return, get_string('fb_viewsaved', 'theme_lmslabs', s($name)), null, \core\output\notification::NOTIFY_SUCCESS);
        break;
    case 'delete':
        views::delete(required_param('id', PARAM_INT), $USER->id);
        break;
    case 'default':
        views::toggle_default(required_param('id', PARAM_INT), $USER->id);
        break;
    default:
        throw new invalid_parameter_exception('Unknown action');
}
redirect($return);
