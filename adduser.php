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
 * Add a person: username (email fills itself in), password, name, custom fields; the rest folded away.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/weblib.php');

use theme_lmslabs\local\adduser;
use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
require_capability('moodle/user:create', $context);

$url = new moodle_url('/theme/lmslabs/adduser.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('addnewuser'));
$PAGE->set_heading(get_string('addnewuser'));
$PAGE->add_body_class('ll-formpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/adduser.js'));

// Last choices are remembered, so adding a whole intake is quick.
$remembered = json_decode((string)get_user_preferences('theme_lmslabs_adduser', '{}'), true) ?: [];

$posted = [];
$errors = [];
if (data_submitted() && confirm_sesskey()) {
    $posted = (array)data_submitted(); // Cleaned field by field below and in adduser::validate()/create().
    foreach ($posted as $k => $v) {
        if (is_array($v)) {
            $posted[$k] = clean_param_array($v, PARAM_INT);
        } else if ($k === 'newpassword') {
            $posted[$k] = (string)$v; // pipeline-ignore: PARAM_RAW — password, checked against the site policy.
        } else {
            $posted[$k] = clean_param($v, $k === 'email' ? PARAM_EMAIL : PARAM_TEXT);
        }
    }
    $posted['username'] = core_text::strtolower(trim($posted['username'] ?? ''));
    $errors = adduser::validate($posted);
    if (!$errors) {
        $user = adduser::create($posted);
        set_user_preference(
            'theme_lmslabs_adduser', json_encode([
            'courses' => array_values(array_map('intval', (array)($posted['courses'] ?? []))),
            'cohort' => (int)($posted['cohort'] ?? 0),
            'createpassword' => !empty($posted['createpassword']),
            'forcechange' => !empty($posted['forcechange']),
        ])
        );
        $name = fullname($user);
        $next = optional_param('next', 'people', PARAM_ALPHA) === 'another' ? $url
            : new moodle_url('/theme/lmslabs/people.php');
        redirect($next, get_string('au_created', 'theme_lmslabs', $name), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$val = function (string $k, $default = '') use ($posted) {
    return isset($posted[$k]) && !is_array($posted[$k]) ? (string)$posted[$k] : $default;
};
$selectedcourses = isset($posted['courses']) ? (array)$posted['courses'] : ($remembered['courses'] ?? []);
$selectedcohort = isset($posted['cohort']) ? (int)$posted['cohort'] : (int)($remembered['cohort'] ?? 0);

$courses = array_map(function ($c) use ($selectedcourses) {
    $c['selected'] = in_array($c['id'], $selectedcourses);
    return $c;
}, adduser::courses());
$cohorts = array_map(function ($c) use ($selectedcohort) {
    $c['selected'] = $c['id'] == $selectedcohort;
    return $c;
}, adduser::cohorts());

$countries = [];
$country = $val('country', $CFG->country ?? '');
foreach (get_string_manager()->get_list_of_countries() as $code => $label) {
    $countries[] = ['value' => $code, 'label' => $label, 'selected' => $code === $country];
}
$more = [];
foreach (adduser::MORE as $f) {
    $more[] = ['name' => $f, 'label' => get_string($f), 'value' => $val($f), 'tip' => get_string('tip_' . $f, 'theme_lmslabs')];
}

$fielderrors = [];
foreach ($errors as $k => $msg) {
    $fielderrors[$k] = ['msg' => $msg];
}
$profile = array_map(function ($f) use ($fielderrors) {
    $f['error'] = $fielderrors[$f['name']]['msg'] ?? null;
    return $f;
}, adduser::profile_fields($posted));
$requiredprofile = array_values(
    array_filter(
    $profile, function ($f) {
    return $f['required'];
}
)
);
$optionalprofile = array_values(
    array_filter(
    $profile, function ($f) {
    return !$f['required'];
}
)
);

$createpassword = $posted ? !empty($posted['createpassword']) : !empty($remembered['createpassword']);
$forcechange = $posted ? !empty($posted['forcechange']) : !empty($remembered['forcechange']);

$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'username' => $val('username'),
    'email' => $val('email'),
    'firstname' => $val('firstname'),
    'lastname' => $val('lastname'),
    'emailsynced' => !$posted || $val('email') === $val('username'),
    'createpassword' => $createpassword,
    'forcechange' => $forcechange,
    'suspended' => !empty($posted['suspended']),
    'policy' => trim(html_to_text(print_password_policy(), 0, false)),
    'e' => $fielderrors,
    'haserrors' => (bool)$errors,
    'requiredprofile' => $requiredprofile,
    'hasrequiredprofile' => (bool)$requiredprofile,
    'optionalprofile' => $optionalprofile,
    'more' => $more,
    'countries' => $countries,
    'courses' => $courses,
    'pcoursessome' => get_string('au_pcourses', 'theme_lmslabs', '{n}'),
    'hascourses' => (bool)$courses,
    'cohorts' => $cohorts,
    'hascohorts' => (bool)$cohorts,
    'remembered' => (bool)$remembered,
    'peopleurl' => (new moodle_url('/theme/lmslabs/people.php'))->out(false),
    'uploadurl' => (new moodle_url('/admin/tool/uploaduser/index.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/user/editadvanced.php', ['id' => -1]),
    'i' => [
        'user' => icons::svg('user-plus'), 'mail' => icons::svg('mail'), 'key' => icons::svg('key-round'),
        'eye' => icons::svg('eye'), 'dice' => icons::svg('sparkles'), 'book' => icons::svg('book-open'),
        'contact' => icons::svg('contact-round'), 'upload' => icons::svg('upload'), 'chev' => icons::svg('chevron-down'),
        'link' => icons::svg('link'), 'check' => icons::svg('check'), 'search' => icons::svg('search'),
        'list' => icons::svg('list'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/adduser', $data);
echo $OUTPUT->footer();
