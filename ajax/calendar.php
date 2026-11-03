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
 * Calendar data for the Premium SaaS calendar: events in a range, personal events, reminders.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');

require_login(null, false);
require_sesskey();
$PAGE->set_context(context_user::instance($USER->id));

$action = required_param('action', PARAM_ALPHA);
$result = ['ok' => true];

switch ($action) {
    case 'events':
        $from = required_param('from', PARAM_INT);
        $to = required_param('to', PARAM_INT);
        if ($to - $from > 120 * DAYSECS) {
            $to = $from + 120 * DAYSECS;
        }
        $result['events'] = \theme_lmslabs\local\calendar::events($from, $to);
        break;

    case 'create':
        // A personal event, only ever visible to its owner.
        require_capability('moodle/calendar:manageownentries', context_system::instance());
        $name = trim(required_param('name', PARAM_TEXT));
        $start = required_param('start', PARAM_INT);
        $duration = max(0, optional_param('duration', 0, PARAM_INT));
        if ($name === '') {
            throw new invalid_parameter_exception('Name required');
        }
        $event = \calendar_event::create(
            (object)[
            'name' => $name,
            'description' => trim(optional_param('description', '', PARAM_TEXT)),
            'format' => FORMAT_PLAIN,
            'eventtype' => 'user',
            'userid' => $USER->id,
            'courseid' => 0,
            'groupid' => 0,
            'timestart' => $start,
            'timeduration' => $duration,
            'visible' => 1,
        ], false
        );
        $result['id'] = $event->id;
        $result['message'] = get_string('cal_created', 'theme_lmslabs');
        break;

    case 'delete':
        $id = required_param('id', PARAM_INT);
        $event = \calendar_event::load($id);
        if ($event->eventtype !== 'user' || $event->userid != $USER->id) {
            throw new required_capability_exception(
                context_system::instance(), 'moodle/calendar:manageownentries',
                'nopermissions', ''
            );
        }
        $event->delete();
        $result['message'] = get_string('cal_deleted', 'theme_lmslabs');
        break;

    case 'reminders':
        $on = required_param('on', PARAM_BOOL);
        set_user_preference('theme_lmslabs_reminders', $on ? 1 : 0);
        $result['message'] = get_string($on ? 'cal_reminders_on' : 'cal_reminders_off', 'theme_lmslabs');
        break;

    default:
        throw new invalid_parameter_exception('Unknown action');
}

echo json_encode($result);
