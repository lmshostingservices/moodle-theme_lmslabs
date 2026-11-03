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
 * Cohorts: site-wide groups of people. Create one in a line, add people by pasting emails or picking them.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
if (!has_any_capability(['moodle/cohort:manage', 'moodle/cohort:assign', 'moodle/cohort:view'], $context)) {
    require_capability('moodle/cohort:view', $context);
}
$canmanage = has_capability('moodle/cohort:manage', $context);
$canassign = has_capability('moodle/cohort:assign', $context);

$url = new moodle_url('/theme/lmslabs/cohorts.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('cohorts', 'cohort'));
$PAGE->set_heading(get_string('cohorts', 'cohort'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/cohorts.js'));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    $id = optional_param('id', 0, PARAM_INT);
    $cohort = $id ? $DB->get_record('cohort', ['id' => $id, 'contextid' => $context->id, 'component' => ''], '*', MUST_EXIST)
        : null;
    switch ($action) {
        case 'create':
            require_capability('moodle/cohort:manage', $context);
            $name = trim(required_param('name', PARAM_TEXT));
            if ($name === '') {
                redirect($url, get_string('ch_needname', 'theme_lmslabs'), null, \core\output\notification::NOTIFY_WARNING);
            }
            $newid = cohort_add_cohort(
                (object)[
                'contextid' => $context->id,
                'name' => $name,
                'idnumber' => trim(optional_param('idnumber', '', PARAM_TEXT)),
                'description' => trim(optional_param('description', '', PARAM_TEXT)),
                'descriptionformat' => FORMAT_PLAIN,
                'visible' => 1,
                'component' => '',
            ]
            );
            redirect(
                new moodle_url($url, ['open' => $newid]), get_string('ch_created', 'theme_lmslabs', s($name)), null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case 'rename':
            require_capability('moodle/cohort:manage', $context);
            $name = trim(required_param('name', PARAM_TEXT));
            if ($cohort && $name !== '') {
                $cohort->name = $name;
                $cohort->description = trim(optional_param('description', $cohort->description, PARAM_TEXT));
                cohort_update_cohort($cohort);
            }
            redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'delete':
            require_capability('moodle/cohort:manage', $context);
            if ($cohort) {
                cohort_delete_cohort($cohort);
            }
            redirect(
                $url, get_string('ch_deleted', 'theme_lmslabs', format_string($cohort->name)), null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case 'paste':
            require_capability('moodle/cohort:assign', $context);
            $raw = trim(optional_param('people', '', PARAM_TEXT));
            $tokens = array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', core_text::strtolower($raw)))));
            $added = 0;
            $already = 0;
            $missing = [];
            foreach ($tokens as $t) {
                $user = $DB->get_record_select(
                    'user', 'deleted = 0 AND mnethostid = :h AND (LOWER(username) = :u OR LOWER(email) = :e)',
                    ['h' => $CFG->mnet_localhost_id, 'u' => $t, 'e' => $t], 'id', IGNORE_MULTIPLE
                );
                if (!$user) {
                    $missing[] = $t;
                } else if ($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $user->id])) {
                    $already++;
                } else {
                    cohort_add_member($cohort->id, $user->id);
                    $added++;
                }
            }
            $msg = get_string(
                'ch_pasted', 'theme_lmslabs', ['added' => $added, 'already' => $already,
                'cohort' => format_string($cohort->name)]
            );
            if ($missing) {
                $msg .= ' ' . get_string(
                    'ch_missing', 'theme_lmslabs', s(
                    implode(', ', array_slice($missing, 0, 10)) .
                    (count($missing) > 10 ? '…' : '')
                )
                );
            }
            redirect(
                new moodle_url($url, ['open' => $cohort->id]), $msg, null,
                $missing ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
            );
            break;
    }
}

$open = optional_param('open', 0, PARAM_INT);
$cohorts = $DB->get_records('cohort', ['contextid' => $context->id, 'component' => ''], 'name ASC');
$counts = $DB->get_records_sql_menu(
    "SELECT cm.cohortid, COUNT(1) FROM {cohort_members} cm JOIN {user} u ON u.id = cm.userid
                                      WHERE u.deleted = 0 GROUP BY cm.cohortid"
);
$sync = $DB->get_records_sql_menu("SELECT customint1, COUNT(1) FROM {enrol} WHERE enrol = 'cohort' GROUP BY customint1");
$rows = [];
$totalmembers = 0;
foreach ($cohorts as $c) {
    $n = (int)($counts[$c->id] ?? 0);
    $totalmembers += $n;
    $faces = [];
    $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    foreach ($DB->get_records_sql("SELECT u.id, $namefields FROM {cohort_members} cm
                                     JOIN {user} u ON u.id = cm.userid
                                    WHERE cm.cohortid = :c AND u.deleted = 0 ORDER BY cm.timeadded DESC", ['c' => $c->id], 0, 5) as $u) {
        $faces[] = ['initials' => core_text::strtoupper(core_text::substr($u->firstname, 0, 1) . core_text::substr($u->lastname, 0, 1)),
            'name' => fullname($u), 'tint' => $u->id % 6];
    }
    $rows[] = [
        'id' => $c->id,
        'name' => format_string($c->name),
        'rawname' => $c->name,
        'description' => trim(html_to_text(format_text($c->description, $c->descriptionformat), 0, false)),
        'idnumber' => $c->idnumber,
        'members' => $n,
        'memberslabel' => get_string($n === 1 ? 'ch_member' : 'ch_members', 'theme_lmslabs', $n),
        'faces' => $faces,
        'more' => max(0, $n - 5),
        'courses' => (int)($sync[$c->id] ?? 0),
        'courseslabel' => get_string('ch_courses', 'theme_lmslabs', (int)($sync[$c->id] ?? 0)),
        'open' => $open == $c->id,
        'membersurl' => (new moodle_url('/theme/lmslabs/members.php', ['type' => 'cohort', 'id' => $c->id]))->out(false),
        'bulkurl' => (new moodle_url('/theme/lmslabs/bulk.php'))->out(false),
        'canmanage' => $canmanage,
        'canassign' => $canassign,
    ];
}

$data = [
    'action' => $url->out(false),
    'sesskey' => sesskey(),
    'rows' => $rows,
    'hasrows' => (bool)$rows,
    'canmanage' => $canmanage,
    'canassign' => $canassign,
    'summary' => get_string('ch_summary', 'theme_lmslabs', ['cohorts' => count($rows), 'members' => $totalmembers]),
    'bulkurl' => (new moodle_url('/theme/lmslabs/bulk.php'))->out(false),
    'classicurl' => \theme_lmslabs\local\router::classic('/cohort/index.php'),
    'i' => [
        'contact' => icons::svg('contact-round'), 'plus' => icons::svg('plus'), 'users' => icons::svg('users'),
        'book' => icons::svg('book-open'), 'pencil' => icons::svg('pencil'), 'trash' => icons::svg('trash'),
        'paste' => icons::svg('file-text'), 'userplus' => icons::svg('user-plus'), 'chev' => icons::svg('chevron-down'),
        'list' => icons::svg('list-checks'), 'search' => icons::svg('search'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/cohorts', $data);
echo $OUTPUT->footer();
