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

/**
 * Rolling intakes: reset each learner a set time after they enrol, warn them first, tell them after.
 *
 * Settings live per course in plugin config (rolling_<courseid>); where each learner is in their cycle lives in a user
 * preference (theme_lmslabs_rolling_<courseid>).
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rolling {
    /**
     * Settings for a course, with defaults.
     *
     * @param int $courseid
     * @return array
     */
    public static function settings(int $courseid): array {
        $saved = json_decode((string)get_config('theme_lmslabs', 'rolling_' . $courseid), true) ?: [];
        return $saved + [
            'on' => 0, 'months' => 12, 'warn' => 14, 'after' => 'restart', 'notify' => 1,
            'subject' => get_string('rl_msg_warn_subject_default', 'theme_lmslabs'),
            'body' => get_string('rl_msg_warn_body_default', 'theme_lmslabs'),
            'donesubject' => get_string('rl_msg_done_subject_default', 'theme_lmslabs'),
            'donebody' => get_string('rl_msg_done_body_default', 'theme_lmslabs'),
        ];
    }

    /**
     * Save settings.
     *
     * @param int $courseid
     * @param array $s
     */
    public static function save(int $courseid, array $s): void {
        set_config('rolling_' . $courseid, json_encode($s), 'theme_lmslabs');
    }

    /**
     * Learners in the course with where they are in their cycle.
     *
     * @param \stdClass $course
     * @param array $s settings
     * @return array[] each: user, start, due, state (waiting|soon|due|done)
     */
    public static function learners(\stdClass $course, array $s): array {
        global $DB;
        $ctx = context_course::instance($course->id);
        $students = array_keys(get_archetype_roles('student'));
        [$rin, $rp] = $DB->get_in_or_equal($students, SQL_PARAMS_NAMED, 'r');
        $rows = $DB->get_records_sql(
            "SELECT ue.id AS ueid, u.*, ue.timestart, ue.timecreated AS enrolled, e.id AS enrolid, e.enrol
                                        FROM {user_enrolments} ue
                                        JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :c
                                        JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                                       WHERE ue.status = 0
                                         AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.contextid = :ctx
                                                     AND ra.roleid $rin)", ['c' => $course->id, 'ctx' => $ctx->id] + $rp
        );
        $out = [];
        $now = time();
        foreach ($rows as $r) {
            $state = json_decode((string)get_user_preferences('theme_lmslabs_rolling_' . $course->id, '{}', $r->id), true) ?: [];
            $start = (int)($state['cycle'] ?? ($r->timestart ?: $r->enrolled));
            $due = strtotime('+' . (int)$s['months'] . ' months', $start);
            $st = $now >= $due ? 'due' : ($now >= $due - $s['warn'] * DAYSECS ? 'soon' : 'waiting');
            $out[$r->id] = ['user' => $r, 'start' => $start, 'due' => $due, 'state' => $st, 'saved' => $state];
        }
        return $out;
    }

    /**
     * Fill the placeholders in a message.
     *
     * @param string $text
     * @param \stdClass $user
     * @param \stdClass $course
     * @param int $due
     * @return string
     */
    public static function fill(string $text, \stdClass $user, \stdClass $course, int $due): string {
        return strtr(
            $text, [
            '{name}' => $user->firstname,
            '{course}' => format_string($course->fullname),
            '{date}' => userdate($due, get_string('strftimedaydate', 'langconfig')),
            '{url}' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ]
        );
    }

    /**
     * Send a message to a learner from the site.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param string $subject
     * @param string $body
     */
    protected static function notify(\stdClass $user, \stdClass $course, string $subject, string $body): void {
        $message = new \core\message\message();
        $message->component = 'moodle';
        $message->name = 'instantmessage';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->courseid = $course->id;
        $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $message->contexturlname = format_string($course->fullname);
        message_send($message);
    }

    /**
     * Clear one learner's work in the course using Moodle's own privacy deletion, then enrol or remove them.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param string $after restart|unenrol
     */
    public static function reset_learner(\stdClass $course, \stdClass $user, string $after): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $ctx = context_course::instance($course->id);
        // Remember who they are in this course so a restart puts them back the same way.
        $roleids = array_keys($DB->get_records('role_assignments', ['userid' => $user->id, 'contextid' => $ctx->id], '', 'roleid'));
        $groupids = array_keys(
            $DB->get_records_sql(
            "SELECT g.id FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
                                                      WHERE g.courseid = ? AND gm.userid = ?", [$course->id, $user->id]
        )
        );
        $enrol = $DB->get_record_sql(
            "SELECT e.* FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                       WHERE e.courseid = ? AND ue.userid = ? ORDER BY ue.id", [$course->id, $user->id], IGNORE_MULTIPLE
        );

        $wanted = [$ctx->id => true];
        foreach ($ctx->get_child_contexts() as $child) {
            $wanted[$child->id] = true;
        }
        // The privacy manager prints a progress trace; keep it out of pages and task logs.
        ob_start();
        $manager = new \core_privacy\manager();
        $approved = new \core_privacy\local\request\contextlist_collection($user->id);
        foreach ($manager->get_contexts_for_userid($user->id) as $list) {
            $ids = array_values(
                array_filter(
                $list->get_contextids(), function ($id) use ($wanted) {
                return isset($wanted[$id]);
            }
            )
            );
            if ($ids) {
                $approved->add_contextlist(new \core_privacy\local\request\approved_contextlist($user, $list->get_component(), $ids));
            }
        }
        $manager->delete_data_for_user($approved);
        ob_end_clean();

        if ($after === 'restart' && $enrol) {
            $plugin = enrol_get_plugin($enrol->enrol);
            if ($plugin) {
                // Our own message says what happened; don't also send the course's welcome message.
                $welcome = $enrol->customint4 ?? null;
                if ($welcome !== null) {
                    $DB->set_field('enrol', 'customint4', 0, ['id' => $enrol->id]);
                }
                $plugin->enrol_user($enrol, $user->id, $roleids ? reset($roleids) : null, time());
                foreach (array_slice($roleids, 1) as $rid) {
                    role_assign($rid, $user->id, $ctx->id);
                }
                foreach ($groupids as $gid) {
                    groups_add_member($gid, $user->id);
                }
                if ($welcome !== null) {
                    $DB->set_field('enrol', 'customint4', $welcome, ['id' => $enrol->id]);
                }
            }
        }
        $state = ['cycle' => $after === 'restart' ? time() : 0, 'warned' => 0, 'reset' => time()];
        set_user_preference('theme_lmslabs_rolling_' . $course->id, json_encode($state), $user->id);
    }

    /**
     * Run one course: warn those coming up, reset those due. Returns counts.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function run_course(\stdClass $course): array {
        $s = self::settings($course->id);
        $done = ['warned' => 0, 'reset' => 0];
        if (!$s['on']) {
            return $done;
        }
        foreach (self::learners($course, $s) as $l) {
            $u = $l['user'];
            $state = $l['saved'];
            if ($l['state'] === 'due') {
                self::reset_learner($course, $u, $s['after']);
                if ($s['notify']) {
                    self::notify(
                        $u, $course, self::fill($s['donesubject'], $u, $course, $l['due']),
                        self::fill($s['donebody'], $u, $course, $l['due'])
                    );
                }
                $done['reset']++;
            } else if ($l['state'] === 'soon' && $s['notify'] && empty($state['warned'])) {
                self::notify(
                    $u, $course, self::fill($s['subject'], $u, $course, $l['due']),
                    self::fill($s['body'], $u, $course, $l['due'])
                );
                $state['warned'] = time();
                set_user_preference('theme_lmslabs_rolling_' . $course->id, json_encode($state), $u->id);
                $done['warned']++;
            }
        }
        return $done;
    }

    /**
     * Run every course that has rolling intakes switched on.
     */
    public static function run_all(): void {
        global $DB;
        foreach ($DB->get_records_select('config_plugins', "plugin = 'theme_lmslabs' AND name LIKE 'rolling\\_%'") as $row) {
            $cfg = json_decode($row->value, true);
            if (empty($cfg['on'])) {
                continue;
            }
            $course = $DB->get_record('course', ['id' => (int)substr($row->name, 8)]);
            if ($course) {
                self::run_course($course);
            }
        }
    }
}
