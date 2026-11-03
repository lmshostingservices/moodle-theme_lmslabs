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

use moodle_url;

/**
 * Simple tenants: a cohort of people who get their own logo, colour and site name.
 *
 * This is branding and tidy views, not security separation: everyone still shares one site and one database.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenants {
    /** @var \stdClass|false|null tenant for this request */
    protected static $current = null;

    /**
     * Whether any tenant exists (a cheap check so sites without tenants pay nothing).
     *
     * @return bool
     */
    public static function any(): bool {
        return (int)get_config('theme_lmslabs', 'tenantcount') > 0;
    }

    /**
     * Keep the cheap "any tenant" flag up to date.
     */
    public static function recount(): void {
        global $DB;
        set_config('tenantcount', $DB->count_records('theme_lmslabs_tenant', ['enabled' => 1]), 'theme_lmslabs');
        self::$current = null;
    }

    /**
     * The tenant whose look applies to the person viewing: their own cohort's tenant, or the one named in the
     * login link before they sign in.
     *
     * @return \stdClass|null
     */
    public static function current(): ?\stdClass {
        global $DB, $USER, $SESSION;
        if (self::$current !== null) {
            return self::$current ?: null;
        }
        self::$current = false;
        if (!self::any() || during_initial_install()) {
            return null;
        }
        if (isloggedin() && !isguestuser()) {
            $t = $DB->get_record_sql(
                "SELECT t.* FROM {theme_lmslabs_tenant} t
                                        JOIN {cohort_members} cm ON cm.cohortid = t.cohortid
                                       WHERE cm.userid = :u AND t.enabled = 1 ORDER BY t.id", ['u' => $USER->id], IGNORE_MULTIPLE
            );
            if ($t) {
                self::$current = $t;
                return $t;
            }
            return null;
        }
        $id = (int)($SESSION->theme_lmslabs_tenant ?? 0);
        if ($id && ($t = $DB->get_record('theme_lmslabs_tenant', ['id' => $id, 'enabled' => 1]))) {
            self::$current = $t;
            return $t;
        }
        return null;
    }

    /**
     * Remember the tenant named in a login link (?tenant=idnumber).
     *
     * @param string $idnumber
     */
    public static function remember(string $idnumber): void {
        global $DB, $SESSION;
        if ($idnumber === '' || !self::any()) {
            return;
        }
        if ($t = $DB->get_record('theme_lmslabs_tenant', ['idnumber' => $idnumber, 'enabled' => 1])) {
            $SESSION->theme_lmslabs_tenant = $t->id;
            self::$current = null;
        }
    }

    /**
     * Public address of a tenant's logo.
     *
     * @param \stdClass $t
     * @return string|null
     */
    public static function logo_url(\stdClass $t): ?string {
        if (empty($t->logo)) {
            return null;
        }
        $url = moodle_url::make_pluginfile_url(
            \context_system::instance()->id, 'theme_lmslabs', 'tenantlogo', $t->id,
            '/', $t->logo
        );
        $url->param('t', $t->timemodified);
        return $url->out(false);
    }

    /**
     * All tenants for the admin page.
     *
     * @return array
     */
    public static function all(): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('theme_lmslabs_tenant', null, 'name ASC') as $t) {
            $cohort = $t->cohortid ? $DB->get_record('cohort', ['id' => $t->cohortid]) : null;
            $out[] = [
                'id' => (int)$t->id, 'name' => format_string($t->name), 'rawname' => $t->name, 'idnumber' => $t->idnumber,
                'cohortid' => (int)$t->cohortid,
                'cohort' => $cohort ? format_string($cohort->name) : get_string('tn_nocohort', 'theme_lmslabs'),
                'members' => $cohort ? $DB->count_records('cohort_members', ['cohortid' => $cohort->id]) : 0,
                'color' => $t->color ?: theme_lmslabs_accent(), 'hascolor' => !empty($t->color),
                'sitename' => (string)$t->sitename, 'logo' => self::logo_url($t), 'initial' => \core_text::strtoupper(
                    \core_text::substr($t->name, 0, 1)),
                'enabled' => (bool)$t->enabled,
                'loginurl' => (new moodle_url('/login/index.php', ['tenant' => $t->idnumber]))->out(false),
            ];
        }
        return $out;
    }

    /**
     * Delete a tenant and its logo.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'theme_lmslabs', 'tenantlogo', $id);
        $DB->delete_records('theme_lmslabs_tenant', ['id' => $id]);
        self::recount();
    }
}
