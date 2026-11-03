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
 * Restore: drop a backup file or pick one, see what's in it, choose where it goes, restore in one click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use theme_lmslabs\local\icons;

$id = optional_param('id', 0, PARAM_INT);
$filehash = optional_param('file', '', PARAM_ALPHANUM);
$fs = get_file_storage();
$userctx = null;

if ($id) {
    $course = get_course($id);
    require_login($course);
    $ctx = context_course::instance($course->id);
    require_capability('moodle/restore:restorecourse', $ctx);
    $PAGE->set_pagelayout('incourse');
} else {
    require_login(null, false);
    $course = null;
    $ctx = context_system::instance();
    $PAGE->set_context($ctx);
    $PAGE->set_pagelayout('admin');
}
$userctx = context_user::instance($USER->id);
$url = new moodle_url('/theme/lmslabs/restore.php', array_filter(['id' => $id]));
$PAGE->set_url(new moodle_url($url, array_filter(['file' => $filehash])));
$PAGE->set_title(get_string('restore'));
$PAGE->set_heading(get_string('rt_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/restore.js'));

$categories = core_course_category::make_categories_list('moodle/course:create');
$cancreate = (bool)$categories;

/**
 * Is this stored file a backup the current person may use here?
 *
 * @param stored_file|false $file
 * @param context $ctx
 * @param context_user $userctx
 * @return bool
 */
function theme_lmslabs_restore_usable($file, $ctx, $userctx): bool {
    if (!$file || $file->is_directory()) {
        return false;
    }
    if ($file->get_contextid() == $userctx->id && $file->get_component() === 'user' && $file->get_filearea() === 'backup') {
        return true;
    }
    if ($ctx->contextlevel == CONTEXT_COURSE && $file->get_contextid() == $ctx->id && $file->get_component() === 'backup') {
        return $file->get_filearea() === 'course' ||
            ($file->get_filearea() === 'automated' && has_capability('moodle/restore:viewautomatedfilearea', $ctx));
    }
    return false;
}

// Step 1b: an uploaded file goes into the person's own backup files, then on to its preview.
if (!empty($_FILES['mbz']['tmp_name']) && confirm_sesskey()) {
    require_capability('moodle/restore:uploadfile', $ctx);
    $up = $_FILES['mbz'];
    $name = clean_param($up['name'], PARAM_FILE);
    if (!is_uploaded_file($up['tmp_name']) || !preg_match('/\.mbz$/i', $name)) {
        redirect($url, get_string('rt_badfile', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($fs->file_exists($userctx->id, 'user', 'backup', 0, '/', $name)) {
        $name = preg_replace('/\.mbz$/i', '', $name) . '-' . date('His') . '.mbz';
    }
    $stored = $fs->create_file_from_pathname(['contextid' => $userctx->id, 'component' => 'user', 'filearea' => 'backup',
        'itemid' => 0, 'filepath' => '/', 'filename' => $name], $up['tmp_name']);
    redirect(new moodle_url($url, ['file' => $stored->get_pathnamehash()]));
}

// Step 3: restore.
if (data_submitted() && confirm_sesskey() && ($tmp = optional_param('tmp', '', PARAM_ALPHANUM))) {
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    $dir = make_backup_temp_directory($tmp, false);
    if (!is_dir($dir) || !file_exists($dir . '/moodle_backup.xml')) {
        redirect($url, get_string('rt_expired', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $info = backup_general_helper::get_backup_information($tmp);
    $where = optional_param('where', 'new', PARAM_ALPHA);
    if (!$course) {
        $where = 'new';
    }
    if ($where === 'new') {
        $catid = required_param('category', PARAM_INT);
        if (!isset($categories[$catid])) {
            throw new required_capability_exception(
                context_coursecat::instance($catid), 'moodle/course:create',
                'nopermissions', ''
            );
        }
        require_capability('moodle/restore:restorecourse', context_coursecat::instance($catid));
        [$fullname, $shortname] = restore_dbops::calculate_course_names(
            0, $info->original_course_fullname,
            $info->original_course_shortname
        );
        $targetid = restore_dbops::create_new_course($fullname, $shortname, $catid);
        $target = backup::TARGET_NEW_COURSE;
    } else {
        $targetid = $course->id;
        $target = $where === 'replace' ? backup::TARGET_EXISTING_DELETING : backup::TARGET_EXISTING_ADDING;
    }
    $rc = new restore_controller($tmp, $targetid, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id, $target);
    $plan = $rc->get_plan();
    $withusers = optional_param('users', 0, PARAM_BOOL) && !empty($info->root_settings['users']) &&
        has_capability('moodle/restore:userinfo', $where === 'new' ? context_coursecat::instance($catid) : $ctx);
    foreach (['users' => (int)$withusers, 'course_fullname' => $fullname ?? null, 'course_shortname' => $shortname ?? null] as $k => $v) {
        if ($v !== null && $plan->setting_exists($k) && $plan->get_setting($k)->get_status() == base_setting::NOT_LOCKED) {
            $plan->get_setting($k)->set_value($v);
        }
    }
    if (!$rc->execute_precheck()) {
        $errors = $rc->get_precheck_results()['errors'] ?? [];
        $rc->destroy();
        if ($where === 'new') {
            delete_course($targetid, false);
        }
        redirect(
            $url, implode(' ', $errors) ?: get_string('rt_failed', 'theme_lmslabs'), null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($target === backup::TARGET_EXISTING_DELETING) {
        restore_dbops::delete_course_content($targetid, ['keep_roles_and_enrolments' => 1, 'keep_groups_and_groupings' => 1]);
    }
    $rc->execute_plan();
    $rc->destroy();
    fulldelete($dir);
    redirect(
        new moodle_url('/course/view.php', ['id' => $targetid]), get_string('rt_done', 'theme_lmslabs'), null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Step 2: preview the chosen file.
$preview = null;
if ($filehash) {
    $file = $fs->get_file_by_hash($filehash);
    if (!theme_lmslabs_restore_usable($file, $ctx, $userctx)) {
        throw new moodle_exception('invalidfile');
    }
    $tmp = restore_controller::get_tempdir_name($id ?: SITEID, $USER->id);
    $dir = make_backup_temp_directory($tmp);
    $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $dir);
    if (!file_exists($dir . '/moodle_backup.xml')) {
        fulldelete($dir);
        redirect($url, get_string('rt_badfile', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $info = backup_general_helper::get_backup_information($tmp);
    $mods = [];
    foreach ($info->activities as $a) {
        $mods[$a->modulename] = ($mods[$a->modulename] ?? 0) + 1;
    }
    arsort($mods);
    $hasusers = !empty($info->root_settings['users']);
    $defaultcat = $course ? $course->category : array_key_first($categories);
    $preview = [
        'tmp' => $tmp,
        'name' => format_string($info->original_course_fullname),
        'short' => format_string($info->original_course_shortname),
        'date' => userdate($info->backup_date, get_string('strftimedatetimeshort', 'langconfig')),
        'filename' => $file->get_filename(),
        'size' => display_size($file->get_filesize()),
        'activities' => count($info->activities),
        'sections' => count($info->sections),
        'mods' => array_slice(
            array_map(function ($m, $n) {
            return ['name' => get_string('modulenameplural', $m), 'n' => $n];
        }, array_keys($mods), $mods), 0, 6
        ),
        'hasusers' => $hasusers,
        'canusers' => $hasusers && has_capability('moodle/restore:userinfo', $ctx),
        'release' => $info->moodle_release,
        'type' => $info->type,
        'notcourse' => $info->type !== backup::TYPE_1COURSE,
    ];
    $data['categories'] = array_map(function ($cid, $name) use ($defaultcat) {
        return ['id' => $cid, 'name' => $name, 'selected' => $cid == $defaultcat];
    }, array_keys($categories), $categories);
}

// Step 1: pick a backup.
$files = [];
$areas = [[$userctx->id, 'user', 'backup']];
if ($course) {
    array_unshift($areas, [$ctx->id, 'backup', 'course']);
    if (has_capability('moodle/restore:viewautomatedfilearea', $ctx)) {
        $areas[] = [$ctx->id, 'backup', 'automated'];
    }
}
foreach ($areas as [$cid, $component, $area]) {
    foreach ($fs->get_area_files($cid, $component, $area, false, 'timecreated DESC', false) as $f) {
        if (!preg_match('/\.mbz$/i', $f->get_filename())) {
            continue;
        }
        $files[] = [
            'ts' => $f->get_timecreated(),
            'name' => $f->get_filename(),
            'when' => userdate($f->get_timecreated(), get_string('strftimedatetimeshort', 'langconfig')),
            'size' => display_size($f->get_filesize()),
            'url' => (new moodle_url($url, ['file' => $f->get_pathnamehash()]))->out(false),
            'on' => $f->get_pathnamehash() === $filehash,
        ];
    }
}
usort(
    $files, function ($a, $b) {
    return $b['ts'] <=> $a['ts'];
}
);

$data = ($data ?? []) + [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'incourse' => (bool)$course,
    'coursename' => $course ? format_string($course->fullname) : '',
    'cancreate' => $cancreate,
    'canupload' => has_capability('moodle/restore:uploadfile', $ctx),
    'maxsize' => display_size(get_max_upload_file_size($CFG->maxbytes)),
    'files' => array_slice($files, 0, 12),
    'hasfiles' => (bool)$files,
    'preview' => $preview,
    'backurl' => $url->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/backup/restorefile.php', ['contextid' => $ctx->id]),
    'i' => ['upload' => icons::svg('file-up'), 'restore' => icons::svg('archive-restore'), 'book' => icons::svg('book-open'),
        'plus' => icons::svg('package-plus'), 'merge' => icons::svg('layers'), 'replace' => icons::svg('refresh'),
        'users' => icons::svg('users'), 'check' => icons::svg('check'), 'alert' => icons::svg('circle-alert'),
        'x' => icons::svg('x'), 'clock' => icons::svg('clock')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/restore', $data);
echo $OUTPUT->footer();
