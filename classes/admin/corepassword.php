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
 * A password setting listed under this theme (component/name) that reads and writes a Moodle core setting.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class corepassword extends \admin_setting_configpasswordunmask {
    use coreaccess;

    /**
     * Constructor.
     *
     * @param string $name Setting name in this theme, without the component.
     * @param string|\lang_string $visiblename Label.
     * @param string|\lang_string $description Help text.
     * @param string $corename Core config name.
     */
    public function __construct(string $name, $visiblename, $description, string $corename) {
        $this->corename = $corename;
        parent::__construct('theme_lmslabs/' . $name, $visiblename, $description, '');
    }

    /**
     * Passwords are stored as typed.
     *
     * @param mixed $data Value.
     * @return string|bool True when valid.
     */
    public function validate($data) {
        return is_string($data) ? true : get_string('errorsetting', 'admin');
    }
}
