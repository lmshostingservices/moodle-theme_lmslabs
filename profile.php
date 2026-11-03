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
 * Profile: who someone is, their courses and progress, badges, and one-click actions.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/badgeslib.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\compat;

require_login(null, false);
$id = optional_param('id', $USER->id, PARAM_INT);
$user = core_user::get_user($id, '*', MUST_EXIST);
if ($user->deleted || isguestuser($user)) {
    throw new moodle_exception('invaliduser');
}
$isme = $user->id == $USER->id;
if (!$isme && !compat::can_view_profile($user)) {
    throw new required_capability_exception(context_user::instance($user->id), 'moodle/user:viewdetails', 'nopermissions', '');
}
$usercontext = context_user::instance($user->id);
$sys = context_system::instance();

$url = new moodle_url('/theme/lmslabs/profile.php', $isme ? [] : ['id' => $user->id]);
$PAGE->set_context($sys);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(fullname($user));
$PAGE->set_heading(fullname($user));
$PAGE->add_body_class('ll-profilepage');

$details = compat::user_details(
    $user, ['email', 'city', 'country', 'description', 'firstaccess', 'lastaccess',
    'institution', 'department', 'phone1', 'phone2', 'customfields', 'interests', 'profileimageurl']
) ?? [];

// Courses in common (or all of theirs when the viewer may see them), with progress for those the viewer teaches.
$courses = [];
$mine = $isme ? null : enrol_get_all_users_courses($USER->id, true);
foreach (enrol_get_all_users_courses($user->id, true, 'id, fullname, shortname, enablecompletion', 'fullname ASC') as $c) {
    $ctx = context_course::instance($c->id);
    $teaches = has_capability('moodle/grade:viewall', $ctx);
    if (!$isme && !isset($mine[$c->id]) && !has_capability('moodle/course:view', $ctx) && !$teaches) {
        continue;
    }
    $roles = get_user_roles($ctx, $user->id, false);
    $pct = ($isme || $teaches) ? \core_completion\progress::get_course_progress_percentage($c, $user->id) : null;
    $courses[] = [
        'name' => format_string($c->fullname, true, ['context' => $ctx]),
        'url' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
        'role' => $roles ? role_get_name(reset($roles), $ctx) : '',
        'progress' => $pct === null ? null : (int)round($pct),
        'gradesurl' => ($isme || $teaches) ? (new moodle_url(
            '/grade/report/user/index.php', ['id' => $c->id,
            'userid' => $user->id]
        ))->out(false) : null,
        'image' => \theme_lmslabs\local\courseimage::url($c),
        'tint' => $c->id % 6,
    ];
}

$badges = [];
if (!empty($CFG->enablebadges)) {
    foreach (badges_get_user_badges($user->id, 0, 0, 12, '', !$isme) as $b) {
        $ctxid = $b->type == BADGE_TYPE_COURSE && $b->courseid ? context_course::instance($b->courseid)->id : $sys->id;
        $badges[] = ['name' => format_string($b->name),
            'image' => moodle_url::make_pluginfile_url($ctxid, 'badges', 'badgeimage', $b->id, '/', 'f3', false)->out(false),
            'url' => (new moodle_url('/badges/badge.php', ['hash' => $b->uniquehash]))->out(false)];
    }
}

$facts = [];
$add = function ($icon, $label, $value, $href = null) use (&$facts) {
    if ($value !== null && $value !== '') {
        $facts[] = ['icon' => icons::svg($icon), 'label' => $label, 'value' => $value, 'href' => $href];
    }
};
$add('mail', get_string('email'), $details['email'] ?? '', !empty($details['email']) ? 'mailto:' . $details['email'] : null);
$add('phone', get_string('phone1'), $details['phone1'] ?? '', !empty($details['phone1']) ? 'tel:' . $details['phone1'] : null);
$add('phone', get_string('phone2'), $details['phone2'] ?? '', !empty($details['phone2']) ? 'tel:' . $details['phone2'] : null);
$add('briefcase', get_string('institution'), $details['institution'] ?? '');
$add('users-round', get_string('department'), $details['department'] ?? '');
$place = trim(($details['city'] ?? '') . (!empty($details['country']) ? ', ' . get_string($details['country'], 'countries') : ''), ', ');
$add('map-pin', get_string('pf_location', 'theme_lmslabs'), $place);
foreach ($details['customfields'] ?? [] as $cf) {
    $add('list', format_string($cf['name']), trim(strip_tags((string)($cf['displayvalue'] ?? $cf['value'] ?? ''))));
}

$canedit = ($isme && has_capability('moodle/user:editownprofile', $usercontext)) || (!$isme && has_capability('moodle/user:update', $sys));
$actions = [];
if (!$isme && !empty($CFG->messaging)) {
    $actions[] = ['label' => get_string('message', 'message'), 'icon' => icons::svg('send'), 'primary' => true,
        'url' => (new moodle_url('/theme/lmslabs/messages.php', ['id' => $user->id]))->out(false)];
}
if ($canedit) {
    $actions[] = ['label' => get_string('editmyprofile'), 'icon' => icons::svg('pencil'),
        'url' => ($isme ? new moodle_url('/user/edit.php') : new moodle_url(
            '/user/editadvanced.php', ['id' => $user->id,
            'course' => SITEID]
        ))->out(false)];
}
if ($isme) {
    $actions[] = ['label' => get_string('preferences'), 'icon' => icons::svg('settings'),
        'url' => (new moodle_url('/theme/lmslabs/prefs.php'))->out(false)];
}
if (!$isme && has_capability('moodle/user:loginas', $sys) && !is_siteadmin($user)) {
    $actions[] = ['label' => get_string('loginas'), 'icon' => icons::svg('log-in'),
        'url' => (new moodle_url('/course/loginas.php', ['id' => SITEID, 'user' => $user->id, 'sesskey' => sesskey()]))->out(false)];
}

$data = [
    'name' => fullname($user),
    'picture' => $OUTPUT->user_picture($user, ['size' => 160, 'link' => false, 'class' => 'll-pr-pic']),
    'sub' => trim(($details['institution'] ?? '') . ($place ? ' · ' . $place : ''), ' ·'),
    'isme' => $isme,
    'online' => $user->lastaccess > time() - 300,
    'lastaccess' => $user->lastaccess ? get_string(
        'pr_lastseen', 'theme_lmslabs',
        implode(' ', array_slice(explode(' ', format_time(time() - $user->lastaccess)), 0, 2))
    ) : get_string('never'),
    'since' => $user->firstaccess ? get_string(
        'pr_since', 'theme_lmslabs', userdate(
        $user->firstaccess,
        get_string('strftimemonthyear', 'langconfig')
    )
    ) : '',
    'description' => !empty($details['description']) ? format_text($details['description'], FORMAT_HTML) : '',
    'interests' => array_map(function ($t) {
        return ['tag' => $t];
    }, array_filter(array_map('trim', explode(',', $details['interests'] ?? '')))),
    'facts' => $facts,
    'hasfacts' => (bool)$facts,
    'courses' => $courses,
    'hascourses' => (bool)$courses,
    'coursecount' => count($courses),
    'badges' => $badges,
    'hasbadges' => (bool)$badges,
    'actions' => $actions,
    'classicurl' => \theme_lmslabs\local\router::classic('/user/profile.php', ['id' => $user->id]),
    'i' => ['book' => icons::svg('book-open'), 'award' => icons::svg('award'), 'user' => icons::svg('user-round'),
        'clock' => icons::svg('clock'), 'arrow' => icons::svg('arrow-up-right')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/profile', $data);
echo $OUTPUT->footer();
