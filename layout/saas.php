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
 * Main layout: left sidebar app shell around Boost's page content.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\request;

$addblockbutton = $OUTPUT->addblockbutton();
$blockdraweropen = isloggedin() && get_user_preferences('drawer-open-block') == true;
if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
// Theme tool pages inside a course (reset, completion, backup...) use the full width. Calling
// core_course_drawer() queues the course index scripts, so it is skipped there rather than discarded.
// LMS Labs plugin tool pages (intakes, recertification, prerequisites...) are tool pages too, even when
// they are opened for one course, so they also get the full width and no course index.
$toolpath = $PAGE->url->get_path(false);
$plugintool = (bool) preg_match(
    '~/(?:local/(?:groupmanager|courseavailabilitydelay|recertify|completionsuspend|emailstudents|trainingmatrix|' .
    'trainingpathways|student_export|mediaoptimiser|cohortbranding|aiquizremedial|aicoursebuilder|clientportal)|' .
    'enrol/prerequisite)/~',
    $toolpath
);
$courseindex = (strpos($toolpath, '/theme/lmslabs/') !== false || $plugintool) ? '' : core_course_drawer();
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$course = $PAGE->course;
$incourse = $course && $course->id != SITEID;

// Learners inside a course get the immersive view: no sidebar, just a way back to My courses.
// (A teacher in Student view counts as a learner, since the switched role has no editing rights.)
$immersive = $incourse && isloggedin() && !isguestuser() &&
    !has_any_capability(['moodle/course:update', 'moodle/course:manageactivities'], context_course::instance($course->id));

// Course formats that drive Boost's own course index drawer (our AI course format moves its controls,
// opens and closes it, and lays the course out around it) get that drawer exactly as Boost renders it,
// instead of the index being folded into our sidebar.
$formatsync = $incourse && $courseindex && in_array($course->format, theme_lmslabs_drawer_formats(), true);
$courseindexopen = $formatsync && get_user_preferences('drawer-open-index', true) == true;

$extraclasses = ['uses-drawers', 'll-app'];
if ($immersive) {
    $extraclasses[] = 'll-immersive';
}
if ($formatsync) {
    $extraclasses[] = 'll-formatsync';
}
// LMS Labs plugin tool pages print plain Moodle tables; this class lets the theme give them the full width and a card edge.
if ($plugintool) {
    $extraclasses[] = 'll-plugintool';
}
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

// Secondary (tab) navigation and the tertiary selector, rendered with whichever core template this version ships.
$newnav = file_exists($CFG->libdir . '/templates/secondarymoremenu.mustache');
$secondaryhtml = '';
$overflowhtml = '';
// Learners don't see Competencies in the course tabs.
if ($immersive) {
    foreach (['competencies', 'coursecompetencies'] as $key) {
        if ($node = $PAGE->secondarynav->find($key, null)) {
            $node->remove();
        }
    }
}

// Site administration's category tabs are replaced by the sidebar.
$sitelevel = in_array($PAGE->context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_USER]) &&
    in_array($PAGE->pagelayout, ['admin', 'report', 'standard', 'mydashboard']);
// The theme's own course pages have the course menu in the sidebar already.
$ourpage = strpos($PAGE->url->get_path(false), '/theme/lmslabs/') !== false;
// Course-level tabs (Course, Settings, Participants...) repeat the sidebar for teachers; activity tabs stay.
$coursetabs = $incourse && !$immersive && $PAGE->context->contextlevel == CONTEXT_COURSE;
if ($PAGE->has_secondary_navigation() && !$sitelevel && !$ourpage && !$coursetabs) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $moredata = $moremenu->export_for_template($OUTPUT);
    if (!empty($moredata['nodearray']) || !empty($moredata['nodecollection'])) {
        $secondaryhtml = $OUTPUT->render_from_template($newnav ? 'core/secondarymoremenu' : 'core/moremenu', $moredata);
    }
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        if ($newnav) {
            $selectmenu = new \core\output\select_menu('tertiarynavigation', $overflowdata->urls, $overflowdata->selected);
            $selectmenu->set_label($overflowdata->label, $overflowdata->labelattributes);
            $overflowhtml = $OUTPUT->render_from_template(
                'core/tertiary_navigation_selector',
                $selectmenu->export_for_template($OUTPUT)
            );
        } else {
            $overflowhtml = $OUTPUT->render_from_template('core/url_select', $overflowdata->export_for_template($OUTPUT));
        }
    }
}

// Site-level pages use the site name as their heading; show the page's own name instead.
if ($PAGE->heading === format_string($SITE->fullname) || $PAGE->heading === $SITE->fullname) {
    $items = $PAGE->navbar->get_items();
    $last = $items ? end($items) : null;
    if ($last && $last->text !== '' && $PAGE->pagelayout !== 'frontpage') {
        $PAGE->set_heading(is_string($last->text) ? $last->text : (string)$last->text);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

// Sidebar navigation.
$nav = (new \theme_lmslabs\local\nav($PAGE))->export();
// Site managers always get the admin menu icon in the top bar, in the same place on every page.
$adminmenu = isloggedin() && !isguestuser() ? (new \theme_lmslabs\local\nav($PAGE))->admin_menu() : [];
if ($courseindex && !$formatsync) {
    foreach ($nav['groups'] as $k => $group) {
        if ($group['key'] === 'course') {
            $nav['groups'][$k]['groupcourseindex'] = $courseindex;
        }
    }
}

// Top bar actions.
$studentview = null;
if ($incourse && isloggedin() && !isguestuser()) {
    $coursecontext = context_course::instance($course->id);
    $returnurl = $PAGE->url->out_as_local_url(false);
    if (is_role_switched($course->id)) {
        $studentview = [
            'active' => true,
            'url' => (new moodle_url(
                '/course/switchrole.php', ['id' => $course->id, 'switchrole' => 0,
                'sesskey' => sesskey(), 'returnurl' => $returnurl]
            ))->out(false),
        ];
    } else if (has_capability('moodle/role:switchroles', $coursecontext)) {
        $studentroles = get_archetype_roles('student');
        $switchable = get_switchable_roles($coursecontext);
        foreach ($studentroles as $role) {
            if (isset($switchable[$role->id])) {
                $studentview = [
                    'active' => false,
                    'url' => (new moodle_url(
                        '/course/switchrole.php', ['id' => $course->id, 'switchrole' => $role->id,
                        'sesskey' => sesskey(), 'returnurl' => $returnurl]
                    ))->out(false),
                ];
                break;
            }
        }
    }
}

// One course format in this theme.
$formatlock = null;
if ($PAGE->url->get_path(false) === '/course/edit.php') {
    $formatlock = theme_lmslabs_course_format();
}

$canaccent = has_capability('theme/lmslabs:managebrand', context_system::instance());
$swatches = [];
if ($canaccent) {
    $current = theme_lmslabs_accent();
    foreach (theme_lmslabs_accent_presets() as $hex => $label) {
        $swatches[] = ['hex' => $hex, 'label' => $label, 'selected' => $hex === $current];
    }
}

$logourl = theme_lmslabs_user_logo_url();
$sitename = format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]);

$jsconfig = [
    'palette' => $nav['palette'],
    'format' => $formatlock,
    'accentUrl' => (new moodle_url('/theme/lmslabs/ajax/accent.php'))->out(false),
    'sesskey' => sesskey(),
    'wwwroot' => $CFG->wwwroot,
    'courseSearch' => (new moodle_url('/course/search.php'))->out(false),
    'settingsSearch' => is_siteadmin() ? (new moodle_url('/admin/search.php'))->out(false) : '',
    'searchAjax' => (new moodle_url('/theme/lmslabs/ajax/search.php'))->out(false),
    'course' => ($PAGE->course && $PAGE->course->id != SITEID && isloggedin() && !isguestuser())
        ? ['id' => (int)$PAGE->course->id, 'name' => format_string(
            $PAGE->course->shortname, true,
            ['context' => context_course::instance($PAGE->course->id), 'escape' => false]
        )] : null,
    'str' => [
        'searchcourses' => get_string('searchcoursesfor', 'theme_lmslabs'),
        'searchsettings' => get_string('searchsettingsfor', 'theme_lmslabs'),
        'noresults' => get_string('noresults', 'theme_lmslabs'),
        'scopecourse' => get_string('palette_scope_course', 'theme_lmslabs'),
        'scopesite' => get_string('palette_scope_site', 'theme_lmslabs'),
        'placeholdercourse' => get_string('palette_placeholder_course', 'theme_lmslabs'),
        'scopehint' => get_string('palette_scope_hint', 'theme_lmslabs'),
        'searching' => get_string('palette_searching', 'theme_lmslabs'),
        'saved' => get_string('accentsaved', 'theme_lmslabs'),
        'savefailed' => get_string('accentfailed', 'theme_lmslabs'),
        'drop_click' => get_string('drop_click', 'theme_lmslabs'),
        'drop_choose' => get_string('drop_choose', 'theme_lmslabs'),
        'drop_or' => get_string('drop_or', 'theme_lmslabs'),
        'drop_more' => get_string('drop_more', 'theme_lmslabs'),
    ],
];

// On Moodle's own screen (?classic=1) for a page that has a Premium SaaS version, offer the way back.
$classicback = null;
if (optional_param('classic', 0, PARAM_BOOL)) {
    $premium = \theme_lmslabs\local\router::premium($PAGE->url->get_path(false), request::query());
    if ($premium) {
        $classicback = ['url' => $premium->out(false), 'icon' => icons::svg('sparkles')];
    }
}

// Field tips for common Moodle form fields that have no help of their own.
$tips = [];
foreach (get_string_manager()->load_component_strings('theme_lmslabs', current_language()) as $key => $text) {
    if (strpos($key, 'tip_') === 0) {
        $tips[substr($key, 4)] = $text;
    }
}
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/tips.js'));

$templatecontext = [
    'classicback' => $classicback,
    'pagehero' => \theme_lmslabs\local\pagehero::for_page($PAGE),
    'ourtitle' => ($ourpage || strpos($toolpath, '/local/aiquizremedial/insights.php') !== false) && $incourse ? [
        'title' => strpos($toolpath, '/local/aiquizremedial/') !== false
            ? get_string('reporttitle', 'local_aiquizremedial') : $PAGE->heading,
        'sub' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)])] : null,
    'today' => $PAGE->pagetype === 'my-index' ? \theme_lmslabs\local\today::export() : null,
    'adminmenu' => $adminmenu ? ['groups' => $adminmenu] : null,
    'tipsconfig' => json_encode($tips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES),
    'sitename' => $sitename,
    'siteinitial' => core_text::strtoupper(core_text::substr($sitename, 0, 1)),
    'logourl' => $logourl,
    'contact' => theme_lmslabs_contact(),
    'homeurl' => (new moodle_url('/my/'))->out(false),
    'output' => $OUTPUT,
    'accentcss' => theme_lmslabs_user_accent_css(),
    'fontcss' => theme_lmslabs_user_font_url(),
    'navgroups' => $nav['groups'],
    'jsconfig' => json_encode($jsconfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES),
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'blockdraweropen' => $blockdraweropen,
    'incourse' => $incourse,
    'secondaryhtml' => $secondaryhtml,
    'overflowhtml' => $overflowhtml,
    'usermenu' => $primarymenu['user'],
    'um' => isloggedin() && !isguestuser() ? \theme_lmslabs\local\usermenu::export($primarymenu['user']) : null,
    'langmenu' => $primarymenu['lang'],
    'isloggedin' => isloggedin() && !isguestuser(),
    'loginurl' => get_login_url(),
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'editswitch' => $PAGE->user_allowed_editing() ? $OUTPUT->edit_switch() : '',
    'studentview' => $studentview,
    'immersive' => $immersive,
    'formatsync' => $formatsync,
    'courseindex' => $formatsync ? $courseindex : false,
    'courseindexopen' => $courseindexopen,
    'mycoursesurl' => (new moodle_url('/theme/lmslabs/courses.php'))->out(false),
    'dashurl' => (new moodle_url('/my/'))->out(false),
    'dashactive' => in_array($PAGE->pagetype, ['my-index'], true),
    'coursesactive' => strpos($PAGE->url->get_path(false), '/theme/lmslabs/courses.php') !== false,
    'canaccent' => $canaccent,
    'swatches' => $swatches,
    'accent' => theme_lmslabs_accent(),
    'navbarplugins' => $OUTPUT->navbar_plugin_output(),
    'breadcrumb' => $OUTPUT->navbar(),
    'promo' => \theme_lmslabs\local\promo::export(),
    'setupcard' => class_exists('\\theme_lmslabs\\local\\getstarted') ? \theme_lmslabs\local\getstarted::card() : false,
    'i' => [
        'menu' => icons::svg('menu'),
        'search' => icons::svg('search'),
        'collapse' => icons::svg('panel-left'),
        'panelopen' => icons::svg('panel-left-open'),
        'panelclose' => icons::svg('panel-left-close'),
        'blocks' => icons::svg('panel-right'),
        'tips' => icons::svg('lightbulb'),
        'admin' => icons::svg('shield-check'),
        'dash' => icons::svg('layout-dashboard'),
        'courses' => icons::svg('book-open'),
        'mail' => icons::svg('mail'),
        'phone' => icons::svg('phone'),
        'eye' => icons::svg('eye'),
        'undo' => icons::svg('undo-2'),
        'palette' => icons::svg('palette'),
        'pipette' => icons::svg('pipette'),
        'check' => icons::svg('check'),
        'chevron' => icons::svg('chevron-down', 'll-ico ll-chev'),
        'x' => icons::svg('x'),
        'command' => icons::svg('command'),
        'arrow' => icons::svg('arrow-up-right'),
        'folder' => icons::svg('folder-open'),
        'back' => icons::svg('arrow-left'),
    ],
];

echo $OUTPUT->render_from_template('theme_lmslabs/saas', $templatecontext);
