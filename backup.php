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
 * Backup: one click for a backup now, and every existing backup of this course with download and restore.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

use theme_lmslabs\local\icons;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
require_capability('moodle/backup:backupcourse', $ctx);
$canusers = has_capability('moodle/backup:userinfo', $ctx);
$canmanage = has_capability('moodle/restore:uploadfile', $ctx);

$url = new moodle_url('/theme/lmslabs/backup.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('backup'));
$PAGE->set_heading(get_string('bk_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/backup.js'));

$fs = get_file_storage();
$userctx = context_user::instance($USER->id);
$prefs = json_decode((string)get_user_preferences('theme_lmslabs_backup', ''), true) ?: [];
$prefs += ['kind' => 'course', 'questionbank' => 1, 'badges' => 1, 'calendar_events' => 1];

// Delete one of the listed backups.
if (($del = optional_param('delete', '', PARAM_ALPHANUM)) && confirm_sesskey()) {
    $file = $fs->get_file_by_hash($del);
    $mine = $file && $file->get_contextid() == $userctx->id && $file->get_component() === 'user' && $file->get_filearea() === 'backup';
    $coursefile = $file && $file->get_contextid() == $ctx->id && $file->get_component() === 'backup' && $canmanage;
    if ($mine || $coursefile) {
        $file->delete();
        redirect($url, get_string('bk_deleted', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Make a backup now.
if (data_submitted() && confirm_sesskey() && optional_param('make', 0, PARAM_BOOL)) {
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    $kind = optional_param('kind', 'course', PARAM_ALPHA) === 'all' && $canusers ? 'all' : 'course';
    $prefs = ['kind' => $kind];
    $bc = new backup_controller(
        backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
        backup::MODE_GENERAL, $USER->id
    );
    $plan = $bc->get_plan();
    $set = function ($name, $value) use ($plan) {
        if ($plan->setting_exists($name)) {
            $setting = $plan->get_setting($name);
            if ($setting->get_status() == backup_setting::NOT_LOCKED) {
                $setting->set_value($value);
            }
        }
    };
    $set('users', $kind === 'all' ? 1 : 0);
    $set('anonymize', 0);
    foreach (['questionbank', 'badges', 'calendar_events'] as $opt) {
        $prefs[$opt] = optional_param('opt_' . $opt, 0, PARAM_BOOL) ? 1 : 0;
        $set($opt, $prefs[$opt]);
    }
    $filename = backup_plan_dbops::get_default_backup_filename(
        backup::FORMAT_MOODLE, backup::TYPE_1COURSE, $course->id,
        $kind === 'all', false
    );
    $set('filename', $filename);
    $bc->execute_plan();
    $results = $bc->get_results();
    $bc->destroy();
    set_user_preference('theme_lmslabs_backup', json_encode($prefs));
    $made = $results['backup_destination'] ?? null;
    redirect(
        new moodle_url($url, $made ? ['made' => $made->get_pathnamehash()] : []),
        get_string('bk_made', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_SUCCESS
    );
}

// Every backup of this course the person can see, newest first.
$made = optional_param('made', '', PARAM_ALPHANUM);
$files = [];
$areas = [
    [$ctx->id, 'backup', 'course', 'all', $canmanage],
    [$userctx->id, 'user', 'backup', 'course', true],
];
if (has_capability('moodle/restore:viewautomatedfilearea', $ctx)) {
    $areas[] = [$ctx->id, 'backup', 'automated', 'auto', false];
}
foreach ($areas as [$cid, $component, $area, $kind, $deletable]) {
    foreach ($fs->get_area_files($cid, $component, $area, false, 'timecreated DESC', false) as $f) {
        if ($component === 'user' && strpos($f->get_filename(), '-course-' . $course->id . '-') === false) {
            continue;
        }
        $hash = $f->get_pathnamehash();
        $files[] = [
            'ts' => $f->get_timecreated(),
            'name' => $f->get_filename(),
            'when' => userdate($f->get_timecreated(), get_string('strftimedatetimeshort', 'langconfig')),
            'ago' => \theme_lmslabs\local\participants::ago($f->get_timecreated()),
            'size' => display_size($f->get_filesize()),
            'kind' => get_string('bk_kind_' . $kind, 'theme_lmslabs'),
            'kindclass' => $kind,
            'isnew' => $hash === $made,
            'download' => moodle_url::make_pluginfile_url(
                $cid, $component, $area, null, $f->get_filepath(), $f->get_filename(),
                true
            )->out(false),
            'restore' => (new moodle_url('/theme/lmslabs/restore.php', ['id' => $course->id, 'file' => $hash]))->out(false),
            'delete' => $deletable ? (new moodle_url($url, ['delete' => $hash, 'sesskey' => sesskey()]))->out(false) : null,
        ];
    }
}
usort(
    $files, function ($a, $b) {
    return $b['ts'] <=> $a['ts'];
}
);

$auto = get_config('backup');
$autoon = !empty($auto->backup_auto_active);
$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'canusers' => $canusers,
    'kindcourse' => $prefs['kind'] !== 'all' || !$canusers,
    'kindall' => $prefs['kind'] === 'all' && $canusers,
    'options' => array_map(function ($k) use ($prefs) {
        return ['key' => $k, 'label' => get_string('bk_opt_' . $k, 'theme_lmslabs'), 'on' => !empty($prefs[$k]),
            'tip' => get_string('bk_opt_' . $k . '_tip', 'theme_lmslabs')];
    }, ['questionbank', 'badges', 'calendar_events']),
    'files' => $files,
    'hasfiles' => (bool)$files,
    'count' => count($files),
    'autoon' => $autoon,
    'autotext' => get_string($autoon ? 'bk_auto_on' : 'bk_auto_off', 'theme_lmslabs'),
    'autourl' => is_siteadmin() ? (new moodle_url('/admin/settings.php', ['section' => 'automated']))->out(false) : null,
    'restoreurl' => (new moodle_url('/theme/lmslabs/restore.php', ['id' => $course->id]))->out(false),
    'importurl' => (new moodle_url('/theme/lmslabs/import.php', ['id' => $course->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/backup/backup.php', ['id' => $course->id]),
    'i' => ['download' => icons::svg('hard-drive-download'), 'book' => icons::svg('book-open'), 'users' => icons::svg('users'),
        'restore' => icons::svg('archive-restore'), 'trash' => icons::svg('trash'), 'clock' => icons::svg('clock'),
        'file' => icons::svg('archive-restore'), 'settings' => icons::svg('settings'), 'import' => icons::svg('import'),
        'check' => icons::svg('check')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/backup', $data);
echo $OUTPUT->footer();
