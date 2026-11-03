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
 * Collects what the user importer did, one row per CSV line, instead of printing Moodle's table.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uutracker extends \uu_progress_tracker {
    /** @var array[] Rows collected so far. */
    public static $rows = [];

    #[\Override]
    public function start() {
        self::$rows = [];
        $this->_row = null;
    }

    #[\Override]
    public function flush() {
        if (!empty($this->_row) && !empty($this->_row['line']['normal'])) {
            $text = function ($col, $level) {
                return trim(html_entity_decode(strip_tags(str_replace('<br />', '; ', $this->_row[$col][$level]))));
            };
            $status = $text('status', 'normal');
            $error = trim(
                $text('status', 'error') . ' ' . $text('username', 'error') . ' ' . $text('email', 'error') . ' ' .
                $text('firstname', 'error') . ' ' . $text('lastname', 'error') . ' ' . $text('password', 'error')
            );
            $warn = trim($text('status', 'warning') . ' ' . $text('username', 'warning') . ' ' . $text('email', 'warning'));
            $state = $error !== '' ? 'bad' : ($warn !== '' && $status === '' ? 'skip' : 'ok');
            self::$rows[] = [
                'line' => (int)$this->_row['line']['normal'],
                'username' => $text('username', 'normal') ?: $text('username', 'info'),
                'name' => trim($text('firstname', 'normal') . ' ' . $text('lastname', 'normal')),
                'email' => $text('email', 'normal'),
                'status' => $status,
                'note' => $error !== '' ? $error : $warn,
                'bad' => $state === 'bad', 'skip' => $state === 'skip', 'ok' => $state === 'ok', 'state' => $state,
            ];
        }
        $this->_row = [];
        foreach ($this->columns as $col) {
            $this->_row[$col] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
        }
    }

    #[\Override]
    public function close() {
        $this->flush();
    }
}
