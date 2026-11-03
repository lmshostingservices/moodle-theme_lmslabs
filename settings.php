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
 * One simple settings page: brand, contact details, outgoing email.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

// Site Owners (theme/lmslabs:managebrand) can open this page as well as site administrators.
$settings = new admin_settingpage(
    'themesettinglmslabs', get_string('configtitle', 'theme_lmslabs'),
    'theme/lmslabs:managebrand'
);

if ($ADMIN->fulltree) {

    // Site identity (Moodle's own site name and description).
    $settings->add(
        new admin_setting_heading(
        'theme_lmslabs/siteheading',
        get_string('siteheading', 'theme_lmslabs'), ''
    )
    );
    $settings->add(new \theme_lmslabs\admin\coresetting('fullname', new lang_string('fullsitename'), '', 'fullname', true));
    $settings->add(new \theme_lmslabs\admin\coresetting('shortname', new lang_string('shortsitename'), '', 'shortname', true));
    $settings->add(new admin_setting_special_frontpagedesc());

    // Brand.
    $settings->add(
        new admin_setting_heading(
        'theme_lmslabs/brandheading',
        get_string('brandheading', 'theme_lmslabs'), ''
    )
    );

    $setting = new admin_setting_configstoredfile(
        'theme_lmslabs/logo', get_string('logo', 'theme_lmslabs'),
        get_string('logo_desc', 'theme_lmslabs'), 'logo', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp']]
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    $setting = new admin_setting_configstoredfile(
        'theme_lmslabs/loginimage', get_string('loginimage', 'theme_lmslabs'),
        get_string('loginimage_desc', 'theme_lmslabs'), 'loginimage', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp']]
    );
    $settings->add($setting);

    $setting = new admin_setting_configcolourpicker(
        'theme_lmslabs/brandcolor', get_string('brandcolor', 'theme_lmslabs'),
        get_string('brandcolor_desc', 'theme_lmslabs'), THEME_LMSLABS_DEFAULT_ACCENT
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Course format.
    $formats = [];
    foreach (core_component::get_plugin_list('format') as $name => $dir) {
        $formats[$name] = get_string('pluginname', 'format_' . $name);
    }
    $settings->add(new admin_setting_heading('theme_lmslabs/formatheading', get_string('formatheading', 'theme_lmslabs'), ''));
    $settings->add(
        new admin_setting_configselect(
        'theme_lmslabs/courseformat', get_string('courseformat', 'theme_lmslabs'),
        get_string('courseformat_desc', 'theme_lmslabs'), '', ['' => get_string('courseformat_auto', 'theme_lmslabs')] + $formats
    )
    );

    // Typography.
    $settings->add(new admin_setting_heading('theme_lmslabs/typeheading', get_string('typeheading', 'theme_lmslabs'), ''));
    $settings->add(
        new admin_setting_configselect(
        'theme_lmslabs/font', get_string('font', 'theme_lmslabs'),
        get_string('font_desc', 'theme_lmslabs'), '', theme_lmslabs_font_choices()
    )
    );
    $settings->add(
        new admin_setting_configtext(
        'theme_lmslabs/fontcustom', get_string('fontcustom', 'theme_lmslabs'),
        get_string('fontcustom_desc', 'theme_lmslabs'), '', PARAM_TEXT
    )
    );
    $sizes = [];
    foreach ([14, 15, 16, 17, 18] as $px) {
        $sizes[$px] = get_string('fontsize_' . $px, 'theme_lmslabs');
    }
    $settings->add(
        new admin_setting_configselect(
        'theme_lmslabs/fontsize', get_string('fontsize', 'theme_lmslabs'),
        '', 15, $sizes
    )
    );

    // Contact details.
    $settings->add(
        new admin_setting_heading(
        'theme_lmslabs/contactheading',
        get_string('contactheading', 'theme_lmslabs'), get_string('contactheading_desc', 'theme_lmslabs')
    )
    );
    $settings->add(
        new admin_setting_configtext(
        'theme_lmslabs/contactemail', get_string('contactemail', 'theme_lmslabs'),
        '', '', PARAM_EMAIL
    )
    );
    $settings->add(
        new admin_setting_configtext(
        'theme_lmslabs/contactphone', get_string('contactphone', 'theme_lmslabs'),
        '', '', PARAM_NOTAGS
    )
    );
    $settings->add(
        new admin_setting_configcheckbox(
        'theme_lmslabs/forwardadminmail',
        get_string('forwardadminmail', 'theme_lmslabs'), get_string('forwardadminmail_desc', 'theme_lmslabs'), 1
    )
    );

    // Outgoing email (SMTP). These write to Moodle's own core settings, so there is one source of truth.
    $testurl = new moodle_url('/admin/testoutgoingmailconf.php');
    $settings->add(
        new admin_setting_heading(
        'theme_lmslabs/smtpheading', get_string('smtpheading', 'theme_lmslabs'),
        get_string('smtpheading_desc', 'theme_lmslabs', $testurl->out(false))
    )
    );
    $settings->add(
        new \theme_lmslabs\admin\coresetting(
        'smtphosts', new lang_string('smtphosts', 'admin'),
        new lang_string('configsmtphosts', 'admin'), 'smtphosts'
    )
    );
    $settings->add(
        new \theme_lmslabs\admin\coreselect(
        'smtpsecure', new lang_string('smtpsecure', 'admin'),
        new lang_string('configsmtpsecure', 'admin'), 'smtpsecure', '',
        ['' => new lang_string('none', 'admin'), 'ssl' => 'SSL', 'tls' => 'TLS']
    )
    );
    $settings->add(
        new \theme_lmslabs\admin\coreselect(
        'smtpauthtype', new lang_string('smtpauthtype', 'admin'),
        new lang_string('configsmtpauthtype', 'admin'), 'smtpauthtype', 'LOGIN',
        ['LOGIN' => 'LOGIN', 'PLAIN' => 'PLAIN', 'NTLM' => 'NTLM', 'CRAM-MD5' => 'CRAM-MD5']
    )
    );
    $settings->add(
        new \theme_lmslabs\admin\coresetting(
        'smtpuser', new lang_string('smtpuser', 'admin'),
        new lang_string('configsmtpuser', 'admin'), 'smtpuser', false, PARAM_NOTAGS
    )
    );
    $settings->add(
        new \theme_lmslabs\admin\corepassword(
        'smtppass', new lang_string('smtppass', 'admin'),
        new lang_string('configsmtpuser', 'admin'), 'smtppass'
    )
    );
    $noreply = 'noreply@' . get_host_from_url($CFG->wwwroot);
    if (!validate_email($noreply)) {
        $noreply = '';
    }
    $settings->add(
        new \theme_lmslabs\admin\coresetting(
        'noreplyaddress', new lang_string('noreplyaddress', 'admin'),
        new lang_string('confignoreplyaddress', 'admin'), 'noreplyaddress', false, PARAM_EMAIL, $noreply
    )
    );
}

// Hosting provider only. The LMS Labs bar can be switched off at a client's request; this page needs
// moodle/site:config, which only site administrators hold (Site Owners do not).
$hosting = new admin_settingpage(
    'themesettinglmslabshosting', get_string('hostingsettings', 'theme_lmslabs'),
    'moodle/site:config'
);
if ($ADMIN->fulltree) {
    $hosting->add(
        new admin_setting_heading(
        'theme_lmslabs/hostingheading', '',
        get_string('hostingsettings_desc', 'theme_lmslabs')
    )
    );
    $setting = new admin_setting_configcheckbox(
        'theme_lmslabs/hidepromo', get_string('hidepromo', 'theme_lmslabs'),
        get_string('hidepromo_desc', 'theme_lmslabs'), 0
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $hosting->add($setting);
    $hosting->add(
        new admin_setting_configcheckbox(
            'theme_lmslabs/showaddons', get_string('showaddons', 'theme_lmslabs'),
            get_string('showaddons_desc', 'theme_lmslabs'), 1
        )
    );
    $setting = new admin_setting_configcheckbox(
        'theme_lmslabs/useaiformat', get_string('useaiformat', 'theme_lmslabs'),
        get_string('useaiformat_desc', 'theme_lmslabs'), 1
    );
    $setting->set_updatedcallback('\\theme_lmslabs\\local\\aiformat::setting_updated');
    $hosting->add($setting);
}
$ADMIN->add('themes', $hosting);

