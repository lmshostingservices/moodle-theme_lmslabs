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

/**
 * Shared behaviour: a setting listed under this theme that reads and writes one of Moodle's own core settings
 * (or a field of the site home course), so there is still one source of truth.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait coreaccess {

    /** @var string The core setting name, or the site course field name. */
    private $corename = '';

    /** @var bool True when the value is a field of the site home course. */
    private $sitefield = false;

    /**
     * Current value.
     *
     * @return string|null
     */
    public function get_setting() {
        global $SITE;
        if ($this->sitefield) {
            return $SITE->{$this->corename} ?? null;
        }
        $value = get_config('core', $this->corename);
        return $value === false ? null : $value;
    }

    /**
     * Save the value into Moodle's own setting.
     *
     * @param mixed $data New value.
     * @return string Empty on success, otherwise an error.
     */
    public function write_setting($data) {
        global $DB, $SITE;
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        if ($this->sitefield) {
            $DB->set_field('course', $this->corename, $data, ['id' => SITEID]);
            $SITE->{$this->corename} = $data;
            \cache_helper::purge_by_event('changesincourse');
        } else {
            set_config($this->corename, $data);
        }
        return '';
    }
}
