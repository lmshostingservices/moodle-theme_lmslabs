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
 * Appearance: brand colour, logo, font, contact details and login picture, with a live preview.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
require_capability('theme/lmslabs:managebrand', $context);
$PAGE->set_context($context);
$url = new moodle_url('/theme/lmslabs/appearance.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('appearance', 'theme_lmslabs'));
$PAGE->set_heading(get_string('ap_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/appearance.js'));

$fs = get_file_storage();

/**
 * Store an uploaded image as a theme setting file, or clear it.
 *
 * @param string $name logo|loginimage
 * @param array|null $upload $_FILES entry
 * @param bool $remove
 * @param array $exts allowed extensions
 * @return bool true if changed
 */
function theme_lmslabs_appearance_file(string $name, ?array $upload, bool $remove, array $exts): bool {
    $fs = get_file_storage();
    $ctxid = context_system::instance()->id;
    if (!empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
        $filename = clean_param($upload['name'], PARAM_FILE);
        if (!in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $exts)) {
            return false;
        }
        $fs->delete_area_files($ctxid, 'theme_lmslabs', $name, 0);
        $fs->create_file_from_pathname(['contextid' => $ctxid, 'component' => 'theme_lmslabs', 'filearea' => $name,
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename], $upload['tmp_name']);
        set_config($name, '/' . $filename, 'theme_lmslabs');
        return true;
    }
    if ($remove) {
        $fs->delete_area_files($ctxid, 'theme_lmslabs', $name, 0);
        unset_config($name, 'theme_lmslabs');
        return true;
    }
    return false;
}

if (data_submitted() && confirm_sesskey()) {
    $accent = theme_lmslabs_clean_hex(optional_param('brandcolor', '', PARAM_TEXT));
    if ($accent) {
        set_config('brandcolor', $accent, 'theme_lmslabs');
    }
    $fonts = theme_lmslabs_font_choices();
    $font = optional_param('font', '', PARAM_TEXT);
    if (isset($fonts[$font])) {
        set_config('font', $font, 'theme_lmslabs');
        set_config('fontcustom', '', 'theme_lmslabs');
    }
    $size = optional_param('fontsize', 15, PARAM_INT);
    set_config('fontsize', in_array($size, [14, 15, 16, 17, 18]) ? $size : 15, 'theme_lmslabs');
    set_config('contactemail', clean_param(optional_param('contactemail', '', PARAM_TEXT), PARAM_EMAIL), 'theme_lmslabs');
    set_config('contactphone', clean_param(optional_param('contactphone', '', PARAM_TEXT), PARAM_NOTAGS), 'theme_lmslabs');
    // Site description, shown to visitors and counted by the Get started guide.
    if (optional_param('summary_sent', 0, PARAM_BOOL)) {
        $summary = trim(optional_param('summary', '', PARAM_TEXT));
        $DB->set_field('course', 'summary', $summary, ['id' => SITEID]);
        $DB->set_field('course', 'summaryformat', FORMAT_PLAIN, ['id' => SITEID]);
    }
    // Outgoing email (same settings as Site administration > Server > Outgoing mail configuration).
    if (optional_param('smtp_sent', 0, PARAM_BOOL)) {
        set_config('smtphosts', trim(optional_param('smtphosts', '', PARAM_TEXT)));
        $secure = optional_param('smtpsecure', '', PARAM_ALPHA);
        set_config('smtpsecure', in_array($secure, ['ssl', 'tls']) ? $secure : '');
        $auth = optional_param('smtpauthtype', 'LOGIN', PARAM_TEXT);
        set_config('smtpauthtype', in_array($auth, ['LOGIN', 'PLAIN', 'NTLM', 'CRAM-MD5']) ? $auth : 'LOGIN');
        set_config('smtpuser', trim(optional_param('smtpuser', '', PARAM_TEXT)));
        $pass = optional_param('smtppass', '', PARAM_RAW); // pipeline-ignore: PARAM_RAW — password, stored as typed.
        if ($pass !== '') {
            set_config('smtppass', $pass);
        }
        if (optional_param('smtpclearpass', 0, PARAM_BOOL)) {
            set_config('smtppass', '');
        }
        $noreply = clean_param(optional_param('noreplyaddress', '', PARAM_TEXT), PARAM_EMAIL);
        if ($noreply !== '') {
            set_config('noreplyaddress', $noreply);
        }
    }
    $fullname = trim(optional_param('fullname', '', PARAM_TEXT));
    $shortname = trim(optional_param('shortname', '', PARAM_TEXT));
    if ($fullname !== '' && $shortname !== '') {
        $DB->set_field('course', 'fullname', $fullname, ['id' => SITEID]);
        $DB->set_field('course', 'shortname', $shortname, ['id' => SITEID]);
        $DB->set_field('course', 'timemodified', time(), ['id' => SITEID]);
    }
    theme_lmslabs_appearance_file(
        'logo', $_FILES['logo'] ?? null, optional_param('removelogo', 0, PARAM_BOOL),
        ['png', 'jpg', 'jpeg', 'svg', 'webp']
    );
    theme_lmslabs_appearance_file(
        'loginimage', $_FILES['loginimage'] ?? null, optional_param('removelogin', 0, PARAM_BOOL),
        ['png', 'jpg', 'jpeg', 'webp']
    );
    theme_reset_all_caches();
    redirect($url, get_string('ap_saved', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$site = get_site();
$accent = theme_lmslabs_accent();
$swatches = [];
$known = false;
foreach (theme_lmslabs_accent_presets() as $hex => $label) {
    $swatches[] = ['hex' => $hex, 'label' => $label, 'selected' => $hex === $accent];
    $known = $known || $hex === $accent;
}
$font = (string)get_config('theme_lmslabs', 'font');
$fontoptions = [];
foreach (theme_lmslabs_font_choices() as $value => $label) {
    $fontoptions[] = ['value' => $value, 'label' => $label, 'selected' => (string)$value === $font];
}
$size = theme_lmslabs_font_size();
$login = theme_lmslabs_login_image_url();
$theme = theme_config::load('lmslabs');
$haslogo = !empty($theme->settings->logo);
$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'fullname' => format_string($site->fullname, true, ['escape' => false]),
    'shortname' => $site->shortname,
    'accent' => $accent,
    'swatches' => $swatches,
    'custom' => !$known,
    'fontoptions' => $fontoptions,
    'sizes' => array_map(function ($px) use ($size) {
        return ['px' => $px, 'label' => get_string('fontsize_' . $px, 'theme_lmslabs'), 'selected' => $px == $size];
    }, [14, 15, 16, 17, 18]),
    'contactemail' => (string)get_config('theme_lmslabs', 'contactemail'),
    'contactphone' => (string)get_config('theme_lmslabs', 'contactphone'),
    'logo' => theme_lmslabs_logo_url(96),
    'haslogo' => $haslogo,
    'loginimage' => $login,
    'hasloginimage' => !empty($theme->settings->loginimage),
    'initial' => core_text::strtoupper(core_text::substr($site->shortname, 0, 1)),
    'summary' => trim(strip_tags((string)$site->summary)),
    'smtphosts' => (string)($CFG->smtphosts ?? ''),
    'smtpsecures' => array_map(function ($v, $l) use ($CFG) {
        return ['value' => $v, 'label' => $l, 'selected' => (string)($CFG->smtpsecure ?? '') === $v];
    }, ['', 'ssl', 'tls'], [get_string('none', 'admin'), 'SSL', 'TLS']),
    'smtpauths' => array_map(function ($v) use ($CFG) {
        return ['value' => $v, 'label' => $v, 'selected' => ($CFG->smtpauthtype ?? 'LOGIN') === $v];
    }, ['LOGIN', 'PLAIN', 'NTLM', 'CRAM-MD5']),
    'smtpuser' => (string)($CFG->smtpuser ?? ''),
    'haspass' => !empty($CFG->smtppass),
    'noreply' => (string)($CFG->noreplyaddress ?? ''),
    'testurl' => (new moodle_url('/admin/testoutgoingmailconf.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/admin/settings.php', ['section' => 'themesettinglmslabs']),
    'i' => ['palette' => icons::svg('palette'), 'type' => icons::svg('type'), 'mail' => icons::svg('mail'),
        'image' => icons::svg('image'), 'upload' => icons::svg('file-up'), 'check' => icons::svg('check'),
        'building' => icons::svg('building-2'), 'trash' => icons::svg('trash'), 'phone' => icons::svg('phone'),
        'dash' => icons::svg('layout-dashboard'), 'book' => icons::svg('book-open'), 'users' => icons::svg('users')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/appearance', $data);
echo $OUTPUT->footer();
