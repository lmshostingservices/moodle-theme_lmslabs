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
 * Calendar: what's due and what's on, as an agenda, a week or a month; subscribe from any calendar app.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\calendar;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no calendar');
}
$context = context_user::instance($USER->id);
$url = new moodle_url('/theme/lmslabs/calendar.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('calendar', 'calendar'));
$PAGE->set_heading(get_string('calendar', 'calendar'));
$PAGE->add_body_class('ll-calpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/calendar.js'));

$courses = array_values(calendar::courses());
$canpersonal = has_capability('moodle/calendar:manageownentries', context_system::instance());
$weekstart = (int)\core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();

$config = [
    'sesskey' => sesskey(),
    'ajax' => (new moodle_url('/theme/lmslabs/ajax/calendar.php'))->out(false),
    'courses' => $courses,
    'weekstart' => $weekstart,
    'feeds' => calendar::feeds(),
    'reminders' => (bool)get_user_preferences('theme_lmslabs_reminders', 0),
    'canpersonal' => $canpersonal,
    'str' => [
        'today' => get_string('today', 'calendar'),
        'tomorrow' => get_string('tomorrow', 'calendar'),
        'overdue' => get_string('cal_overdue', 'theme_lmslabs'),
        'thisweek' => get_string('cal_thisweek', 'theme_lmslabs'),
        'later' => get_string('cal_later', 'theme_lmslabs'),
        'nothing' => get_string('cal_nothing', 'theme_lmslabs'),
        'nothingday' => get_string('cal_nothingday', 'theme_lmslabs'),
        'allclear' => get_string('cal_allclear', 'theme_lmslabs'),
        'open' => get_string('cal_open', 'theme_lmslabs'),
        'google' => get_string('cal_addgoogle', 'theme_lmslabs'),
        'ics' => get_string('cal_downloadics', 'theme_lmslabs'),
        'delete' => get_string('delete'),
        'due' => get_string('cal_kind_due', 'theme_lmslabs'),
        'course' => get_string('cal_kind_course', 'theme_lmslabs'),
        'personal' => get_string('cal_kind_personal', 'theme_lmslabs'),
        'site' => get_string('cal_kind_site', 'theme_lmslabs'),
        'done' => get_string('cal_done', 'theme_lmslabs'),
        'more' => get_string('cal_more', 'theme_lmslabs', '{n}'),
        'copied' => get_string('cal_copied', 'theme_lmslabs'),
        'allday' => get_string('cal_allday', 'theme_lmslabs'),
    ],
];

$data = [
    'config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES),
    'courses' => $courses,
    'hascourses' => (bool)$courses,
    'canpersonal' => $canpersonal,
    'reminders' => $config['reminders'],
    'feeds' => $config['feeds'],
    'prefsurl' => (new moodle_url('/user/calendar.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/calendar/view.php', ['view' => 'month']),
    'i' => [
        'left' => icons::svg('chevron-left'), 'right' => icons::svg('chevron-right'), 'plus' => icons::svg('plus'),
        'share' => icons::svg('calendar-clock'), 'bell' => icons::svg('bell'), 'x' => icons::svg('x'),
        'copy' => icons::svg('copy'), 'download' => icons::svg('download'), 'link' => icons::svg('external-link'),
        'list' => icons::svg('list'), 'week' => icons::svg('calendar-days'), 'month' => icons::svg('layout-grid'),
        'filter' => icons::svg('list-filter'), 'chev' => icons::svg('chevron-down'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/calendar', $data);
echo $OUTPUT->footer();
