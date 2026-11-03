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
 * Upload users: drop a CSV, check it, choose what to do, import, see what happened - one screen flow.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/admin/tool/uploaduser/locallib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:uploadusers', $context);
$PAGE->set_context($context);
$url = new moodle_url('/theme/lmslabs/uploadusers.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('uploadusers', 'tool_uploaduser'));
$PAGE->set_heading(get_string('uu_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/uploadusers.js'));

require_once(__DIR__ . '/classes/local/uutracker.php');

$iid = optional_param('iid', 0, PARAM_INT);

// Step 1b: a file arrives.
if (!empty($_FILES['csv']['tmp_name']) && confirm_sesskey()) {
    $up = $_FILES['csv'];
    if (!is_uploaded_file($up['tmp_name'])) {
        redirect($url, get_string('uu_badfile', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $content = file_get_contents($up['tmp_name']);
    $first = strtok($content, "\n");
    $delimiter = 'comma';
    foreach (['semicolon' => ';', 'tab' => "\t", 'colon' => ':'] as $name => $ch) {
        if (substr_count($first, $ch) > substr_count($first, ',')) {
            $delimiter = $name;
        }
    }
    $iid = csv_import_reader::get_new_iid('uploaduser');
    $cir = new csv_import_reader($iid, 'uploaduser');
    $readcount = $cir->load_csv_content($content, 'UTF-8', $delimiter);
    if ($readcount === false || $cir->get_error()) {
        redirect($url, get_string('uu_badfile', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url($url, ['iid' => $iid]));
}

$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'exampleurl' => (new moodle_url('/admin/tool/uploaduser/example.csv'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/admin/tool/uploaduser/index.php'),
    'peopleurl' => (new moodle_url('/theme/lmslabs/people.php'))->out(false),
    'i' => ['upload' => icons::svg('file-up'), 'check' => icons::svg('check'), 'alert' => icons::svg('circle-alert'),
        'plus' => icons::svg('user-plus'), 'refresh' => icons::svg('refresh'), 'users' => icons::svg('users'),
        'x' => icons::svg('x'), 'download' => icons::svg('download'), 'key' => icons::svg('key-round'),
        'mail' => icons::svg('mail')],
];

// Step 3: run the import and show what happened.
if ($iid && data_submitted() && confirm_sesskey() && optional_param('run', 0, PARAM_BOOL)) {
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    $cir = new csv_import_reader($iid, 'uploaduser');
    $mode = optional_param('mode', 'add', PARAM_ALPHA);
    $form = (object)[
        'uutype' => ['add' => UU_USER_ADDNEW, 'both' => UU_USER_ADD_UPDATE, 'update' => UU_USER_UPDATE][$mode] ?? UU_USER_ADDNEW,
        'uupasswordnew' => 1,
        'uupasswordold' => 0,
        'uuupdatetype' => $mode === 'add' ? UU_UPDATE_NOCHANGES : UU_UPDATE_FILEOVERRIDE,
        'uuforcepasswordchange' => optional_param('force', 0, PARAM_BOOL) ? UU_PWRESET_ALL : UU_PWRESET_NONE,
        'uumatchemail' => 0, 'uuallowrenames' => 0, 'uuallowdeletes' => 0, 'uuallowsuspends' => 1,
        'uunoemailduplicates' => 1, 'uustandardusernames' => 1, 'uubulk' => 0,
        'auth' => 'manual', 'username' => '',
    ];
    $process = new \tool_uploaduser\process($cir, \theme_lmslabs\local\uutracker::class);
    $process->get_file_columns();
    $process->set_form_data($form);
    ob_start();
    $process->process();
    ob_end_clean();
    $rows = \theme_lmslabs\local\uutracker::$rows;
    $ok = $skipped = $failed = 0;
    foreach ($rows as $r) {
        if ($r['state'] === 'bad') {
            $failed++;
        } else if ($r['state'] === 'skip') {
            $skipped++;
        } else {
            $ok++;
        }
    }
    $cir->cleanup(true);
    $data += [
        'done' => true,
        'ok' => $ok, 'skipped' => $skipped, 'failed' => $failed,
        'allok' => !$failed && !$skipped,
        'rows' => array_slice($rows, 0, 300),
        'more' => max(0, count($rows) - 300),
        'stats' => array_map(function ($l) {
            return ['text' => $l];
        }, $process->get_stats()),
        'showrows' => $failed || $skipped,
    ];
} else if ($iid) {
    // Step 2: check the file and choose what to do.
    $cir = new csv_import_reader($iid, 'uploaduser');
    $columns = $cir->get_columns();
    if (!$columns) {
        redirect($url, get_string('uu_expired', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $known = array_merge(['username', 'password', 'firstname', 'lastname', 'email', 'auth', 'suspended', 'city', 'country',
        'lang', 'timezone', 'idnumber', 'institution', 'department', 'phone1', 'phone2', 'address', 'description',
        'deleted', 'oldusername', 'theme', 'maildisplay', 'mailformat', 'emailstop', 'autosubscribe', 'maildigest'],
        array_map(function ($f) {
            return 'profile_field_' . $f->shortname;
        }, $DB->get_records('user_info_field', null, '', 'id, shortname')));
    $isknown = function ($c) use ($known) {
        $c = core_text::strtolower($c);
        return in_array($c, $known) || preg_match('/^(course|group|type|role|enrolperiod|enrolstatus|cohort|sysrole)\d+$/', $c) ||
            in_array($c, ['id', 'idnumber']);
    };
    $cols = [];
    foreach ($columns as $c) {
        $cols[] = ['name' => $c, 'known' => $isknown($c)];
    }
    $have = array_map('core_text::strtolower', $columns);
    $missing = [];
    foreach (['username', 'firstname', 'lastname', 'email'] as $need) {
        if (!in_array($need, $have)) {
            $missing[] = ['name' => $need];
        }
    }
    $cir->init();
    $preview = [];
    $total = 0;
    while ($line = $cir->next()) {
        if ($total < 5) {
            $preview[] = ['cells' => array_map(function ($v) {
                return ['v' => s(core_text::substr((string)$v, 0, 40))];
            }, array_pad($line, count($columns), ''))];
        }
        $total++;
    }
    $cir->close();
    $data += [
        'review' => true,
        'iid' => $iid,
        'cols' => $cols,
        'missing' => $missing,
        'hasmissing' => (bool)$missing,
        'hasunknown' => (bool)array_filter(
            $cols, function ($c) {
            return !$c['known'];
        }
        ),
        'headers' => array_map(function ($c) {
            return ['name' => $c];
        }, $columns),
        'preview' => $preview,
        'total' => $total,
        'totalmsg' => get_string($total === 1 ? 'uu_rows_one' : 'uu_rows', 'theme_lmslabs', $total),
        'haspassword' => in_array('password', $have),
        'canimport' => !$missing && $total > 0,
    ];
} else {
    $data['pick'] = true;
    $data['maxsize'] = display_size(get_max_upload_file_size($CFG->maxbytes));
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/uploadusers', $data);
echo $OUTPUT->footer();
