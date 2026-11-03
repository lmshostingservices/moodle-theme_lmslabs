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
 * Import: pick another course from cards and copy its content into this one in one click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use theme_lmslabs\local\icons;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
require_capability('moodle/restore:restoretargetimport', $ctx);

$url = new moodle_url('/theme/lmslabs/import.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('import'));
$PAGE->set_heading(get_string('im_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/import.js'));

if (data_submitted() && confirm_sesskey() && ($from = optional_param('from', 0, PARAM_INT))) {
    $source = get_course($from);
    require_capability('moodle/backup:backuptargetimport', context_course::instance($source->id));
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    $replace = optional_param('replace', 0, PARAM_BOOL);
    $bc = new backup_controller(
        backup::TYPE_1COURSE, $source->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
        backup::MODE_IMPORT, $USER->id
    );
    $bc->execute_plan();
    $backupid = $bc->get_backupid();
    $bc->destroy();
    $target = $replace ? backup::TARGET_CURRENT_DELETING : backup::TARGET_CURRENT_ADDING;
    $rc = new restore_controller($backupid, $course->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, $target);
    if (!$rc->execute_precheck()) {
        $errors = $rc->get_precheck_results()['errors'] ?? [];
        $rc->destroy();
        redirect(
            $url, implode(' ', $errors) ?: get_string('im_failed', 'theme_lmslabs'), null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($replace) {
        restore_dbops::delete_course_content($course->id, ['keep_roles_and_enrolments' => 1, 'keep_groups_and_groupings' => 1]);
    }
    $rc->execute_plan();
    $rc->destroy();
    fulldelete(make_backup_temp_directory($backupid, false));
    redirect(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('im_done', 'theme_lmslabs', format_string($source->fullname)), null, \core\output\notification::NOTIFY_SUCCESS
    );
}

// Every course the person can copy from.
$cards = [];
$courses = get_user_capability_course(
    'moodle/backup:backuptargetimport', null, true,
    'fullname, shortname, category, visible', 'fullname ASC'
) ?: [];
$courses = array_column($courses, null, 'id');
$ids = array_diff(array_keys($courses), [$course->id, SITEID]);
$counts = [];
if ($ids) {
    [$in, $p] = $DB->get_in_or_equal($ids);
    $counts = $DB->get_records_sql_menu(
        "SELECT course, COUNT(1) FROM {course_modules} WHERE course $in AND deletioninprogress = 0
                                     GROUP BY course", $p
    );
}
$catnames = core_course_category::make_categories_list();
$tint = 0;
foreach ($ids as $cid) {
    $c = $courses[$cid];
    $cards[] = [
        'id' => $cid,
        'name' => format_string($c->fullname),
        'short' => format_string($c->shortname),
        'category' => $catnames[$c->category] ?? '',
        'activities' => (int)($counts[$cid] ?? 0),
        'image' => \theme_lmslabs\local\courseimage::url($c),
        'tint' => $tint++ % 6,
        'hidden' => !$c->visible,
        'search' => core_text::strtolower($c->fullname . ' ' . $c->shortname . ' ' . ($catnames[$c->category] ?? '')),
    ];
}

$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'coursename' => format_string($course->fullname),
    'cards' => $cards,
    'hascards' => (bool)$cards,
    'count' => count($cards),
    'classicurl' => \theme_lmslabs\local\router::classic('/backup/import.php', ['id' => $course->id]),
    'i' => ['search' => icons::svg('search'), 'import' => icons::svg('import'), 'check' => icons::svg('check'),
        'eyeoff' => icons::svg('eye-off'), 'alert' => icons::svg('circle-alert'), 'layers' => icons::svg('layers')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/import', $data);
echo $OUTPUT->footer();
