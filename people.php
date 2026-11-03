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
 * People: search and filter everyone on the site, with saved views and bulk actions.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\filterbar;
use theme_lmslabs\local\people;
use theme_lmslabs\local\views;
use theme_lmslabs\local\audience;
use theme_lmslabs\local\icons;

require_login(null, false);
$context = context_system::instance();
if (!has_any_capability(['moodle/user:update', 'moodle/user:delete'], $context)) {
    require_capability('moodle/user:update', $context);
}

$base = new moodle_url('/theme/lmslabs/people.php');
views::apply_default('people', $base);
$page = optional_param('page', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url($base);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('people', 'theme_lmslabs'));
$PAGE->set_heading(get_string('people', 'theme_lmslabs'));
$PAGE->add_body_class('ll-listpage');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/filterbar.js'));

$courses = optional_param_array('f_course', [], PARAM_INT);
$fb = new filterbar('people', $base, people::pills($courses), people::quick_cards(), people::sorts(), true);
$results = people::results($fb, $page);
$total = $results['total'];

$cohorts = [];
foreach ($DB->get_records('cohort', ['component' => ''], 'name ASC', 'id, name') as $c) {
    $cohorts[] = ['id' => $c->id, 'name' => format_string($c->name)];
}
$from = $total ? $page * people::PERPAGE + 1 : 0;
$to = min($total, ($page + 1) * people::PERPAGE);

$data = [
    'fb' => $fb->export(audience::field_options()),
    'rows' => $results['rows'],
    'hasrows' => (bool)$results['rows'],
    'total' => $total,
    'range' => get_string('fb_range', 'theme_lmslabs', ['from' => $from, 'to' => $to, 'total' => $total]),
    'prevurl' => $page > 0 ? $fb->url(['page' => $page - 1])->out(false) : null,
    'nexturl' => $to < $total ? $fb->url(['page' => $page + 1])->out(false) : null,
    'adduserurl' => (new moodle_url('/user/editadvanced.php', ['id' => -1]))->out(false),
    'uploadurl' => (new moodle_url('/admin/tool/uploaduser/index.php'))->out(false),
    'bulkurl' => (new moodle_url('/theme/lmslabs/people_bulk.php'))->out(false),
    'returnurl' => $fb->url(['page' => $page ?: null])->out_as_local_url(false),
    'sesskey' => sesskey(),
    'cohorts' => $cohorts,
    'hascohorts' => (bool)$cohorts,
    'classicurl' => (new moodle_url('/admin/user.php', ['classic' => 1]))->out(false),
    'i' => [
        'userplus' => icons::svg('user-plus'), 'upload' => icons::svg('upload'), 'pencil' => icons::svg('pencil'),
        'eye' => icons::svg('eye'), 'users' => icons::svg('users'), 'x' => icons::svg('x'),
        'contact' => icons::svg('contact-round'), 'more' => icons::svg('list-checks'),
        'pause' => icons::svg('user-x'), 'play' => icons::svg('user-check'), 'left' => icons::svg('arrow-left'),
        'right' => icons::svg('arrow-up-right'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/people', $data);
echo $OUTPUT->footer();
