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
 * Get started: the Site Owner's quick start checklist.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\setup;
use theme_lmslabs\local\icons;

require_login(null, false);
require_capability('theme/lmslabs:managebrand', context_system::instance());

// Hide or show the sidebar card.
if ($hide = optional_param('hide', null, PARAM_INT)) {
    require_sesskey();
    set_user_preference(setup::PREF_HIDDEN, 1);
    redirect(get_local_referer(false) ?: new moodle_url('/my/'));
}
if (optional_param('show', 0, PARAM_BOOL)) {
    require_sesskey();
    unset_user_preference(setup::PREF_HIDDEN);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/lmslabs/getstarted.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('setup_title', 'theme_lmslabs'));
$PAGE->set_heading(get_string('setup_title', 'theme_lmslabs'));

$steps = setup::steps();
$progress = setup::progress($steps);
$next = null;
foreach ($steps as &$step) {
    if (!$step['done'] && !$next) {
        $step['isnext'] = true;
        $next = $step;
    }
}
unset($step);

$data = $progress + [
    'steps' => $steps,
    'dash' => round(150.8 * (1 - $progress['percent'] / 100), 2),
    'hidden' => (bool)get_user_preferences(setup::PREF_HIDDEN, 0),
    'showurl' => (new moodle_url('/theme/lmslabs/getstarted.php', ['show' => 1, 'sesskey' => sesskey()]))->out(false),
    'hideurl' => (new moodle_url('/theme/lmslabs/getstarted.php', ['hide' => 1, 'sesskey' => sesskey()]))->out(false),
    'i' => ['check' => icons::svg('check'), 'arrow' => icons::svg('arrow-up-right'), 'sparkles' => icons::svg('sparkles')],
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/setup', $data);
echo $OUTPUT->footer();
