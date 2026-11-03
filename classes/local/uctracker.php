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

namespace theme_lmslabs\local;

/**
 * Collects what the course importer did, one row per CSV line, instead of printing Moodle's table.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uctracker extends \tool_uploadcourse_tracker {
    /** @var array[] Rows collected, keyed by line. */
    public $rows = [];

    /**
     * Collect instead of print.
     *
     * @param int $line
     * @param bool $outcome
     * @param array $status
     * @param array $data
     */
    public function output($line, $outcome, $status, $data) {
        $note = trim(html_entity_decode(strip_tags(implode('; ', (array)$status))));
        if (!isset($this->rows[$line])) {
            $this->rows[$line] = ['line' => $line, 'shortname' => (string)($data['shortname'] ?? ''),
                'fullname' => (string)($data['fullname'] ?? ''), 'id' => $data['id'] ?? 0, 'bad' => false, 'note' => ''];
        }
        if (!$outcome) {
            $this->rows[$line]['bad'] = true;
        }
        $this->rows[$line]['note'] = trim($this->rows[$line]['note'] . ' ' . $note);
        $this->rows[$line]['id'] = $data['id'] ?? $this->rows[$line]['id'];
    }

    /**
     * No table.
     */
    public function start() {
    }

    /**
     * No table.
     */
    public function finish() {
    }

    /**
     * No summary: the page counts rows itself.
     *
     * @param int $total
     * @param int $created
     * @param int $updated
     * @param int $deleted
     * @param int $errors
     */
    public function results($total, $created, $updated, $deleted, $errors) {
    }
}
