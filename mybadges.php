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
 * My badges: a trophy shelf of what you've earned, and what you can earn next in your courses.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
if (empty($CFG->enablebadges)) {
    throw new moodle_exception('badgesdisabled', 'badges');
}
if (isguestuser()) {
    throw new require_login_exception('Guests have no badges');
}
$url = new moodle_url('/theme/lmslabs/mybadges.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mybadges', 'theme_lmslabs'));
$PAGE->set_heading(get_string('mybadges', 'theme_lmslabs'));
$PAGE->add_body_class('ll-badgespage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/mybadges.js'));

$image = function (int $badgeid, int $contextid): string {
    return moodle_url::make_pluginfile_url($contextid, 'badges', 'badgeimage', $badgeid, '/', 'f3', false)->out(false);
};
$contextfor = function ($b) {
    return $b->type == BADGE_TYPE_COURSE && $b->courseid ? context_course::instance($b->courseid)->id
        : context_system::instance()->id;
};

$earned = [];
$earnedids = [];
$courses = enrol_get_all_users_courses($USER->id, true, 'id, fullname');
foreach (badges_get_user_badges($USER->id) as $b) {
    $earnedids[$b->id] = true;
    $issued = $b->dateissued;
    $earned[] = [
        'id' => $b->id,
        'name' => format_string($b->name),
        'description' => trim(html_to_text(format_text($b->description, FORMAT_HTML), 200, false)),
        'image' => $image((int)$b->id, $contextfor($b)),
        'course' => $b->courseid && isset($courses[$b->courseid]) ? format_string($courses[$b->courseid]->fullname) : '',
        'when' => userdate($issued, get_string('strftimedate', 'langconfig')),
        'isnew' => $issued > time() - 14 * DAYSECS,
        'expires' => !empty($b->dateexpire) ? userdate($b->dateexpire, get_string('strftimedate', 'langconfig')) : '',
        'url' => (new moodle_url('/badges/badge.php', ['hash' => $b->uniquehash]))->out(false),
        'download' => (new moodle_url('/badges/badge.php', ['hash' => $b->uniquehash, 'bake' => 1]))->out(false),
        'public' => !empty($b->visible),
    ];
}

// Badges you could earn next: active badges in your courses and on the site that you don't have yet.
$available = [];
$courseids = array_keys($courses);
[$insql, $params] = $courseids ? $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED) : ['= -1', []];
$params['site'] = BADGE_TYPE_SITE;
$params['course'] = BADGE_TYPE_COURSE;
$candidates = $DB->get_records_select(
    'badge', "status IN (1, 3) AND (type = :site OR (type = :course AND courseid $insql))",
    $params, 'type DESC, name ASC', 'id, name, description, type, courseid', 0, 24
);
foreach ($candidates as $b) {
    if (isset($earnedids[$b->id])) {
        continue;
    }
    $available[] = [
        'name' => format_string($b->name),
        'description' => trim(html_to_text(format_text($b->description, FORMAT_HTML), 160, false)),
        'image' => $image((int)$b->id, $contextfor($b)),
        'course' => $b->courseid && isset($courses[$b->courseid]) ? format_string($courses[$b->courseid]->fullname)
            : get_string('mb_sitebadge', 'theme_lmslabs'),
        'courseurl' => $b->courseid ? (new moodle_url('/course/view.php', ['id' => $b->courseid]))->out(false) : null,
    ];
}

$data = [
    'earned' => $earned,
    'hasearned' => (bool)$earned,
    'count' => count($earned),
    'headline' => get_string(count($earned) === 1 ? 'mb_count_one' : 'mb_count', 'theme_lmslabs', count($earned)),
    'available' => $available,
    'hasavailable' => (bool)$available,
    'backpackurl' => !empty($CFG->badges_allowexternalbackpack)
        ? (new moodle_url('/badges/mybackpack.php'))->out(false) : null,
    'prefsurl' => (new moodle_url('/badges/preferences.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/badges/mybadges.php'),
    'copied' => get_string('mb_copied', 'theme_lmslabs'),
    'i' => [
        'award' => icons::svg('award'), 'share' => icons::svg('share'), 'download' => icons::svg('download'),
        'link' => icons::svg('external-link'), 'target' => icons::svg('target'), 'medal' => icons::svg('medal'),
        'settings' => icons::svg('settings'), 'search' => icons::svg('search'), 'x' => icons::svg('x'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/mybadges', $data);
echo $OUTPUT->footer();
