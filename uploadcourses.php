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
 * Upload courses: drop a CSV, check it, choose what to do, import, see what happened.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/course/lib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
require_capability('tool/uploadcourse:use', $context);
$PAGE->set_context($context);
$url = new moodle_url('/theme/lmslabs/uploadcourses.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('uploadcourses', 'tool_uploadcourse'));
$PAGE->set_heading(get_string('uc_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/uploadusers.js'));

$iid = optional_param('iid', 0, PARAM_INT);

if (!empty($_FILES['csv']['tmp_name']) && confirm_sesskey()) {
    $up = $_FILES['csv'];
    $content = is_uploaded_file($up['tmp_name']) ? file_get_contents($up['tmp_name']) : '';
    $first = strtok($content, "\n");
    $delimiter = 'comma';
    foreach (['semicolon' => ';', 'tab' => "\t"] as $name => $ch) {
        if (substr_count($first, $ch) > substr_count($first, ',')) {
            $delimiter = $name;
        }
    }
    $iid = csv_import_reader::get_new_iid('uploadcourse');
    $cir = new csv_import_reader($iid, 'uploadcourse');
    $count = $cir->load_csv_content($content, 'UTF-8', $delimiter);
    if (!$count || $cir->get_error()) {
        redirect($url, get_string('uu_badfile', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url($url, ['iid' => $iid]));
}

$categories = core_course_category::make_categories_list('moodle/course:create');
$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'exampleurl' => (new moodle_url('/admin/tool/uploadcourse/example.csv'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/admin/tool/uploadcourse/index.php'),
    'coursesurl' => (new moodle_url('/theme/lmslabs/manage.php'))->out(false),
    'i' => ['upload' => icons::svg('file-up'), 'check' => icons::svg('check'), 'alert' => icons::svg('circle-alert'),
        'plus' => icons::svg('plus'), 'refresh' => icons::svg('refresh'), 'book' => icons::svg('book-open'),
        'x' => icons::svg('x'), 'download' => icons::svg('download'), 'copy' => icons::svg('layers')],
];

if ($iid && data_submitted() && confirm_sesskey() && optional_param('run', 0, PARAM_BOOL)) {
    $cir = new csv_import_reader($iid, 'uploadcourse');
    $mode = optional_param('mode', 'new', PARAM_ALPHA);
    $modes = ['new' => \tool_uploadcourse_processor::MODE_CREATE_NEW, 'both' => \tool_uploadcourse_processor::MODE_CREATE_OR_UPDATE,
        'update' => \tool_uploadcourse_processor::MODE_UPDATE_ONLY];
    $options = ['mode' => $modes[$mode] ?? $modes['new'],
        'updatemode' => $mode === 'new' ? \tool_uploadcourse_processor::UPDATE_NOTHING
            : \tool_uploadcourse_processor::UPDATE_ALL_WITH_DATA_ONLY,
        'allowdeletes' => false, 'allowrenames' => false, 'allowresets' => false];
    $template = optional_param('template', '', PARAM_TEXT);
    if ($template !== '') {
        $options['templatecourse'] = $template;
    }
    $catid = optional_param('category', 0, PARAM_INT);
    $defaults = $catid && isset($categories[$catid]) ? ['category' => $catid] : [];
    $defaults['visible'] = optional_param('visible', 0, PARAM_BOOL) ? 1 : 0;
    $processor = new \tool_uploadcourse_processor($cir, $options, $defaults);
    if ($errs = $processor->get_errors()) {
        redirect(
            new moodle_url($url, ['iid' => $iid]), implode(' ', array_map('strip_tags', $errs)), null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $tracker = new \theme_lmslabs\local\uctracker(\tool_uploadcourse_tracker::NO_OUTPUT);
    $processor->execute($tracker);
    $rows = array_values($tracker->rows);
    $failed = count(
        array_filter(
        $rows, function ($r) {
        return $r['bad'];
    }
    )
    );
    $cir->cleanup(true);
    $data += [
        'done' => true, 'total' => count($rows), 'ok' => count($rows) - $failed, 'failed' => $failed, 'allok' => !$failed,
        'rows' => array_map(function ($r) {
            $r['url'] = $r['id'] ? (new moodle_url('/course/view.php', ['id' => $r['id']]))->out(false) : null;
            return $r;
        }, $rows),
        'showrows' => (bool)$failed,
    ];
} else if ($iid) {
    $cir = new csv_import_reader($iid, 'uploadcourse');
    $columns = $cir->get_columns();
    if (!$columns) {
        redirect($url, get_string('uu_expired', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $known = ['shortname', 'fullname', 'category', 'idnumber', 'summary', 'format', 'visible', 'startdate', 'enddate',
        'lang', 'theme', 'newsitems', 'showgrades', 'enablecompletion', 'tags', 'templatecourse', 'delete', 'rename', 'reset',
        'backupfile', 'role_student', 'role_teacher', 'enrolment_1', 'enrolment_1_role', 'groupmode', 'maxbytes', 'showreports',
        'summaryformat', 'marker', 'legacyfiles', 'guest', 'password', 'visibleold'];
    $isknown = function ($c) use ($known) {
        $c = core_text::strtolower($c);
        return in_array($c, $known) || preg_match('/^(role_|enrolment_|customfield_|format_)/', $c);
    };
    $cols = array_map(function ($c) use ($isknown) {
        return ['name' => $c, 'known' => $isknown($c)];
    }, $columns);
    $have = array_map('core_text::strtolower', $columns);
    $missing = [];
    foreach (['shortname', 'fullname'] as $need) {
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
    $templates = [];
    foreach (get_courses('all', 'c.fullname ASC', 'c.id, c.shortname, c.fullname') as $c) {
        if ($c->id != SITEID) {
            $templates[] = ['short' => $c->shortname, 'name' => format_string($c->fullname)];
        }
    }
    $data += [
        'review' => true, 'iid' => $iid, 'cols' => $cols, 'missing' => $missing, 'hasmissing' => (bool)$missing,
        'hasunknown' => (bool)array_filter(
            $cols, function ($c) {
            return !$c['known'];
        }
        ),
        'headers' => array_map(function ($c) {
            return ['name' => $c];
        }, $columns),
        'preview' => $preview, 'total' => $total,
        'totalmsg' => get_string($total === 1 ? 'uc_rows_one' : 'uc_rows', 'theme_lmslabs', $total),
        'hascategory' => in_array('category', $have),
        'categories' => array_map(function ($id, $n) {
            return ['id' => $id, 'name' => $n];
        }, array_keys($categories), $categories),
        'templates' => $templates,
        'canimport' => !$missing && $total > 0,
    ];
} else {
    $data['pick'] = true;
    $data['maxsize'] = display_size(get_max_upload_file_size($CFG->maxbytes));
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/uploadcourses', $data);
echo $OUTPUT->footer();
