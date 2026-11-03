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

use context_course;
use context_system;

/**
 * Add a person: the essentials, custom profile fields, and optional course and cohort access in one step.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adduser {
    /** Optional fields kept in the "More details" accordion. */
    const MORE = ['idnumber', 'institution', 'department', 'phone1', 'phone2', 'city'];

    /**
     * Custom profile fields, ready for the template.
     *
     * @param array $posted values posted back after a failed save
     * @return array
     */
    public static function profile_fields(array $posted = []): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $out = [];
        foreach (profile_get_user_fields_with_data(0) as $f) {
            if (!$f->is_editable() && !is_siteadmin()) {
                continue;
            }
            $field = $f->field;
            $name = 'profile_field_' . $field->shortname;
            $value = $posted[$name] ?? ($field->defaultdata ?? '');
            $row = [
                'name' => $name,
                'label' => format_string($field->name),
                'required' => (bool)$field->required,
                'tip' => trim(
                    html_to_text(
                    format_text($field->description ?? '', $field->descriptionformat ?? FORMAT_HTML),
                    0, false
                )
                ),
                'value' => is_array($value) ? '' : (string)$value,
                'is' . $field->datatype => true,
                'category' => $f->get_category_name() !== null ? format_string($f->get_category_name()) : '',
            ];
            if ($field->datatype === 'menu') {
                $row['options'] = [];
                foreach (explode("\n", (string)$field->param1) as $opt) {
                    $opt = trim($opt);
                    if ($opt !== '') {
                        $row['options'][] = ['value' => $opt, 'label' => format_string($opt), 'selected' => $opt === $row['value']];
                    }
                }
            }
            if ($field->datatype === 'checkbox') {
                $row['checked'] = !empty($row['value']);
            }
            if ($field->datatype === 'datetime' && is_numeric($row['value']) && $row['value']) {
                $row['value'] = date('Y-m-d', (int)$row['value']);
            }
            if (!in_array($field->datatype, ['text', 'menu', 'checkbox', 'textarea', 'datetime'])) {
                $row['istext'] = true;
            }
            $out[] = $row;
        }
        // Required fields first, so the quick path stays at the top.
        usort(
            $out, function ($a, $b) {
            return $b['required'] <=> $a['required'];
        }
        );
        return $out;
    }

    /**
     * Courses this person can enrol others into with manual enrolment.
     *
     * @return array
     */
    public static function courses(): array {
        global $DB;
        $out = [];
        $courses = $DB->get_records_select(
            'course', 'id <> :site', ['site' => SITEID], 'fullname ASC', 'id, fullname, shortname,
            category, visible'
        );
        foreach ($courses as $c) {
            if (!has_capability('enrol/manual:enrol', context_course::instance($c->id))) {
                continue;
            }
            $out[] = ['id' => $c->id, 'name' => format_string($c->fullname), 'short' => format_string($c->shortname),
                'hidden' => !$c->visible];
        }
        return $out;
    }

    /**
     * Site cohorts this person may add members to.
     *
     * @return array
     */
    public static function cohorts(): array {
        global $DB;
        $out = [];
        if (!has_capability('moodle/cohort:assign', context_system::instance())) {
            return $out;
        }
        foreach ($DB->get_records('cohort', ['contextid' => SYSCONTEXTID, 'component' => ''], 'name ASC', 'id, name') as $c) {
            $out[] = ['id' => $c->id, 'name' => format_string($c->name)];
        }
        return $out;
    }

    /**
     * Check the posted data.
     *
     * @param array $d posted values
     * @return array field => error message
     */
    public static function validate(array $d): array {
        global $DB, $CFG;
        $errors = [];
        $username = trim($d['username'] ?? '');
        if ($username === '') {
            $errors['username'] = get_string('required');
        } else if ($username !== \core_text::strtolower($username)) {
            $errors['username'] = get_string('usernamelowercase');
        } else if ($username !== clean_param($username, PARAM_USERNAME)) {
            $errors['username'] = get_string('invalidusername');
        } else if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $errors['username'] = get_string('usernameexists');
        }
        $email = trim($d['email'] ?? '');
        if ($email === '') {
            $errors['email'] = get_string('required');
        } else if (!validate_email($email)) {
            $errors['email'] = get_string('invalidemail');
        } else if (empty($CFG->allowaccountssameemail) &&
                $DB->record_exists_select(
                    'user', 'LOWER(email) = LOWER(:e) AND deleted = 0 AND mnethostid = :h',
                    ['e' => $email, 'h' => $CFG->mnet_localhost_id]
                )) {
            $errors['email'] = get_string('emailexists');
        }
        foreach (['firstname', 'lastname'] as $f) {
            if (trim($d[$f] ?? '') === '') {
                $errors[$f] = get_string('required');
            }
        }
        if (empty($d['createpassword'])) {
            $pw = $d['newpassword'] ?? '';
            $msg = '';
            if ($pw === '') {
                $errors['newpassword'] = get_string('required');
            } else if (!check_password_policy($pw, $msg)) {
                $errors['newpassword'] = trim(strip_tags($msg));
            }
        }
        foreach (self::profile_fields() as $pf) {
            if ($pf['required'] && empty($pf['ischeckbox']) && trim((string)($d[$pf['name']] ?? '')) === '') {
                $errors[$pf['name']] = get_string('required');
            }
        }
        return $errors;
    }

    /**
     * Create the person, then add course and cohort access.
     *
     * @param array $d posted values
     * @return \stdClass the new user
     */
    public static function create(array $d): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $user = new \stdClass();
        $user->username = trim($d['username']);
        $user->email = trim($d['email']);
        $user->firstname = trim($d['firstname']);
        $user->lastname = trim($d['lastname']);
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->suspended = empty($d['suspended']) ? 0 : 1;
        foreach (self::MORE as $f) {
            $user->$f = trim($d[$f] ?? '');
        }
        if (!empty($d['country'])) {
            $user->country = clean_param($d['country'], PARAM_ALPHA);
        }
        if (!empty($d['timezone'])) {
            $user->timezone = clean_param($d['timezone'], PARAM_TIMEZONE);
        }
        $withpassword = empty($d['createpassword']);
        if ($withpassword) {
            $user->password = $d['newpassword'];
        }
        // Moodle 5.3 moved user creation to \core\user::create_user().
        $user->id = method_exists('\core\user', 'create_user')
            ? \core\user::create_user($user, $withpassword, false)
            : user_create_user($user, $withpassword, false);

        // Custom profile fields.
        $profile = (object)['id' => $user->id];
        foreach (self::profile_fields() as $pf) {
            $v = $d[$pf['name']] ?? '';
            if (!empty($pf['isdatetime'])) {
                $v = $v ? strtotime($v . ' 12:00') : 0;
            } else if (!empty($pf['ischeckbox'])) {
                $v = empty($v) ? 0 : 1;
            } else if (!empty($pf['istextarea'])) {
                $v = ['text' => (string)$v, 'format' => FORMAT_HTML];
            }
            $profile->{$pf['name']} = $v;
        }
        profile_save_data($profile);

        \core\event\user_created::create_from_userid($user->id)->trigger();
        $user = $DB->get_record('user', ['id' => $user->id]);

        if (!empty($d['createpassword'])) {
            setnew_password_and_mail($user);
            unset_user_preference('create_password', $user);
            set_user_preference('auth_forcepasswordchange', 1, $user);
        } else if (!empty($d['forcechange'])) {
            set_user_preference('auth_forcepasswordchange', 1, $user);
        }

        // Course access: manual enrolment as a learner.
        $student = $DB->get_record('role', ['shortname' => 'student']);
        $manual = enrol_get_plugin('manual');
        foreach (array_unique(array_map('intval', (array)($d['courses'] ?? []))) as $courseid) {
            if (!$manual || !$student || !$DB->record_exists('course', ['id' => $courseid]) || $courseid == SITEID) {
                continue;
            }
            $ctx = context_course::instance($courseid);
            if (!has_capability('enrol/manual:enrol', $ctx)) {
                continue;
            }
            $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
            if (!$instance) {
                $course = get_course($courseid);
                $instanceid = $manual->add_default_instance($course);
                $instance = $DB->get_record('enrol', ['id' => $instanceid]);
            }
            $manual->enrol_user($instance, $user->id, $student->id);
        }

        // Cohort membership.
        if (!empty($d['cohort']) && has_capability('moodle/cohort:assign', context_system::instance())) {
            $cohortid = (int)$d['cohort'];
            if ($DB->record_exists('cohort', ['id' => $cohortid, 'component' => ''])) {
                cohort_add_member($cohortid, $user->id);
            }
        }
        return $user;
    }
}
