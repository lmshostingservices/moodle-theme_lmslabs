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
 * Search for the command palette: inside a course it finds that course's activities, people, groups and badges;
 * on the whole site it finds courses and people.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use theme_lmslabs\local\icons;

require_login(null, false);
require_sesskey();
if (isguestuser()) {
    throw new require_login_exception('Guests cannot search');
}
$q = trim(required_param('q', PARAM_TEXT));
$scope = optional_param('scope', 'site', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$PAGE->set_context(context_system::instance());
$items = [];
if (core_text::strlen($q) < 2) {
    echo json_encode(['items' => []]);
    die;
}
$has = function (string $text) use ($q): bool {
    return core_text::strpos(core_text::strtolower($text), core_text::strtolower($q)) !== false;
};
$add = function (string $name, string $group, string $url, string $icon) use (&$items) {
    $items[] = ['n' => $name, 'g' => $group, 'u' => $url, 'svg' => icons::svg($icon)];
};
$icon = ['assign' => 'file-text', 'quiz' => 'file-question', 'forum' => 'message-square', 'page' => 'file-text',
    'url' => 'external-link', 'resource' => 'file-text', 'folder' => 'folder', 'label' => 'file-text'];

if ($scope === 'course' && $courseid && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course, false);
    $ctx = context_course::instance($course->id);
    $modinfo = get_fast_modinfo($course);
    $n = 0;
    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->uservisible || $cm->modname === 'label' || !$has($cm->name)) {
            continue;
        }
        $section = $modinfo->get_section_info($cm->sectionnum);
        $group = get_string('modulename', $cm->modname);
        $add(format_string($cm->name), $group, $cm->url ? $cm->url->out(false) : '', $icon[$cm->modname] ?? 'layers');
        if (in_array($cm->modname, ['assign', 'quiz']) && has_capability("mod/{$cm->modname}:manageoverrides", $cm->context)) {
            $add(
                get_string('ov_title', 'theme_lmslabs') . ': ' . format_string($cm->name), $group,
                (new moodle_url('/theme/lmslabs/overrides.php', ['cmid' => $cm->id]))->out(false), 'clock'
            );
        }
        if (++$n >= 6) {
            break;
        }
    }
    if (has_capability('moodle/course:viewparticipants', $ctx)) {
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $found = 0;
        foreach (get_enrolled_users($ctx, '', 0, "u.id, $fields, u.email", 'u.lastname, u.firstname', 0, 500, true) as $u) {
            if ($has(fullname($u)) || $has($u->email)) {
                $add(
                    fullname($u), get_string('participants'), (new moodle_url(
                    '/theme/lmslabs/profile.php',
                    ['id' => $u->id]
                ))->out(false), 'users'
                );
                if (++$found >= 5) {
                    break;
                }
            }
        }
    }
    if (has_capability('moodle/course:managegroups', $ctx)) {
        foreach (groups_get_all_groups($course->id) as $g) {
            if ($has($g->name)) {
                $add(
                    format_string($g->name), get_string('group'), (new moodle_url(
                    '/theme/lmslabs/groups.php',
                    ['id' => $course->id]
                ))->out(false), 'users-round'
                );
                break;
            }
        }
    }
    if (!empty($CFG->enablebadges) && has_any_capability(['moodle/badges:viewbadges', 'moodle/badges:awardbadge'], $ctx)) {
        foreach ($DB->get_records('badge', ['courseid' => $course->id, 'type' => 2]) as $b) {
            if ($has($b->name)) {
                $add(
                    format_string($b->name), get_string('badges', 'badges'), (new moodle_url(
                    '/theme/lmslabs/coursebadges.php',
                    ['id' => $course->id]
                ))->out(false), 'award'
                );
                break;
            }
        }
    }
} else {
    foreach (core_course_category::search_courses(['search' => $q], ['limit' => 6]) as $c) {
        $add(
            format_string($c->fullname, true, ['context' => context_course::instance($c->id)]),
            get_string('course'), (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false), 'book-open'
        );
    }
    if (has_capability('moodle/user:viewdetails', context_system::instance())) {
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $like1 = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':t1', false);
        $like2 = $DB->sql_like('u.email', ':t2', false);
        $t = '%' . $DB->sql_like_escape($q) . '%';
        foreach ($DB->get_records_sql("SELECT u.id, $fields FROM {user} u WHERE u.deleted = 0 AND u.id <> :g AND ($like1 OR $like2)
                                      ORDER BY u.lastname, u.firstname", ['t1' => $t, 't2' => $t, 'g' => $CFG->siteguest], 0, 5) as $u) {
            $add(
                fullname($u), get_string('user'), (new moodle_url('/theme/lmslabs/profile.php', ['id' => $u->id]))->out(false),
                'users'
            );
        }
    }
}
echo json_encode(['items' => $items]);
