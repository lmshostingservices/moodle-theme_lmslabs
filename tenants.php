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
 * Tenants & branding: give each organisation its own logo, colour, site name and sign-in link.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\tenants;

require_login(null, false);
$context = context_system::instance();
require_capability('theme/lmslabs:managebrand', $context);
$PAGE->set_context($context);
$url = new moodle_url('/theme/lmslabs/tenants.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tn_title', 'theme_lmslabs'));
$PAGE->set_heading(get_string('tn_title', 'theme_lmslabs'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/tenants.js'));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    $id = optional_param('id', 0, PARAM_INT);
    if ($action === 'delete' && $id) {
        tenants::delete($id);
        redirect($url, get_string('tn_deleted', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'save') {
        $name = trim(required_param('name', PARAM_TEXT));
        $slug = strtolower(trim(optional_param('idnumber', '', PARAM_ALPHANUMEXT)));
        if ($slug === '') {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        }
        $cohortid = optional_param('cohortid', 0, PARAM_INT);
        $color = theme_lmslabs_clean_hex(trim(optional_param('color', '', PARAM_TEXT)));
        $problem = '';
        if ($name === '' || $slug === '') {
            $problem = get_string('tn_needname', 'theme_lmslabs');
        } else if (!$cohortid || !$DB->record_exists('cohort', ['id' => $cohortid])) {
            $problem = get_string('tn_needcohort', 'theme_lmslabs');
        } else if ($DB->record_exists_select('theme_lmslabs_tenant', 'idnumber = :s AND id <> :i', ['s' => $slug, 'i' => $id])) {
            $problem = get_string('tn_slugtaken', 'theme_lmslabs');
        } else if ($DB->record_exists_select('theme_lmslabs_tenant', 'cohortid = :c AND id <> :i', ['c' => $cohortid, 'i' => $id])) {
            $problem = get_string('tn_cohorttaken', 'theme_lmslabs');
        }
        if ($problem) {
            redirect($url, $problem, null, \core\output\notification::NOTIFY_WARNING);
        }
        $rec = (object)['name' => $name, 'idnumber' => $slug, 'cohortid' => $cohortid, 'color' => $color,
            'sitename' => trim(optional_param('sitename', '', PARAM_TEXT)), 'enabled' => optional_param('enabled', 0, PARAM_BOOL),
            'timemodified' => time()];
        if ($id) {
            $rec->id = $id;
            $DB->update_record('theme_lmslabs_tenant', $rec);
        } else {
            $rec->logo = null;
            $id = $DB->insert_record('theme_lmslabs_tenant', $rec);
        }
        $fs = get_file_storage();
        $upload = $_FILES['logo'] ?? null;
        if (!empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
            $filename = clean_param($upload['name'], PARAM_FILE);
            if (in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'])) {
                $fs->delete_area_files($context->id, 'theme_lmslabs', 'tenantlogo', $id);
                $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'theme_lmslabs',
                    'filearea' => 'tenantlogo', 'itemid' => $id, 'filepath' => '/', 'filename' => $filename], $upload['tmp_name']);
                $DB->set_field('theme_lmslabs_tenant', 'logo', $filename, ['id' => $id]);
            }
        } else if (optional_param('removelogo', 0, PARAM_BOOL)) {
            $fs->delete_area_files($context->id, 'theme_lmslabs', 'tenantlogo', $id);
            $DB->set_field('theme_lmslabs_tenant', 'logo', null, ['id' => $id]);
        }
        tenants::recount();
        redirect($url, get_string('tn_saved', 'theme_lmslabs', s($name)), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$cohorts = [];
foreach ($DB->get_records('cohort', ['component' => ''], 'name ASC') as $c) {
    $cohorts[] = ['id' => (int)$c->id, 'name' => format_string($c->name) . ' (' .
        $DB->count_records('cohort_members', ['cohortid' => $c->id]) . ')'];
}
$all = tenants::all();
$data = [
    'sesskey' => sesskey(),
    'action' => $url->out(false),
    'tenants' => $all,
    'hastenants' => (bool)$all,
    'cohorts' => $cohorts,
    'hascohorts' => (bool)$cohorts,
    'cohortsurl' => (new moodle_url('/theme/lmslabs/cohorts.php'))->out(false),
    'accent' => theme_lmslabs_accent(),
    'i' => ['plus' => icons::svg('plus'), 'x' => icons::svg('x'), 'building' => icons::svg('building-2'),
        'copy' => icons::svg('copy'), 'pencil' => icons::svg('pencil'), 'trash' => icons::svg('trash'),
        'users' => icons::svg('users'), 'link' => icons::svg('external-link'), 'upload' => icons::svg('upload')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/tenants', $data);
echo $OUTPUT->footer();
