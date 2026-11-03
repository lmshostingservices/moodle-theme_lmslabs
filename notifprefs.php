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
 * Notification settings: grouped, searchable, one switch per channel, plus "all email off" in one click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/message/lib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no notification settings');
}
$user = $USER;
require_capability('moodle/user:editownmessageprofile', context_user::instance($user->id));
$url = new moodle_url('/theme/lmslabs/notifprefs.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('notificationpreferences', 'message'));
$PAGE->set_heading(get_string('notificationpreferences', 'message'));
$PAGE->add_body_class('ll-notifprefspage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/notifprefs.js'));

// Save (AJAX, same page).
if (optional_param('save', '', PARAM_ALPHAEXT) && confirm_sesskey()) {
    $key = required_param('key', PARAM_ALPHANUMEXT);
    $value = trim(optional_param('value', '', PARAM_TEXT));
    $allowed = array_keys(get_message_processors());
    $parts = array_values(array_intersect(array_filter(explode(',', $value)), $allowed));
    if (strpos($key, 'message_provider_') !== 0 || substr($key, -8) !== '_enabled') {
        throw new invalid_parameter_exception('Bad key');
    }
    set_user_preference($key, $parts ? implode(',', $parts) : 'none');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    die;
}

$processors = get_message_processors();
$providers = message_get_providers_for_user($user->id);
$preferences = \core_message\api::get_all_message_preferences($processors, $providers, $user);
$list = (new \core_message\output\preferences\notification_list($processors, $providers, $preferences, $user))
    ->export_for_template($PAGE->get_renderer('core', 'message'));
$list = json_decode(json_encode($list), true);

$channels = [];
foreach ($list['processors'] ?? [] as $p) {
    $channels[] = ['name' => $p['name'], 'label' => $p['displayname'],
        'icon' => icons::svg(['popup' => 'bell', 'email' => 'mail', 'airnotifier' => 'phone'][$p['name']] ?? 'send')];
}
$groups = [];
foreach ($list['components'] ?? [] as $c) {
    if (empty($c['notifications'])) {
        continue;
    }
    $rows = [];
    foreach ($c['notifications'] as $n) {
        $cells = [];
        foreach ($n['processors'] as $p) {
            $cells[] = ['name' => $p['name'], 'on' => !empty($p['enabled']), 'locked' => !empty($p['locked']),
                'lockedmessage' => $p['lockedmessage'] ?? '', 'label' => $p['displayname'],
                'supported' => $p['supportsprocessor'] ?? true];
        }
        $rows[] = ['label' => $n['displayname'], 'key' => $n['preferencekey'] . '_enabled', 'cells' => $cells];
    }
    $groups[] = ['label' => $c['displayname'], 'rows' => $rows, 'count' => count($rows)];
}
usort(
    $groups, function ($a, $b) {
    return strcasecmp($a['label'], $b['label']);
}
);

$data = [
    'channels' => $channels,
    'channelcount' => max(1, count($channels)),
    'groups' => $groups,
    'sesskey' => sesskey(),
    'saveurl' => $url->out(false),
    'disabled' => !empty($user->emailstop),
    'prefsurl' => (new moodle_url('/theme/lmslabs/prefs.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/message/notificationpreferences.php', ['userid' => $user->id]),
    'i' => ['search' => icons::svg('search'), 'chev' => icons::svg('chevron-down'), 'lock' => icons::svg('lock'),
        'back' => icons::svg('arrow-left')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/notifprefs', $data);
echo $OUTPUT->footer();
