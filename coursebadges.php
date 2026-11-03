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
 * Course badges: every badge as a card, create in one step, switch on or off, award by hand.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');

use theme_lmslabs\local\icons;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
if (empty($CFG->enablebadges) || empty($CFG->badges_allowcoursebadges)) {
    throw new moodle_exception('badgesdisabled', 'badges');
}
$ctx = context_course::instance($course->id);
$caps = ['moodle/badges:viewbadges', 'moodle/badges:createbadge', 'moodle/badges:awardbadge'];
if (!has_any_capability($caps, $ctx)) {
    require_capability('moodle/badges:viewbadges', $ctx);
}
$url = new moodle_url('/theme/lmslabs/coursebadges.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('badges', 'badges'));
$PAGE->set_heading(get_string('cb_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/coursebadges.js'));

$cancreate = has_capability('moodle/badges:createbadge', $ctx);
$canaward = has_capability('moodle/badges:awardbadge', $ctx);
$cards = \theme_lmslabs\local\badges::cards($course);
foreach ($cards as &$c) {
    $c['canaward'] = $canaward && $c['manual'] && $c['active'];
    $c['canswitch'] = $cancreate && $c['hascriteria'] && !$c['locked'];
}
unset($c);
$data = [
    'courseid' => $course->id,
    'sesskey' => sesskey(),
    'ajax' => (new moodle_url('/theme/lmslabs/ajax/badges.php'))->out(false),
    'cards' => $cards,
    'hascards' => (bool)$cards,
    'cancreate' => $cancreate,
    'canaward' => $canaward,
    'summary' => get_string(
        'cb_summary', 'theme_lmslabs', ['n' => count($cards),
        'live' => count(
            array_filter(
            $cards, function ($c) {
            return $c['active'];
        }
        )
        )]
    ),
    'classicurl' => \theme_lmslabs\local\router::classic('/badges/index.php', ['type' => 2, 'id' => $course->id]),
    'mybadgesurl' => (new moodle_url('/theme/lmslabs/mybadges.php'))->out(false),
    'i' => [
        'award' => icons::svg('award'), 'plus' => icons::svg('plus'), 'users' => icons::svg('users'),
        'send' => icons::svg('send'), 'settings' => icons::svg('settings'), 'upload' => icons::svg('upload'),
        'external' => icons::svg('external-link'), 'search' => icons::svg('search'), 'x' => icons::svg('x'),
        'trophy' => icons::svg('trophy'), 'check' => icons::svg('check'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/coursebadges', $data);
echo $OUTPUT->footer();
