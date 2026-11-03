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

namespace theme_lmslabs\admin;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/adminlib.php');

/**
 * A drop-down setting listed under this theme (component/name) that reads and writes a Moodle core setting.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coreselect extends \admin_setting_configselect {
    use coreaccess;

    /**
     * Constructor.
     *
     * @param string $name Setting name in this theme, without the component.
     * @param string|\lang_string $visiblename Label.
     * @param string|\lang_string $description Help text.
     * @param string $corename Core config name.
     * @param string $default Default value.
     * @param array $choices Value => label.
     */
    public function __construct(string $name, $visiblename, $description, string $corename, string $default,
            array $choices) {
        $this->corename = $corename;
        parent::__construct('theme_lmslabs/' . $name, $visiblename, $description, $default, $choices);
    }

    /**
     * Only a listed choice can be saved.
     *
     * @param mixed $data Chosen value.
     * @return string|bool True when valid, otherwise an error.
     */
    public function validate($data) {
        $this->load_choices();
        return is_array($this->choices) && array_key_exists($data, $this->choices) ? true : get_string('errorsetting', 'admin');
    }
}
