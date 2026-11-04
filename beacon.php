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
 * Shown from the Beacon Reports menu when Beacon Reports is not installed.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;

require_login(null, false);
$view = optional_param('view', 'overview', PARAM_ALPHA);

// If Beacon has been installed since, go straight there.
if (core_component::get_component_directory('local_beacon')) {
    $views = \theme_lmslabs\local\nav::BEACON_VIEWS;
    [, $path, $params] = $views[$view] ?? $views['overview'];
    redirect(new moodle_url($path, $params));
}

$context = context_system::instance();
require_capability('moodle/site:viewreports', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/lmslabs/beacon.php', ['view' => $view]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('beaconreports', 'theme_lmslabs'));
$PAGE->set_heading(get_string('beaconreports', 'theme_lmslabs'));

$data = [
    'icon' => icons::svg('chart-pie'),
    'arrow' => icons::svg('arrow-up-right'),
    'mail' => icons::svg('mail'),
    'url' => \theme_lmslabs\local\promo::LABS,
    'support' => 'mailto:' . \theme_lmslabs\local\promo::SUPPORT,
    'features' => [
        ['icon' => icons::svg('layout-dashboard'), 'name' => get_string('beacon_stats', 'theme_lmslabs'),
            'text' => get_string('beacon_stats_desc', 'theme_lmslabs')],
        ['icon' => icons::svg('gauge'), 'name' => get_string('beacon_kpis', 'theme_lmslabs'),
            'text' => get_string('beacon_kpis_desc', 'theme_lmslabs')],
        ['icon' => icons::svg('scroll-text'), 'name' => get_string('beacon_reports', 'theme_lmslabs'),
            'text' => get_string('beacon_reports_desc', 'theme_lmslabs')],
        ['icon' => icons::svg('chart-pie'), 'name' => get_string('beacon_charts', 'theme_lmslabs'),
            'text' => get_string('beacon_charts_desc', 'theme_lmslabs')],
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/beacon_missing', $data);
echo $OUTPUT->footer();
