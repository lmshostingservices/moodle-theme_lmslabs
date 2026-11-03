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
 * Read-only facts about the current web request, taken through Moodle's own functions.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {
    /**
     * The query string of this request as an array of names and values (text only, for comparing and routing).
     *
     * @return array
     */
    public static function query(): array {
        $url = function_exists('me') ? me() : false;
        $params = [];
        if (is_string($url) && strpos($url, '?') !== false) {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
        }
        return clean_param_array($params, PARAM_TEXT, true);
    }

    /**
     * One query string value as text, or null when it is not there.
     *
     * @param string $name Parameter name.
     * @return string|null
     */
    public static function get(string $name): ?string {
        $query = self::query();
        return isset($query[$name]) && is_string($query[$name]) ? clean_param($query[$name], PARAM_ALPHANUMEXT) : null;
    }

    /**
     * The path of the script being run, such as /my/courses.php (empty when unknown).
     *
     * @return string
     */
    public static function script(): string {
        $url = function_exists('me') ? me() : false;
        if (!is_string($url)) {
            return '';
        }
        return (string)parse_url($url, PHP_URL_PATH);
    }

    /**
     * True for a plain page view (no form was posted).
     *
     * @return bool
     */
    public static function is_get(): bool {
        $method = filter_input(INPUT_SERVER, 'REQUEST_METHOD') ?: getenv('REQUEST_METHOD') ?: 'GET';
        return $method === 'GET';
    }
}
