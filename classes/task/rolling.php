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


namespace theme_lmslabs\task;

/**
 * Resets learners in courses with rolling intakes and sends their reminders.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rolling extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_rolling', 'theme_lmslabs');
    }

    /**
     * Run it.
     */
    public function execute() {
        \theme_lmslabs\local\rolling::run_all();
    }
}
