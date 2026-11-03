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
 * Enrolment methods: how people get into the course, each switched on or off in one click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

use theme_lmslabs\local\icons;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
require_capability('moodle/course:enrolreview', $ctx);

$url = new moodle_url('/theme/lmslabs/enrolmethods.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('enrolmentinstances', 'enrol'));
$PAGE->set_heading(get_string('enrolmentinstances', 'enrol'));

// Switch on or off.
if (($toggle = optional_param('toggle', 0, PARAM_INT)) && confirm_sesskey()) {
    $instance = $DB->get_record('enrol', ['id' => $toggle, 'courseid' => $course->id], '*', MUST_EXIST);
    $plugin = enrol_get_plugin($instance->enrol);
    require_capability('enrol/' . $instance->enrol . ':config', $ctx);
    if ($plugin && $plugin->can_hide_show_instance($instance)) {
        $plugin->update_status($instance, $instance->status == ENROL_INSTANCE_ENABLED ? ENROL_INSTANCE_DISABLED : ENROL_INSTANCE_ENABLED);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$iconfor = ['manual' => 'user-plus', 'self' => 'door-open', 'cohort' => 'contact-round', 'guest' => 'eye', 'meta' => 'link',
    'fee' => 'gift', 'paypal' => 'gift', 'lti' => 'external-link', 'database' => 'server', 'flatfile' => 'file-text'];
$methods = [];
$counts = $DB->get_records_sql_menu(
    "SELECT e.id, COUNT(ue.id) FROM {enrol} e
                                       LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                      WHERE e.courseid = ? GROUP BY e.id", [$course->id]
);
foreach (enrol_get_instances($course->id, false) as $instance) {
    $plugin = enrol_get_plugin($instance->enrol);
    if (!$plugin) {
        continue;
    }
    $on = $instance->status == ENROL_INSTANCE_ENABLED;
    $canconfig = has_capability('enrol/' . $instance->enrol . ':config', $ctx);
    $editurl = $canconfig && $plugin->can_edit_instance($instance) ? (new moodle_url(
        '/enrol/editinstance.php',
        ['courseid' => $course->id, 'id' => $instance->id, 'type' => $instance->enrol]
    ))->out(false) : null;
    $methods[] = [
        'id' => $instance->id,
        'name' => $plugin->get_instance_name($instance),
        'type' => $instance->enrol,
        'desc' => get_string('em_' . $instance->enrol, 'theme_lmslabs') ?: '',
        'icon' => icons::svg($iconfor[$instance->enrol] ?? 'door-open'),
        'on' => $on,
        'users' => (int)($counts[$instance->id] ?? 0),
        'canswitch' => $canconfig && $plugin->can_hide_show_instance($instance),
        'toggleurl' => (new moodle_url($url, ['toggle' => $instance->id, 'sesskey' => sesskey()]))->out(false),
        'editurl' => $editurl,
        'enrolurl' => $instance->enrol === 'manual' && has_capability('enrol/manual:enrol', $ctx)
            ? (new moodle_url('/theme/lmslabs/participants.php', ['id' => $course->id]))->out(false) : null,
    ];
}
$add = [];
foreach (enrol_get_plugins(true) as $name => $plugin) {
    if (!$plugin->can_add_instance($course->id)) {
        continue;
    }
    $add[] = ['name' => get_string('pluginname', 'enrol_' . $name), 'icon' => icons::svg($iconfor[$name] ?? 'door-open'),
        'desc' => get_string_manager()->string_exists('em_' . $name, 'theme_lmslabs') ? get_string('em_' . $name, 'theme_lmslabs') : '',
        'url' => (new moodle_url('/enrol/editinstance.php', ['type' => $name, 'courseid' => $course->id]))->out(false)];
}

$data = [
    'methods' => $methods,
    'add' => $add,
    'hasadd' => (bool)$add,
    'classicurl' => \theme_lmslabs\local\router::classic('/enrol/instances.php', ['id' => $course->id]),
    'i' => ['pencil' => icons::svg('pencil'), 'users' => icons::svg('users'), 'plus' => icons::svg('plus'),
        'userplus' => icons::svg('user-plus')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/enrolmethods', $data);
echo $OUTPUT->footer();
