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
 * Add a course: the essentials and one-click switches, remembered for the next course.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

use theme_lmslabs\local\icons;

// The same simple screen edits an existing course (?id=) or adds a new one (?category=).
$editid = optional_param('id', 0, PARAM_INT);
$editcourse = null;
if ($editid) {
    $editcourse = get_course($editid);
    require_login($editcourse);
    $coursecontext = context_course::instance($editcourse->id);
    require_capability('moodle/course:update', $coursecontext);
    $creatable = core_course_category::make_categories_list('moodle/course:changecategory');
    $current = core_course_category::get($editcourse->category, IGNORE_MISSING, true);
    if ($current && !isset($creatable[$current->id])) {
        $creatable = [$current->id => $current->get_formatted_name()] + $creatable;
    }
    $categoryid = (int)$editcourse->category;
    $prefs = [];
} else {
    require_login(null, false);
    $categoryid = optional_param('category', 0, PARAM_INT);
    $creatable = core_course_category::make_categories_list('moodle/course:create');
    if (!$creatable) {
        require_capability('moodle/course:create', context_system::instance());
    }
    $prefs = json_decode((string)get_user_preferences('theme_lmslabs_addcourse', '{}'), true) ?: [];
    if (!$categoryid || !isset($creatable[$categoryid])) {
        $categoryid = isset($creatable[$prefs['category'] ?? 0]) ? (int)$prefs['category'] : (int)array_key_first($creatable);
    }
    $catcontext = context_coursecat::instance($categoryid);
    require_capability('moodle/course:create', $catcontext);
}

$url = $editcourse ? new moodle_url('/theme/lmslabs/addcourse.php', ['id' => $editcourse->id])
    : new moodle_url('/theme/lmslabs/addcourse.php', ['category' => $categoryid]);
if (!$editcourse) {
    $PAGE->set_context($catcontext);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout($editcourse ? 'incourse' : 'admin');
$PAGE->set_title($editcourse ? get_string('settings') : get_string('addcourse', 'theme_lmslabs'));
$PAGE->set_heading($editcourse ? get_string('ac_settings', 'theme_lmslabs') : get_string('addcourse', 'theme_lmslabs'));
$PAGE->add_body_class('ll-formpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/addcourse.js'));

// Switches and their defaults (site defaults the first time, then whatever was used last).
$moodlecourse = get_config('moodlecourse');
$switchdefs = [
    'visible' => (int)($moodlecourse->visible ?? 1),
    'completion' => (int)($moodlecourse->enablecompletion ?? 1),
    'groups' => 0,
    'showgrades' => (int)($moodlecourse->showgrades ?? 1),
    'activitydates' => (int)($moodlecourse->showactivitydates ?? 1),
    'selfenrol' => 0,
];
$switches = [];
foreach ($switchdefs as $k => $default) {
    $switches[$k] = isset($prefs[$k]) ? (int)$prefs[$k] : $default;
}
if ($editcourse) {
    $selfon = $DB->record_exists('enrol', ['courseid' => $editcourse->id, 'enrol' => 'self', 'status' => ENROL_INSTANCE_ENABLED]);
    $switches = [
        'visible' => (int)$editcourse->visible,
        'completion' => (int)$editcourse->enablecompletion,
        'groups' => (int)($editcourse->groupmode != NOGROUPS),
        'showgrades' => (int)$editcourse->showgrades,
        'activitydates' => (int)$editcourse->showactivitydates,
        'selfenrol' => (int)$selfon,
    ];
}

// AI course format options, shown to teachers when the course uses that format.
$aifopts = [];
$aifkeys = ['showherobanner', 'shownavchevrons', 'showcourseindex', 'displayascards', 'showactivitiesoncards', 'herosticky'];
if ($editcourse && $editcourse->format === \theme_lmslabs\local\aiformat::FORMAT && has_capability('moodle/course:update',
        context_course::instance($editcourse->id))) {
    $fmt = course_get_format($editcourse);
    $defs = $fmt->course_format_options(true);
    $cur = $fmt->get_format_options();
    foreach ($aifkeys as $k) {
        if (empty($defs[$k]['element_attributes'][0])) {
            continue;
        }
        $opts = [];
        foreach ($defs[$k]['element_attributes'][0] as $val => $label) {
            $opts[] = ['value' => $val, 'label' => $label, 'selected' => (int)($cur[$k] ?? 0) === (int)$val];
        }
        $help = '';
        if (get_string_manager()->string_exists($k . '_help', 'format_aicourse')) {
            $help = trim(preg_replace('/\s+/', ' ', strip_tags(get_string($k . '_help', 'format_aicourse'))));
        }
        $aifopts[] = ['key' => $k, 'label' => $defs[$k]['label'], 'help' => $help, 'options' => $opts];
    }
}

$errors = [];
$posted = [];
if (data_submitted() && confirm_sesskey()) {
    $posted = [
        'fullname' => trim(required_param('fullname', PARAM_TEXT)),
        'shortname' => trim(required_param('shortname', PARAM_TEXT)),
        'category' => required_param('categoryid', PARAM_INT),
        'startdate' => optional_param('startdate', '', PARAM_TEXT),
        'enddate' => optional_param('enddate', '', PARAM_TEXT),
        'duration' => optional_param('duration', 0, PARAM_INT),
        'summary' => trim(optional_param('summary', '', PARAM_TEXT)),
        'idnumber' => trim(optional_param('idnumber', '', PARAM_TEXT)),
        'lang' => optional_param('lang', '', PARAM_LANG),
    ];
    foreach (array_keys($switchdefs) as $k) {
        $switches[$k] = optional_param('sw_' . $k, 0, PARAM_BOOL) ? 1 : 0;
    }
    if ($posted['fullname'] === '') {
        $errors['fullname'] = get_string('required');
    }
    if ($posted['shortname'] === '') {
        $errors['shortname'] = get_string('required');
    } else if ($DB->record_exists_select('course', 'shortname = ? AND id <> ?', [$posted['shortname'], $editid])) {
        $errors['shortname'] = get_string('ac_shortnametaken', 'theme_lmslabs');
    }
    if ($posted['idnumber'] !== '' && $DB->record_exists_select('course', 'idnumber = ? AND id <> ?',
            [$posted['idnumber'], $editid])) {
        $errors['idnumber'] = get_string('courseidnumbertaken', 'error', s($posted['idnumber']));
    }
    if (!isset($creatable[$posted['category']])) {
        $errors['category'] = get_string('required');
    }
    $start = $posted['startdate'] ? strtotime($posted['startdate'] . ' 00:00') : usergetmidnight(time());
    $end = 0;
    if ($posted['enddate'] !== '') {
        $end = strtotime($posted['enddate'] . ' 23:59');
        if ($end && $end <= $start) {
            $errors['enddate'] = get_string('enddatebeforestartdate', 'error');
        }
    }
    if (!$errors && $editcourse) {
        $summary = $editcourse->summary;
        if ($posted['summary'] !== trim(html_to_text($editcourse->summary, 0, false))) {
            $summary = $posted['summary'] !== '' ? '<p>' . s($posted['summary']) . '</p>' : '';
        }
        $update = (object)[
            'id' => $editcourse->id,
            'fullname' => $posted['fullname'],
            'shortname' => $posted['shortname'],
            'category' => $posted['category'],
            'idnumber' => $posted['idnumber'],
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
            'startdate' => $start,
            'enddate' => $end,
            'visible' => $switches['visible'],
            'enablecompletion' => $switches['completion'],
            'groupmode' => $switches['groups'] ? ($editcourse->groupmode ?: SEPARATEGROUPS) : NOGROUPS,
            'showgrades' => $switches['showgrades'],
            'showactivitydates' => $switches['activitydates'],
            'lang' => $posted['lang'],
        ];
        update_course($update);
        if ($aifopts) {
            $fopt = new stdClass();
            foreach ($aifopts as $row) {
                $fopt->{$row['key']} = optional_param('aif_' . $row['key'], 0, PARAM_INT);
            }
            course_get_format($editcourse->id)->update_course_format_options($fopt);
        }
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            \theme_lmslabs\local\courseimage::store($editcourse->id, $_FILES['image']['tmp_name'], $_FILES['image']['name']);
        }
        if (enrol_is_enabled('self')) {
            $self = enrol_get_plugin('self');
            $instance = $DB->get_record('enrol', ['courseid' => $editcourse->id, 'enrol' => 'self'], '*', IGNORE_MULTIPLE);
            if ($switches['selfenrol'] && !$instance) {
                $self->add_instance(get_course($editcourse->id), ['status' => ENROL_INSTANCE_ENABLED]);
            } else if ($instance) {
                $self->update_status($instance, $switches['selfenrol'] ? ENROL_INSTANCE_ENABLED : ENROL_INSTANCE_DISABLED);
            }
        }
        redirect(
            new moodle_url('/course/view.php', ['id' => $editcourse->id]), get_string('changessaved'), null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if (!$errors && !$editcourse) {
        require_capability('moodle/course:create', context_coursecat::instance($posted['category']));
        $data = (object)[
            'fullname' => $posted['fullname'],
            'shortname' => $posted['shortname'],
            'category' => $posted['category'],
            'idnumber' => $posted['idnumber'],
            'summary' => $posted['summary'] !== '' ? '<p>' . s($posted['summary']) . '</p>' : '',
            'summaryformat' => FORMAT_HTML,
            'startdate' => $start,
            'enddate' => $end,
            'visible' => $switches['visible'],
            'enablecompletion' => $switches['completion'],
            'groupmode' => $switches['groups'] ? SEPARATEGROUPS : NOGROUPS,
            'groupmodeforce' => 0,
            'showgrades' => $switches['showgrades'],
            'showactivitydates' => $switches['activitydates'],
            'lang' => $posted['lang'],
        ];
        if (\theme_lmslabs\local\aiformat::enabled()) {
            $data->format = \theme_lmslabs\local\aiformat::FORMAT;
        }
        $course = create_course($data);
        $context = context_course::instance($course->id);

        // Course image.
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $name = clean_filename($_FILES['image']['name']);
            if (file_mimetype_in_typegroup(mimeinfo('type', $name), 'web_image')) {
                get_file_storage()->create_file_from_pathname([
                    'contextid' => $context->id, 'component' => 'course', 'filearea' => 'overviewfiles',
                    'itemid' => 0, 'filepath' => '/', 'filename' => $name,
                ], $_FILES['image']['tmp_name']);
            }
        }

        // Self enrolment on or off.
        if ($switches['selfenrol'] && enrol_is_enabled('self')) {
            $self = enrol_get_plugin('self');
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', IGNORE_MULTIPLE);
            if ($instance) {
                $self->update_status($instance, ENROL_INSTANCE_ENABLED);
            } else {
                $self->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
            }
        }

        // The creator teaches the course they just made, if they couldn't otherwise see it.
        if (!is_enrolled($context) && !has_capability('moodle/course:view', $context)) {
            $manual = enrol_get_plugin('manual');
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
            $teacher = $DB->get_record('role', ['shortname' => 'editingteacher']);
            if ($manual && $instance && $teacher) {
                $manual->enrol_user($instance, $USER->id, $teacher->id);
            }
        }

        // Remember everything except the names for next time.
        set_user_preference(
            'theme_lmslabs_addcourse', json_encode(
            $switches + [
            'category' => $posted['category'],
            'duration' => $posted['duration'],
            'lang' => $posted['lang'],
        ]
        )
        );

        $next = optional_param('next', 'open', PARAM_ALPHA);
        $target = $next === 'another' ? new moodle_url('/theme/lmslabs/addcourse.php', ['category' => $posted['category']])
            : new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect(
            $target, get_string('ac_created', 'theme_lmslabs', format_string($course->fullname)), null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$categories = [];
$selectedcat = $posted['category'] ?? $categoryid;
foreach ($creatable as $id => $name) {
    $categories[] = ['id' => $id, 'name' => $name, 'selected' => $id == $selectedcat];
}
$duration = $editcourse ? -1 : (int)($posted['duration'] ?? ($prefs['duration'] ?? 0));
$durations = [];
foreach ([0 => 'ac_noend', 3 => 'ac_3m', 6 => 'ac_6m', 12 => 'ac_12m', 24 => 'ac_24m'] as $months => $str) {
    $durations[] = ['months' => $months, 'label' => get_string($str, 'theme_lmslabs'), 'selected' => $months === $duration];
}
$langs = [['value' => '', 'label' => get_string('ac_anylang', 'theme_lmslabs'), 'selected' => true]];
$installed = get_string_manager()->get_list_of_translations();
$lang = $posted['lang'] ?? ($editcourse ? $editcourse->lang : ($prefs['lang'] ?? ''));
if (count($installed) > 1) {
    foreach ($installed as $code => $label) {
        $langs[] = ['value' => $code, 'label' => $label, 'selected' => $code === $lang];
    }
}

$switchrows = [];
$icons = ['visible' => 'eye', 'completion' => 'circle-check-big', 'groups' => 'users-round', 'showgrades' => 'chart-column',
    'activitydates' => 'calendar-days', 'selfenrol' => 'door-open'];
foreach ($switches as $k => $on) {
    if ($k === 'selfenrol' && !enrol_is_enabled('self')) {
        continue;
    }
    $switchrows[] = [
        'key' => $k, 'on' => (bool)$on, 'icon' => icons::svg($icons[$k]),
        'label' => get_string('ac_sw_' . $k, 'theme_lmslabs'),
        'desc' => get_string('ac_sw_' . $k . '_desc', 'theme_lmslabs'),
    ];
}
$e = [];
foreach ($errors as $k => $msg) {
    $e[$k] = ['msg' => $msg];
}

$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'fullname' => $posted['fullname'] ?? ($editcourse->fullname ?? ''),
    'shortname' => $posted['shortname'] ?? ($editcourse->shortname ?? ''),
    'idnumber' => $posted['idnumber'] ?? ($editcourse->idnumber ?? ''),
    'summary' => $posted['summary'] ?? ($editcourse ? trim(html_to_text($editcourse->summary, 0, false)) : ''),
    'startdate' => $posted['startdate'] ?? date('Y-m-d', !empty($editcourse->startdate) ? $editcourse->startdate : usergetmidnight(time())),
    'enddate' => $posted['enddate'] ?? (!empty($editcourse->enddate) ? date('Y-m-d', $editcourse->enddate) : ''),
    'editing' => (bool)$editcourse,
    'currentimage' => $editcourse ? \theme_lmslabs\local\courseimage::url($editcourse) : null,
    'deleteurl' => $editcourse && has_capability('moodle/course:delete', context_course::instance($editcourse->id))
        ? (new moodle_url('/course/delete.php', ['id' => $editcourse->id]))->out(false) : null,
    'categories' => $categories,
    'durations' => $durations,
    'langs' => $langs,
    'haslangs' => count($langs) > 1,
    'switches' => $switchrows,
    'aifopts' => $aifopts,
    'hasaifopts' => (bool)$aifopts,
    'remembered' => (bool)$prefs,
    'aiformat' => \theme_lmslabs\local\aiformat::enabled(),
    'e' => $e,
    'haserrors' => (bool)$errors,
    'year' => date('Y'),
    'cancelurl' => ($editcourse ? new moodle_url('/course/view.php', ['id' => $editcourse->id])
        : new moodle_url('/theme/lmslabs/manage.php'))->out(false),
    'uploadurl' => (new moodle_url('/admin/tool/uploadcourse/index.php'))->out(false),
    'restoreurl' => (new moodle_url('/backup/restorefile.php', ['contextid' => context_system::instance()->id]))->out(false),
    'classicurl' => $editcourse ? \theme_lmslabs\local\router::classic('/course/edit.php', ['id' => $editcourse->id])
        : \theme_lmslabs\local\router::classic('/course/edit.php', ['category' => $categoryid]),
    'i' => [
        'book' => icons::svg('book-open'), 'image' => icons::svg('image'), 'calendar' => icons::svg('calendar-days'),
        'toggle' => icons::svg('toggle'), 'list' => icons::svg('list'), 'chev' => icons::svg('chevron-down'),
        'check' => icons::svg('check'), 'upload' => icons::svg('file-up'), 'restore' => icons::svg('archive-restore'),
        'sparkles' => icons::svg('sparkles'), 'folder' => icons::svg('folder'), 'wand' => icons::svg('wand'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/addcourse', $data);
echo $OUTPUT->footer();
