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
 * Notifications: everything the site told you, grouped by day, one click to what it's about.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no notifications');
}
$url = new moodle_url('/theme/lmslabs/notifications.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('notifications', 'message'));
$PAGE->set_heading(get_string('notifications', 'message'));
$PAGE->add_body_class('ll-notifpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/notifications.js'));

$config = [
    'userid' => (int)$USER->id,
    'str' => [
        'today' => get_string('today', 'calendar'),
        'yesterday' => get_string('yesterday', 'calendar'),
        'none' => get_string('nt_none', 'theme_lmslabs'),
        'nounread' => get_string('nt_nounread', 'theme_lmslabs'),
        'more' => get_string('nt_more', 'theme_lmslabs'),
        'marked' => get_string('nt_marked', 'theme_lmslabs'),
        'markread' => get_string('nt_markread', 'theme_lmslabs'),
        'emptyhint' => get_string('nt_emptyhint', 'theme_lmslabs'),
        'k_recert' => get_string('nt_k_recert', 'theme_lmslabs'),
        'k_group' => get_string('nt_k_group', 'theme_lmslabs'),
        'k_badge' => get_string('nt_k_badge', 'theme_lmslabs'),
        'k_done' => get_string('nt_k_done', 'theme_lmslabs'),
        'k_assign' => get_string('nt_k_assign', 'theme_lmslabs'),
        'k_quiz' => get_string('nt_k_quiz', 'theme_lmslabs'),
        'k_forum' => get_string('nt_k_forum', 'theme_lmslabs'),
        'k_grade' => get_string('nt_k_grade', 'theme_lmslabs'),
        'k_enrol' => get_string('nt_k_enrol', 'theme_lmslabs'),
        'k_message' => get_string('nt_k_message', 'theme_lmslabs'),
        'k_other' => get_string('nt_k_other', 'theme_lmslabs'),
    ],
    'icons' => [
        'bell' => icons::svg('bell'), 'rotate' => icons::svg('rotate-ccw'), 'users' => icons::svg('users-round'),
        'award' => icons::svg('award'), 'done' => icons::svg('circle-check-big'), 'file' => icons::svg('file-text'),
        'chat' => icons::svg('message-square'), 'chart' => icons::svg('chart-column'),
        'cap' => icons::svg('graduation-cap'), 'mail' => icons::svg('mail'), 'check' => icons::svg('check'),
    ],
];
$data = [
    'config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES),
    'prefsurl' => (new moodle_url('/message/notificationpreferences.php', ['userid' => $USER->id]))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/message/output/popup/notifications.php'),
    'i' => ['check' => icons::svg('check-check'), 'settings' => icons::svg('settings'), 'bell' => icons::svg('bell')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/notifications', $data);
echo $OUTPUT->footer();
