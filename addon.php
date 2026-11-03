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
 * Explains an LMS Labs add-on that is not installed on this site.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\nav;
use theme_lmslabs\local\promo;

require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$plugin = optional_param('plugin', '', PARAM_ALPHANUMEXT);
if (!isset(nav::ADDONS[$plugin])) {
    throw new moodle_exception('ad_unknown', 'theme_lmslabs', new moodle_url('/'));
}
[$component, $group, $string, $icon, , $path, $params] = nav::ADDONS[$plugin];

// Installed since the link was drawn: go straight to it.
if (core_component::get_component_directory($component)) {
    redirect(new moodle_url($path, $params));
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/lmslabs/addon.php', ['plugin' => $plugin]));
$PAGE->set_pagelayout('report');
$name = get_string($string, 'theme_lmslabs');
$PAGE->set_title($name);
$PAGE->set_heading($name);

$menus = ['users' => 'users', 'courses' => 'courses', 'training' => 'ad_traininggroup', 'admintools' => 'admintools'];
$features = [];
foreach ([1, 2, 3] as $n) {
    $features[] = ['icon' => icons::svg('check'), 'name' => get_string("{$string}_f{$n}", 'theme_lmslabs'), 'text' => ''];
}
$data = [
    'icon' => icons::svg($icon),
    'name' => $name,
    'desc' => get_string("{$string}_desc", 'theme_lmslabs'),
    'where' => get_string('ad_where', 'theme_lmslabs', get_string($menus[$group] ?? 'courses', 'theme_lmslabs')),
    'url' => promo::LABS,
    'features' => $features,
    'quicklinks' => (bool)core_component::get_component_directory('block_aiplugin_nav'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/addon', $data);
echo $OUTPUT->footer();
