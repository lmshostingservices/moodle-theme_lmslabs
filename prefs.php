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
 * Preferences: the settings people actually change, as simple cards that save instantly.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no preferences');
}
$usercontext = context_user::instance($USER->id);
$url = new moodle_url('/theme/lmslabs/prefs.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('preferences'));
$PAGE->set_heading(get_string('preferences'));
$PAGE->add_body_class('ll-prefspage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/prefs.js'));

$select = function (string $key, array $options, $current) {
    $out = [];
    foreach ($options as $value => $label) {
        $out[] = ['value' => $value, 'label' => $label, 'selected' => (string)$value === (string)$current];
    }
    return ['key' => $key, 'options' => $out];
};
$toggle = function (string $key, bool $on, string $label, string $desc) {
    return ['key' => $key, 'on' => $on, 'label' => $label, 'desc' => $desc];
};

$langs = get_string_manager()->get_list_of_translations();
$zones = ['99' => get_string('serverlocaltime')] + core_date::get_list_of_timezones();
$editors = ['' => get_string('defaulteditor')];
foreach (editors_get_enabled() as $name => $e) {
    $editors[$name] = get_string('pluginname', 'editor_' . $name);
}
$canedit = has_capability('moodle/user:editownprofile', $usercontext);
$hasmanual = !empty(get_auth_plugin($USER->auth)->can_change_password());
$blocknon = (int)get_user_preferences('message_blocknoncontacts', 0);
$who = [0 => get_string('pf_msg_courses', 'theme_lmslabs'), 1 => get_string('pf_msg_contacts', 'theme_lmslabs')];
if (!empty($CFG->messagingallusers)) {
    $who[2] = get_string('pf_msg_anyone', 'theme_lmslabs');
}

$cards = [
    [
        'key' => 'account', 'icon' => icons::svg('user-round'), 'title' => get_string('pf_account', 'theme_lmslabs'),
        'desc' => fullname($USER) . ' · ' . $USER->email,
        'links' => array_values(
            array_filter([
            $canedit ? ['label' => get_string('editmyprofile'), 'url' => (new moodle_url('/user/edit.php'))->out(false),
                'icon' => icons::svg('pencil')] : null,
            $hasmanual ? ['label' => get_string('changepassword'), 'url' => (new moodle_url('/login/change_password.php'))
                ->out(false), 'icon' => icons::svg('key')] : null,
            ['label' => get_string('profile'), 'url' => (new moodle_url('/user/profile.php'))->out(false),
                'icon' => icons::svg('arrow-up-right')],
        ])
        ),
    ],
    [
        'key' => 'region', 'icon' => icons::svg('globe'), 'title' => get_string('pf_region', 'theme_lmslabs'),
        'desc' => get_string('pf_region_desc', 'theme_lmslabs'),
        'selects' => array_values(
            array_filter([
            count($langs) > 1 ? ['label' => get_string('preferredlanguage'), 'tip' => get_string('tip_lang', 'theme_lmslabs')]
                + $select('lang', $langs, $USER->lang) : null,
            ['label' => get_string('timezone'), 'tip' => get_string('tip_timezone', 'theme_lmslabs')]
                + $select('timezone', $zones, $USER->timezone),
            ['label' => get_string('pref_timeformat', 'calendar'), 'tip' => get_string('pf_timeformat_tip', 'theme_lmslabs')]
                + $select(
                    'calendar_timeformat', ['' => get_string('default'), '%I:%M %p' => '2:30 PM', '%H:%M' => '14:30'],
                    get_user_preferences('calendar_timeformat', '')
                ),
            ['label' => get_string('pref_startwday', 'calendar'), 'tip' => get_string('pf_startwday_tip', 'theme_lmslabs')]
                + $select(
                    'calendar_startwday', [1 => get_string('monday', 'calendar'), 0 => get_string('sunday', 'calendar'),
                    6 => get_string('saturday', 'calendar')], get_user_preferences('calendar_startwday', $CFG->calendar_startwday ?? 1)
                ),
        ])
        ),
    ],
    [
        'key' => 'notify', 'icon' => icons::svg('bell'), 'title' => get_string('pf_notify', 'theme_lmslabs'),
        'desc' => get_string('pf_notify_desc', 'theme_lmslabs'),
        'toggles' => [
            $toggle(
                'emailstop', empty($USER->emailstop), get_string('pf_emails', 'theme_lmslabs'),
                get_string('pf_emails_desc', 'theme_lmslabs')
            ),
            $toggle(
                'theme_lmslabs_reminders', (bool)get_user_preferences('theme_lmslabs_reminders', 0),
                get_string('cal_reminders', 'theme_lmslabs'), get_string('cal_reminders_desc', 'theme_lmslabs')
            ),
        ],
        'links' => [['label' => get_string('pf_allnotifications', 'theme_lmslabs'), 'icon' => icons::svg('sliders-horizontal'),
            'url' => (new moodle_url('/message/notificationpreferences.php', ['userid' => $USER->id]))->out(false)]],
    ],
    [
        'key' => 'messages', 'icon' => icons::svg('message-square'), 'title' => get_string('messages', 'message'),
        'desc' => get_string('pf_messages_desc', 'theme_lmslabs'),
        'selects' => [['label' => get_string('pf_whocan', 'theme_lmslabs'), 'tip' => get_string('pf_whocan_tip', 'theme_lmslabs')]
            + $select('message_blocknoncontacts', $who, $blocknon)],
        'toggles' => [$toggle(
            'message_entertosend', (bool)get_user_preferences('message_entertosend', 1),
            get_string('pf_enter', 'theme_lmslabs'), get_string('pf_enter_desc', 'theme_lmslabs')
        )],
    ],
    [
        'key' => 'forums', 'icon' => icons::svg('users'), 'title' => get_string('pf_forums', 'theme_lmslabs'),
        'desc' => get_string('pf_forums_desc', 'theme_lmslabs'),
        'selects' => [['label' => get_string('emaildigest'), 'tip' => get_string('pf_digest_tip', 'theme_lmslabs')]
            + $select(
                'maildigest', [0 => get_string('pf_digest0', 'theme_lmslabs'), 1 => get_string('pf_digest1', 'theme_lmslabs'),
                2 => get_string('pf_digest2', 'theme_lmslabs')], $USER->maildigest
            )],
        'toggles' => [
            $toggle(
                'autosubscribe', (bool)$USER->autosubscribe, get_string('pf_autosub', 'theme_lmslabs'),
                get_string('pf_autosub_desc', 'theme_lmslabs')
            ),
            $toggle(
                'trackforums', (bool)$USER->trackforums, get_string('pf_track', 'theme_lmslabs'),
                get_string('pf_track_desc', 'theme_lmslabs')
            ),
        ],
    ],
    [
        'key' => 'editor', 'icon' => icons::svg('pencil'), 'title' => get_string('pf_editor', 'theme_lmslabs'),
        'desc' => get_string('pf_editor_desc', 'theme_lmslabs'),
        'selects' => count($editors) > 2 ? [['label' => get_string('textediting')] +
            $select('htmleditor', $editors, get_user_preferences('htmleditor', ''))] : [],
        'links' => [['label' => get_string('pf_badges', 'theme_lmslabs'), 'icon' => icons::svg('award'),
            'url' => (new moodle_url('/badges/preferences.php'))->out(false)]],
    ],
];

$data = [
    'cards' => $cards,
    'sesskey' => sesskey(),
    'ajax' => (new moodle_url('/theme/lmslabs/ajax/prefs.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/user/preferences.php'),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/prefs', $data);
echo $OUTPUT->footer();
