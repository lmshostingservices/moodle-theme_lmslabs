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
 * Help centre: searchable, filterable how-to guides with screenshots.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\help;
use theme_lmslabs\local\icons;
use theme_lmslabs\local\promo;

require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/'));
}

$context = context_system::instance();
require_capability('moodle/user:editownprofile', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/lmslabs/help.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('help_title', 'theme_lmslabs'));
$PAGE->set_heading(get_string('help_title', 'theme_lmslabs'));
$PAGE->add_body_class('ll-help-page');
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/help.js'));

$mine = help::audience();
$articles = help::articles();
$topics = [];
foreach (help::topics() as $t) {
    $topics[] = ['name' => $t, 'count' => count(
        array_filter(
        $articles, function ($a) use ($t) {
        return $a['topic'] === $t;
    }
    )
    )];
}
$audiences = [
    ['key' => 'all', 'label' => get_string('help_aud_all', 'theme_lmslabs')],
    ['key' => 'learner', 'label' => get_string('help_aud_learner', 'theme_lmslabs')],
    ['key' => 'teacher', 'label' => get_string('help_aud_teacher', 'theme_lmslabs')],
    ['key' => 'admin', 'label' => get_string('help_aud_admin', 'theme_lmslabs')],
];
$data = [
    'articles' => $articles,
    'topics' => $topics,
    'audiences' => $audiences,
    'mine' => $mine,
    'total' => count($articles),
    'support' => promo::SUPPORT,
    'youtube' => promo::YOUTUBE,
    'i' => [
        'search' => icons::svg('search'), 'x' => icons::svg('x'), 'open' => icons::svg('arrow-up-right'),
        'bulb' => icons::svg('lightbulb'), 'mail' => icons::svg('mail'), 'video' => icons::svg('video'),
        'image' => icons::svg('image'), 'chev' => icons::svg('chevron-right'),
    ],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/help', $data);
echo $OUTPUT->footer();
