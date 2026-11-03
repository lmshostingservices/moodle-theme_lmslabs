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
 * Messages: a full-page inbox with conversations, the thread and who you're talking to.
 *
 * Built on Moodle's own message web services, so everything stays in sync with the mobile app,
 * notifications and email.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot message');
}
if (empty($CFG->messaging)) {
    throw new moodle_exception('disabled', 'message');
}
$context = context_user::instance($USER->id);
$withuser = optional_param('id', 0, PARAM_INT);
$convid = optional_param('convid', 0, PARAM_INT);

$url = new moodle_url('/theme/lmslabs/messages.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('messages', 'message'));
$PAGE->set_heading(get_string('messages', 'message'));
$PAGE->add_body_class('ll-inboxpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/messages.js'));

$withname = '';
if ($withuser && $withuser != $USER->id) {
    $other = core_user::get_user($withuser);
    $withname = $other ? fullname($other) : '';
}

$canbroadcast = false;
foreach (enrol_get_all_users_courses($USER->id, true, 'id') as $c) {
    if (has_capability('moodle/course:bulkmessaging', context_course::instance($c->id))) {
        $canbroadcast = true;
        break;
    }
}

$config = [
    'userid' => (int)$USER->id,
    'sesskey' => sesskey(),
    'withuser' => $withuser,
    'withname' => $withname,
    'convid' => $convid,
    'ajax' => (new moodle_url('/theme/lmslabs/ajax/messages.php'))->out(false),
    'str' => [
        'today' => get_string('today', 'calendar'),
        'yesterday' => get_string('yesterday', 'calendar'),
        'you' => get_string('msg_you', 'theme_lmslabs'),
        'empty' => get_string('msg_emptythread', 'theme_lmslabs'),
        'noconvs' => get_string('msg_noconvs', 'theme_lmslabs'),
        'nomatch' => get_string('msg_nomatch', 'theme_lmslabs'),
        'sending' => get_string('msg_sending', 'theme_lmslabs'),
        'failed' => get_string('msg_failed', 'theme_lmslabs'),
        'online' => get_string('msg_online', 'theme_lmslabs'),
        'lastseen' => get_string('msg_lastseen', 'theme_lmslabs', '{t}'),
        'starred' => get_string('msg_starred', 'theme_lmslabs'),
        'progress' => get_string('msg_progress', 'theme_lmslabs', '{n}'),
        'grades' => get_string('grades'),
        'noshared' => get_string('msg_noshared', 'theme_lmslabs'),
        'contacts' => get_string('contacts', 'message'),
        'others' => get_string('msg_others', 'theme_lmslabs'),
        'learners' => get_string('msg_learners', 'theme_lmslabs', '{n}'),
        'group' => get_string('msg_group', 'theme_lmslabs'),
        'selfnote' => get_string('msg_selfnote', 'theme_lmslabs'),
        'profile' => get_string('profile'),
        'together' => get_string('msg_together', 'theme_lmslabs'),
    ],
];

$data = [
    'config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES),
    'canbroadcast' => $canbroadcast,
    'prefsurl' => (new moodle_url('/message/notificationpreferences.php', ['userid' => $USER->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/message/index.php'),
    'i' => [
        'search' => icons::svg('search'), 'plus' => icons::svg('plus'), 'send' => icons::svg('send'),
        'star' => icons::svg('star'), 'user' => icons::svg('user-round'), 'back' => icons::svg('arrow-left'),
        'chat' => icons::svg('message-square'), 'book' => icons::svg('book-open'), 'x' => icons::svg('x'),
        'settings' => icons::svg('settings'), 'users' => icons::svg('users'), 'info' => icons::svg('panel-right'),
        'megaphone' => icons::svg('send'), 'mail' => icons::svg('mail'), 'clock' => icons::svg('clock'),
        'chart' => icons::svg('chart-column'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/messages', $data);
echo $OUTPUT->footer();
