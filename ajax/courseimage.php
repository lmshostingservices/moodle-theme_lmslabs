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
 * Change a course card image from the card itself.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login(null, false);
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    throw new invalid_parameter_exception('No file');
}
$name = clean_filename($_FILES['image']['name']);
if (!file_mimetype_in_typegroup(mimeinfo('type', $name), 'web_image') || $_FILES['image']['size'] > 10 * 1024 * 1024) {
    throw new invalid_parameter_exception(get_string('ci_badfile', 'theme_lmslabs'));
}
$url = \theme_lmslabs\local\courseimage::store($courseid, $_FILES['image']['tmp_name'], $name);
echo json_encode(['ok' => true, 'url' => $url, 'message' => get_string('ci_saved', 'theme_lmslabs')]);
