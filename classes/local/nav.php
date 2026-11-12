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
use moodle_page;
use moodle_url;

/**
 * Builds the curated left-hand navigation.
 *
 * Everything trainers, teachers and site managers use day to day is one click away.
 * The long tail of technical settings is reached through Boost view.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nav {
    /** Beacon Reports pages: key => [string id, page, params, icon]. */
    const BEACON_VIEWS = [
        'overview' => ['beacon_overview', '/local/beacon/index.php', [], 'layout-dashboard'],
        'stats' => ['beacon_stats', '/local/beacon/index.php', ['view' => 'stats'], 'layout-dashboard'],
        'kpis' => ['beacon_kpis', '/local/beacon/index.php', ['view' => 'kpis'], 'gauge'],
        'reports' => ['beacon_reports', '/local/beacon/index.php', ['view' => 'reports'], 'scroll-text'],
        'charts' => ['beacon_charts', '/local/beacon/charts.php', [], 'chart-pie'],
        'studio' => ['beacon_studio', '/local/beacon/studio.php', [], 'chart-column'],
    ];

    /**
     * Admin Tools: key => [component, page, string id, icon]. Shown only when the plugin is installed.
     * To add a tool, add one line here and its string in lang/en/theme_lmslabs.php.
     */
    const ADMIN_TOOLS = [
        'speedup' => ['tool_speedup', '/admin/tool/speedup/index.php', 'speedup', 'gauge', 'tool/speedup:view'],
        'simple2fa' => ['auth_simple2fa', '/admin/settings.php', 'simple2fa', 'shield-check', 'moodle/site:config',
            ['section' => 'authsettingsimple2fa']],
    ];


    /**
     * LMS Labs add-ons: key => [component, group, string id, icon, capability, page, params].
     * When the plugin is installed, its page appears in the group named here. When it is not, site admins see a
     * quiet entry under "LMS Labs add-ons" that opens a page explaining what it does.
     */
    const ADDONS = [
        'emailstudents' => ['local_emailstudents', 'users', 'ad_emailstudents', 'mail', 'local/emailstudents:sendsite',
            '/local/emailstudents/compose.php', []],
        'clientportal' => ['local_clientportal', 'users', 'ad_clientportal', 'building-2',
            'local/clientportal:manageclients', '/local/clientportal/admin/clients.php', []],
        'coursebuilder' => ['local_aicoursebuilder', 'courses', 'ad_coursebuilder', 'sparkles',
            'local/aicoursebuilder:create', '/local/aicoursebuilder/index.php', []],
        'groupmanager' => ['local_groupmanager', 'courses', 'ad_groupmanager', 'users-round', 'local/groupmanager:view',
            '/local/groupmanager/index.php', []],
        'availability' => ['local_courseavailabilitydelay', 'courses', 'ad_availability', 'calendar-clock',
            'local/courseavailabilitydelay:manage', '/local/courseavailabilitydelay/manage.php', []],
        'recertify' => ['local_recertify', 'courses', 'ad_recertify', 'rotate-ccw', 'local/recertify:manage',
            '/local/recertify/index.php', []],
        'completionsuspend' => ['local_completionsuspend', 'courses', 'ad_completionsuspend', 'user-x',
            'local/completionsuspend:manage', '/local/completionsuspend/index.php', []],
        'prerequisite' => ['enrol_prerequisite', 'courses', 'ad_prerequisite', 'lock-keyhole', 'moodle/site:config',
            '/enrol/prerequisite/gates.php', []],
        'trainingmatrix' => ['local_trainingmatrix', 'training', 'ad_trainingmatrix', 'layout-grid',
            'local/trainingmatrix:view', '/local/trainingmatrix/index.php', []],
        'trainingpathways' => ['local_trainingpathways', 'training', 'ad_trainingpathways', 'route',
            'local/trainingpathways:view', '/local/trainingpathways/manage.php', []],
        'remedial' => ['local_aiquizremedial', 'training', 'ad_remedial', 'life-buoy', 'local/aiquizremedial:viewown',
            '/local/aiquizremedial/index.php', []],
        'studentexport' => ['local_student_export', 'training', 'ad_studentexport', 'download', 'local/student_export:view',
            '/local/student_export/index.php', []],
        'mediaoptimiser' => ['local_mediaoptimiser', 'admintools', 'ad_mediaoptimiser', 'image',
            'local/mediaoptimiser:viewdashboard', '/local/mediaoptimiser/index.php', []],
        'cohortbranding' => ['local_cohortbranding', 'admintools', 'ad_cohortbranding', 'palette',
            'local/cohortbranding:manage', '/local/cohortbranding/index.php', []],
    ];

    /** @var array add-ons that are not installed, collected while the menu is built */
    protected $missing = [];

    /** @var moodle_page */
    protected $page;

    /** @var array groups built so far */
    protected $groups = [];

    /** @var array flat list of every item, for active-matching and the command palette */
    protected $all = [];

    /**
     * Constructor.
     *
     * @param moodle_page $page
     */
    public function __construct(moodle_page $page) {
        $this->page = $page;
    }

    /**
     * Shorthand for a theme string.
     *
     * @param string $id
     * @return string
     */
    protected static function str(string $id): string {
        return get_string($id, 'theme_lmslabs');
    }

    /**
     * Build an item if the user has the capability.
     *
     * @param string $key
     * @param string $name
     * @param string $path
     * @param array $params
     * @param string $icon
     * @param string|array|null $caps one capability or any-of list; null = no check
     * @param \context|null $context
     * @return array|null
     */
    protected function item(string $key, string $name, string $path, array $params, string $icon,
            $caps = null, $context = null): ?array {
        if ($caps !== null) {
            $context = $context ?? context_system::instance();
            if (!has_any_capability((array)$caps, $context)) {
                return null;
            }
        }
        $url = new moodle_url($path, $params);
        return [
            'key' => $key,
            'name' => $name,
            'url' => $url->out(false),
            'path' => $path,
            'params' => $params,
            'icon' => icons::svg($icon),
            'iconname' => $icon,
            'active' => false,
            'haschildren' => false,
            'children' => [],
            'open' => false,
        ];
    }

    /**
     * Add a group, dropping empty items.
     *
     * @param string $key
     * @param string|null $label
     * @param array $items
     * @param bool $collapsible
     */
    protected function group(string $key, ?string $label, array $items, bool $collapsible = true): void {
        $items = array_values(array_filter($items));
        if (!$items) {
            return;
        }
        $this->groups[] = [
            'key' => $key,
            'label' => $label,
            'haslabel' => $label !== null,
            'collapsible' => $collapsible && $label !== null,
            'items' => $items,
        ];
    }

    /**
     * Is a script present in this Moodle version?
     *
     * @param string $path relative path such as /question/banks.php
     * @return bool
     */
    protected static function exists(string $path): bool {
        global $CFG;
        return file_exists($CFG->dirroot . $path);
    }

    /**
     * Build everything and return the template context.
     *
     * @return array
     */
    public function export(): array {
        global $CFG, $SITE, $USER;

        if (!isloggedin() || isguestuser()) {
            $this->group(
                'main', null, [
                $this->item('home', get_string('home'), '/', [], 'house'),
                $this->item('courses', get_string('courses'), '/course/index.php', [], 'book-open'),
            ], false
            );
            return $this->finish();
        }

        $syscontext = context_system::instance();

        // Personal workspace. Calendar, messages, grades and badges live in the avatar menu.
        $this->group(
            'main', null, [
            $this->item('dashboard', get_string('myhome'), '/my/', [], 'layout-dashboard'),
            $this->item('mycourses', get_string('mycourses'), '/theme/lmslabs/courses.php', [], 'book-open'),
        ], false
        );

        // The course being viewed.
        $course = $this->page->course;
        if ($course && $course->id != SITEID) {
            $this->add_course_groups($course);
        }

        // Site management, curated. Inside a course the sidebar is about that course only.
        if (!$course || $course->id == SITEID) {
            $this->add_admin_groups($syscontext);
        }

        // Help is for everyone, last in the menu.
        $this->group(
            'help', self::str('help'), [
            $this->item('helpcentre', self::str('helpcentre'), '/theme/lmslabs/help.php', [], 'life-buoy'),
        ]
        );

        return $this->finish();
    }

    /**
     * The site management groups on their own, for the top bar's admin menu inside a course.
     *
     * @return array groups, or [] when the user manages nothing
     */
    public function admin_menu(): array {
        $this->groups = [];
        $this->add_admin_groups(context_system::instance());
        $out = [];
        foreach ($this->groups as $g) {
            $items = [];
            foreach ($g['items'] as $it) {
                // Flatten submenus (Beacon reports) to their first page.
                $items[] = ['name' => $it['name'], 'url' => $it['url'], 'icon' => $it['icon']];
            }
            $out[] = ['label' => $g['label'], 'items' => $items];
        }
        $this->groups = [];
        // Help is always one click away from the admin menu too.
        $out[] = ['label' => self::str('help'), 'items' => [[
            'name' => self::str('helpcentre'),
            'url' => (new moodle_url('/theme/lmslabs/help.php'))->out(false),
            'icon' => icons::svg('life-buoy'),
        ]]];
        return $out;
    }

    /**
     * Every page this person can open from the menus, flat, for the top bar shortcut picker.
     *
     * The same role-aware items as the sidebar, the site admin menu and the avatar menu. Course tools are included
     * only when the page is inside a course; they are marked with scope 'c' and show in every course where the
     * person can use them.
     *
     * @return array key => ['key', 'name', 'group', 'url', 'icon', 'scope']
     */
    public function catalogue(): array {
        global $CFG;
        $this->groups = [];
        $this->missing = [];
        if (!isloggedin() || isguestuser()) {
            return [];
        }
        $this->group(
            'main', self::str('sc_g_main'), [
            $this->item('dashboard', get_string('myhome'), '/my/', [], 'layout-dashboard'),
            $this->item('mycourses', get_string('mycourses'), '/theme/lmslabs/courses.php', [], 'book-open'),
            $this->item('p_calendar', get_string('calendar', 'calendar'), '/theme/lmslabs/calendar.php', [], 'calendar'),
            $this->item('p_grades', get_string('grades', 'grades'), '/theme/lmslabs/mygrades.php', [], 'chart-column'),
            !empty($CFG->enablebadges) ? $this->item(
                'p_badges', self::str('mybadges'), '/theme/lmslabs/mybadges.php', [], 'award'
            ) : null,
            !empty($CFG->messaging) ? $this->item(
                'p_messages', get_string('messages', 'message'), '/theme/lmslabs/messages.php', [], 'message-square'
            ) : null,
            $this->item(
                'p_notifications', get_string('notifications', 'message'), '/theme/lmslabs/notifications.php', [], 'bell'
            ),
            $this->item('p_profile', get_string('profile'), '/theme/lmslabs/profile.php', [], 'user-round'),
            $this->item('p_preferences', get_string('preferences'), '/theme/lmslabs/prefs.php', [], 'settings'),
        ]
        );
        $course = $this->page->course;
        if ($course && $course->id != SITEID) {
            $this->add_course_groups($course);
        }
        $this->add_admin_groups(context_system::instance());
        $this->group(
            'help', self::str('help'), [
            $this->item('helpcentre', self::str('helpcentre'), '/theme/lmslabs/help.php', [], 'life-buoy'),
        ]
        );

        $out = [];
        foreach ($this->groups as $group) {
            // Add-ons the site does not have are adverts, not destinations.
            if ($group['key'] === 'addons') {
                continue;
            }
            $incourse = in_array($group['key'], ['course', 'coursereuse'], true);
            foreach ($group['items'] as $item) {
                $list = $item['haschildren'] ? $item['children'] : [$item];
                foreach ($list as $it) {
                    if (isset($out[$it['key']])) {
                        continue;
                    }
                    $out[$it['key']] = [
                        'key' => $it['key'],
                        'name' => $item['haschildren'] ? $item['name'] . ': ' . $it['name'] : $it['name'],
                        'group' => $incourse ? self::str('sc_g_course') : (string)($group['label'] ?? ''),
                        'url' => $it['url'],
                        'icon' => $it['iconname'] ?? 'link',
                        'scope' => $incourse ? 'c' : 's',
                    ];
                }
            }
        }
        $this->groups = [];
        $this->missing = [];
        return $out;
    }

    /**
     * Course groups.
     *
     * @param \stdClass $course
     */
    protected function add_course_groups(\stdClass $course): void {
        global $CFG;
        $ctx = context_course::instance($course->id);
        $id = $course->id;
        $canmanage = has_capability('moodle/course:update', $ctx);

        $grades = has_capability('moodle/grade:viewall', $ctx)
            ? $this->item(
                'cgrades', get_string('grades', 'grades'), '/theme/lmslabs/gradebook.php', ['id' => $id],
                'chart-column', 'moodle/grade:viewall', $ctx
            )
            : $this->item(
                'cgrades', get_string('grades', 'grades'), '/grade/report/user/index.php', ['id' => $id],
                'chart-column', 'moodle/grade:view', $ctx
            );

        $badges = null;
        if (!empty($CFG->enablebadges) && !empty($CFG->badges_allowcoursebadges)) {
            $badges = $this->item(
                'cbadges', get_string('badges', 'badges'), '/theme/lmslabs/coursebadges.php', ['id' => $id],
                'award', ['moodle/badges:createbadge', 'moodle/badges:awardbadge', 'moodle/badges:configurecriteria'], $ctx
            );
        }

        $qbank = $this->item(
            'qbank', get_string('questionbank', 'question'), '/theme/lmslabs/questionbank.php', ['id' => $id],
            'file-question', ['moodle/question:editall', 'moodle/question:useall', 'moodle/question:viewall',
                'moodle/question:editmine'], $ctx
        );

        $this->group(
            'course', format_string($course->shortname, true, ['context' => $ctx]), [
            $this->item('chome', self::str('coursehome'), '/course/view.php', ['id' => $id], 'library'),
            $this->item(
                'participants', get_string('participants'), '/theme/lmslabs/participants.php', ['id' => $id], 'users',
                'moodle/course:viewparticipants', $ctx
            ),
            $this->item(
                'grading', self::str('gr_title'), '/theme/lmslabs/grading.php', ['id' => $id], 'check-check',
                ['mod/assign:grade', 'mod/quiz:grade'], $ctx
            ),
            $grades,
            $this->item(
                'overrides', self::str('ov_title'), '/theme/lmslabs/overrides.php', ['id' => $id], 'clock',
                ['mod/assign:manageoverrides', 'mod/quiz:manageoverrides'], $ctx
            ),
            $this->item(
                'creports', get_string('reports'), '/theme/lmslabs/reports.php', ['id' => $id], 'chart-line',
                'moodle/site:viewreports', $ctx
            ),
            \core_component::get_component_directory('block_aiplugin_nav')
                && file_exists($CFG->dirroot . '/blocks/aiplugin_nav/course_report.php')
                ? $this->item(
                    'coursereport', self::str('coursereport'), '/blocks/aiplugin_nav/course_report.php', ['id' => $id],
                    'chart-pie', 'moodle/course:manageactivities', $ctx
                )
                : null,
            $this->item(
                'completion', self::str('completion'), '/theme/lmslabs/completion.php', ['id' => $id], 'circle-check-big',
                'moodle/course:update', $ctx
            ),
            $this->item(
                'groups', get_string('groups'), '/theme/lmslabs/groups.php', ['id' => $id], 'users-round',
                'moodle/course:managegroups', $ctx
            ),
            $this->item(
                'cenrol', get_string('enrolmentinstances', 'enrol'), '/theme/lmslabs/enrolmethods.php', ['id' => $id],
                'door-open', 'moodle/course:enrolreview', $ctx
            ),
            $qbank,
            $badges,
            $this->item(
                'csettings', get_string('settings'), '/course/edit.php', ['id' => $id], 'settings',
                'moodle/course:update', $ctx
            ),
        ]
        );

        if ($canmanage || has_any_capability(['moodle/backup:backupcourse', 'moodle/restore:restorecourse'], $ctx)) {
            $this->group(
                'coursereuse', self::str('coursereuse'), [
                $this->item(
                    'backup', get_string('backup'), '/theme/lmslabs/backup.php', ['id' => $id], 'hard-drive-download',
                    'moodle/backup:backupcourse', $ctx
                ),
                $this->item(
                    'restore', get_string('restore'), '/theme/lmslabs/restore.php', ['id' => $id],
                    'archive-restore', 'moodle/restore:restorecourse', $ctx
                ),
                $this->item(
                    'import', get_string('import'), '/theme/lmslabs/import.php', ['id' => $id], 'import',
                    'moodle/restore:restoretargetimport', $ctx
                ),
                $this->item(
                    'reset', get_string('reset'), '/theme/lmslabs/reset.php', ['id' => $id], 'rotate-ccw',
                    'moodle/course:reset', $ctx
                ),
            ]
            );
        }
    }


    /**
     * Menu items for the add-ons that belong to a group. Installed ones are real links; missing ones are collected
     * for the "LMS Labs add-ons" group, which only site admins see.
     *
     * @param string $group users, courses, training or admintools
     * @return array
     */
    protected function addon_items(string $group): array {
        $out = [];
        foreach (self::ADDONS as $key => [$component, $g, $string, $icon, $cap, $path, $params]) {
            if ($g !== $group) {
                continue;
            }
            if (!\core_component::get_component_directory($component)) {
                $this->missing[] = [$key, $string, $icon];
                continue;
            }
            if (!get_capability_info($cap)) {
                continue;
            }
            $label = self::str($string);
            // The training plugins show staff the management view and learners their own.
            if ($key === 'trainingmatrix') {
                if (!has_any_capability(['local/trainingmatrix:viewall', 'local/trainingmatrix:manage'], context_system::instance())) {
                    [$path, $label] = ['/local/trainingmatrix/my.php', self::str('ad_mytraining')];
                }
            } else if ($key === 'remedial') {
                // Trainers see the report for their courses; learners see their own revision modules.
                $isstaff = has_capability('local/aiquizremedial:viewall', context_system::instance())
                    || (bool) get_user_capability_course('local/aiquizremedial:viewall', null, true, '', '', 1);
                if ($isstaff) {
                    [$path, $label] = ['/local/aiquizremedial/insights.php', self::str('ad_remedialstaff')];
                } else {
                    $label = self::str('ad_myrevision');
                }
            } else if ($key === 'trainingpathways') {
                $sys = context_system::instance();
                if (has_capability('local/trainingpathways:manage', $sys)) {
                    $path = '/local/trainingpathways/manage.php';
                } else if (has_capability('local/trainingpathways:viewall', $sys)) {
                    $path = '/local/trainingpathways/dashboard.php';
                } else {
                    [$path, $label] = ['/local/trainingpathways/view.php', self::str('ad_mypathways')];
                }
            }
            if ($key === 'coursebuilder') {
                // Teachers given the builder in their own category can use it too, not just site-wide builders.
                $ui = '\\local_aicoursebuilder\\local\\ui';
                $held = class_exists($ui) ? $ui::can_build()
                    : (has_capability($cap, context_system::instance())
                        || (bool)\core_course_category::make_categories_list($cap));
                $out[] = $held ? $this->item('addon_' . $key, $label, $path, $params, $icon) : null;
                continue;
            }
            if ($key === 'remedial') {
                // Learners hold the capability in their courses, not site-wide.
                $held = has_capability($cap, context_system::instance())
                    || (bool) get_user_capability_course($cap, null, true, '', '', 1);
                $out[] = $held ? $this->item('addon_' . $key, $label, $path, $params, $icon) : null;
                continue;
            }
            $out[] = $this->item('addon_' . $key, $label, $path, $params, $icon, $cap);
        }
        return $out;
    }

    /**
     * The add-ons the site does not have yet, as one quiet group for site admins.
     */
    protected function add_missing_group(): void {
        if (!is_siteadmin() || get_config('theme_lmslabs', 'showaddons') === '0' || !$this->missing) {
            return;
        }
        $items = [];
        foreach ($this->missing as [$key, $string, $icon]) {
            $item = $this->item('addonmissing_' . $key, self::str($string), '/theme/lmslabs/addon.php', ['plugin' => $key], $icon);
            $item['tag'] = self::str('ad_tag');
            $item['hint'] = get_string('ad_hint', 'theme_lmslabs', self::str($string));
            $item['addon'] = true;
            $items[] = $item;
        }
        $this->group('addons', self::str('ad_group'), $items);
        $last = count($this->groups) - 1;
        $this->groups[$last]['startclosed'] = true;
    }

    /**
     * Curated site administration.
     *
     * @param \context_system $sys
     */
    protected function add_admin_groups($sys): void {
        global $CFG;
        $this->group(
            'users', get_string('users'), [
            $this->item(
                'users', self::str('allusers'), '/theme/lmslabs/people.php', [], 'users',
                ['moodle/user:update', 'moodle/user:delete']
            ),
            $this->item(
                'adduser', get_string('addnewuser'), '/user/editadvanced.php', ['id' => -1], 'user-plus',
                'moodle/user:create'
            ),
            $this->item(
                'uploadusers', get_string('uploadusers', 'tool_uploaduser'), '/theme/lmslabs/uploadusers.php', [],
                'upload', 'moodle/site:uploadusers'
            ),
            $this->item(
                'bulkusers', self::str('bulkactions'), '/admin/user/user_bulk.php', [], 'list-checks',
                'moodle/user:update'
            ),
            $this->item(
                'cohorts', get_string('cohorts', 'cohort'), '/cohort/index.php', [], 'contact-round',
                ['moodle/cohort:manage', 'moodle/cohort:view']
            ),
            ...$this->addon_items('users'),
        ]
        );

        $category = \core_course_category::get_default();
        $this->group(
            'courses', get_string('courses'), [
            $this->item(
                'managecourses', self::str('managecourses'), '/theme/lmslabs/manage.php', [], 'folder-tree',
                ['moodle/category:manage', 'moodle/course:create']
            ),
            $category ? $this->item(
                'addcourse', self::str('addcourse'), '/course/edit.php', ['category' => $category->id],
                'square-plus', 'moodle/course:create', \context_coursecat::instance($category->id)
            ) : null,
            $this->item(
                'uploadcourses', get_string('uploadcourses', 'tool_uploadcourse'), '/theme/lmslabs/uploadcourses.php',
                [], 'file-up', 'tool/uploadcourse:use'
            ),
            $this->item(
                'programs', self::str('programs'), '/theme/lmslabs/programs.php', [], 'layers',
                'theme/lmslabs:manageprograms'
            ),
            $this->item(
                'restoresite', self::str('restorecourse'), '/theme/lmslabs/restore.php', [],
                'archive-restore', 'moodle/restore:restorecourse'
            ),
            ...$this->addon_items('courses'),
        ]
        );

        $this->group('training', self::str('ad_traininggroup'), $this->addon_items('training'));

        // Beacon Reports (LMS Labs). Without it, the links explain what's missing.
        $installed = (bool)\core_component::get_component_directory('local_beacon');
        $caps = ['moodle/site:viewreports', 'theme/lmslabs:managebrand'];
        $children = [];
        foreach (self::BEACON_VIEWS as $view => [$string, $path, $params, $icon]) {
            // Report Studio arrived in Beacon 2.14; skip it on older installs.
            if ($installed && $view === 'studio' && !file_exists($CFG->dirroot . '/local/beacon/studio.php')) {
                continue;
            }
            // Without Beacon, each link opens a page explaining what's missing.
            [$path, $params] = $installed ? [$path, $params] : ['/theme/lmslabs/beacon.php', ['view' => $view]];
            $children[] = $this->item('beacon' . $view, self::str($string), $path, $params, $icon, $caps);
        }
        $children = array_values(array_filter($children));
        if ($children) {
            $parent = $this->item(
                'beacon', self::str('beaconreports'), $children[0]['path'], $children[0]['params'],
                'chart-pie', $caps
            );
            $parent['haschildren'] = true;
            $parent['children'] = $children;
            $this->group('reports', get_string('reports'), [$parent]);
        }

        // Admin Tools: LMS Labs tools that are installed, plus purge caches.
        $brand = 'theme/lmslabs:managebrand';
        $tools = [];
        foreach (self::ADMIN_TOOLS as $key => $def) {
            [$component, $path, $string, $icon, $cap] = $def;
            $params = $def[5] ?? [];
            // Shown only to people the tool itself lets in, so the link never leads to an error page.
            if (\core_component::get_component_directory($component) && get_capability_info($cap)) {
                $tools[] = $this->item($key, self::str($string), $path, $params, $icon, $cap);
            }
        }
        $tools = array_merge($tools, $this->addon_items('admintools'));
        $tools[] = $this->item(
            'purge', self::str('purgecaches'), '/theme/lmslabs/purge.php', ['sesskey' => sesskey()],
            'rotate-ccw', $brand
        );
        $this->group('admintools', self::str('admintools'), $tools);
        $this->add_missing_group();

        $this->group(
            'site', self::str('site'), [
            $this->item('setup', self::str('setup_title'), '/theme/lmslabs/getstarted.php', [], 'sparkles', $brand),
            $this->item(
                'themesettings', self::str('appearance'), '/theme/lmslabs/appearance.php', [],
                'palette', $brand
            ),
            is_siteadmin() ? $this->item(
                'hosting', self::str('hostingsettings'), '/theme/lmslabs/hosting.php',
                [], 'server'
            ) : null,
            is_siteadmin() ? $this->item(
                'boostview', self::str('boostview'), '/admin/search.php',
                ['lmsw' => 'boost', 'sesskey' => sesskey()], 'square-terminal'
            ) : null,
        ]
        );
    }

    /**
     * Mark the active item and return the export.
     *
     * @return array
     */
    protected function finish(): array {
        $pageurl = $this->page->url;
        $pagepath = $pageurl->get_path(false);
        $pageparams = $pageurl->params();

        // Every link with its position: [group, item] or [group, item, child].
        $candidates = [];
        foreach ($this->groups as $gi => $group) {
            foreach ($group['items'] as $ii => $item) {
                if ($item['haschildren']) {
                    foreach ($item['children'] as $ci => $child) {
                        $candidates[] = [[$gi, $ii, $ci], $child];
                    }
                } else {
                    $candidates[] = [[$gi, $ii], $item];
                }
            }
        }
        $best = null;
        $bestscore = -1;
        foreach ($candidates as [$pos, $item]) {
            $itempath = (new moodle_url($item['path']))->get_path(false);
            if ($itempath !== $pagepath && !($item['path'] === '/my/' && $pagepath === '/my/index.php')) {
                continue;
            }
            $score = 1;
            foreach ($item['params'] as $k => $v) {
                // The calendar keeps its link active whichever view is open.
                if ($k === 'sesskey' || ($k === 'view' && $item['key'] === 'calendar')) {
                    continue;
                }
                if (!isset($pageparams[$k]) || (string)$pageparams[$k] !== (string)$v) {
                    continue 2;
                }
                $score++;
            }
            if ($score > $bestscore) {
                $best = $pos;
                $bestscore = $score;
            }
        }
        // Inside an LMS Labs add-on (editing a branding, a client's staff, a course-builder plan...), keep that
        // add-on's menu item active: its inner pages need a record id, so they are never menu links themselves.
        if (!$best) {
            foreach ($candidates as [$pos, $item]) {
                if (strpos($item['key'], 'addon_') !== 0) {
                    continue;
                }
                $parts = explode('/', trim($item['path'], '/'));
                $root = '/' . $parts[0] . '/' . ($parts[1] ?? '') . '/';
                if (count($parts) > 1 && strpos($pagepath, $root) !== false) {
                    $best = $pos;
                    break;
                }
            }
        }
        if ($best) {
            [$gi, $ii] = $best;
            $this->groups[$gi]['hasactive'] = true;
            if (isset($best[2])) {
                $this->groups[$gi]['items'][$ii]['children'][$best[2]]['active'] = true;
                $this->groups[$gi]['items'][$ii]['open'] = true;
            } else {
                $this->groups[$gi]['items'][$ii]['active'] = true;
            }
        }

        $palette = [];
        foreach ($candidates as [$pos, $item]) {
            $group = $this->groups[$pos[0]];
            $parent = isset($pos[2]) ? $group['items'][$pos[1]]['name'] : null;
            $palette[] = [
                'n' => $item['name'],
                'g' => $parent ?? ($group['label'] ?? ''),
                'u' => $item['url'],
                'i' => $item['key'],
                's' => in_array($group['key'] ?? '', ['course', 'coursereuse']) ? 'c' : 's',
            ];
        }
        return [
            'groups' => $this->groups,
            'palette' => $palette,
        ];
    }
}
