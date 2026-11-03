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
 * The Help centre articles. Screenshots live in pix/help/<key>.jpg (see tools/help_shots.py).
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class help {
    /** Topic order and labels. */
    const TOPICS = ['Getting around', 'Learning', 'Teaching', 'Admin', 'Full tutorials'];

    /**
     * The audience the current user is, highest first: admin, teacher, learner.
     *
     * @return string
     */
    public static function audience(): string {
        global $USER;
        $sys = \context_system::instance();
        if (is_siteadmin() || has_capability('moodle/site:config', $sys)) {
            return 'admin';
        }
        if (has_capability('moodle/course:create', $sys)) {
            return 'teacher';
        }
        $courses = get_user_capability_course('moodle/course:update', $USER->id, false, 'id', 'id', 1);
        return $courses ? 'teacher' : 'learner';
    }

    /**
     * All articles as template data.
     *
     * @return array
     */
    public static function articles(): array {
        global $CFG, $OUTPUT;
        $rows = [
            ['menu', 'Getting around', 'all', 'panel-left',
                'Find your way around',
                'The left menu holds everything you use. The top bar holds the tools you need on every page.',
                ['Use the left menu to move between your dashboard, courses and, for managers, people, courses and admin tools.', 'The top bar shows your breadcrumb, the Home and My courses icons and, for site admins, the Site administration icon.', 'Open the avatar menu (top right) for your profile, grades, calendar, messages, badges and preferences.', 'Click the arrow at the edge of the left menu to collapse it and give the page more room.'],
                'The Site administration icon sits in the same place on every page.',
                '/my/', 'navigation sidebar menu top bar avatar collapse'],
            ['search', 'Getting around', 'all', 'search',
                'Search and jump with Ctrl K',
                'Reach any page in two key presses instead of clicking through menus.',
                ['Press Ctrl K (Cmd K on a Mac) on any page.', 'Start typing a page name, such as "cohorts" or "gradebook".', 'Use the arrow keys to pick a result and press Enter to open it.', 'Press Esc to close the search.'],
                'Site admins can also search every Moodle setting from the same box.',
                '/my/', 'search command palette shortcut keyboard find'],
            ['accent', 'Getting around', 'all', 'palette',
                'Change the accent colour',
                'Pick the colour of buttons, links and highlights in one click.',
                ['Click the colour dot in the top bar.', 'Choose a colour from the swatches, or enter your own.', 'The whole site updates straight away and remembers your choice.'],
                'Site owners set the default colour for everyone in Appearance.',
                '/my/', 'colour color theme brand accent dot personalise'],
            ['mycourses', 'Learning', 'learner', 'book-open',
                'Your courses and progress',
                'See every course, how far through you are, and jump straight back in.',
                ['Open My courses from the left menu.', 'The Continue card at the top takes you to where you left off.', 'The progress card beside it shows how many courses are complete, in progress or not started.', 'Use the search box, filters and view switch (cards or list) to find a course.'],
                'Programs, if your site uses them, group courses into learning pathways.',
                '/theme/lmslabs/courses.php', 'my courses continue progress resume filter programs dashboard'],
            ['grades', 'Learning', 'learner', 'trophy',
                'Check your grades',
                'All your grades in one place, across every course.',
                ['Open the avatar menu and choose Grades.', 'Pick a course to see each activity and your mark.', 'Feedback from your trainer appears next to the mark.'],
                'Marks update as soon as your trainer releases them.',
                '/theme/lmslabs/mygrades.php', 'grades marks results feedback assessment'],
            ['calendar', 'Learning', 'learner', 'calendar',
                'Calendar and due dates',
                'See due dates, events and sessions together.',
                ['Open the avatar menu and choose Calendar.', 'Switch between month, week and day views.', 'Click an event to see the activity it belongs to.'],
                'Everything with a due date in your courses appears here automatically.',
                '/theme/lmslabs/calendar.php', 'calendar due dates events deadlines schedule'],
            ['messages', 'Learning', 'all', 'message-square',
                'Messages and notifications',
                'Talk to trainers and classmates and keep on top of alerts.',
                ['Open the avatar menu and choose Messages.', 'Pick a conversation or start a new one.', 'Choose what you are notified about, and how, in Notification preferences.'],
                'Turn off email notifications you do not need to keep your inbox clear.',
                '/theme/lmslabs/messages.php', 'messages chat notifications alerts email preferences'],
            ['studentview', 'Teaching', 'teacher', 'eye',
                'Preview a course as a student',
                'See exactly what learners see, then switch back in one click.',
                ['Open your course.', 'Click Student view in the top bar.', 'Browse the course as a learner would.', 'Click Return to my view to come back to your own view.'],
                'No need to enrol a test account.',
                '/my/', 'student view preview learner role switch test'],
            ['addcourse', 'Teaching', 'teacher', 'square-plus',
                'Create a course',
                'Set up a new course in one screen.',
                ['Choose Add a course from the left menu.', 'Enter the course name and pick a category.', 'Choose the format and start date, then save.', 'You land in the new course, ready to add content.'],
                'Use Programs to group related courses once you have several.',
                '/course/edit.php', 'create new course add setup category format'],
            ['aiformat', 'Teaching', 'teacher', 'sparkles',
                'Change the AI course format look',
                'Control the banner, arrows, course index and cards from the course settings.',
                ['Open your course and go to Settings.', 'Find the AI course format card.', 'Switch the banner, arrows, course index and cards on or off.', 'Save. The course page updates straight away.'],
                "Section colours and tints are set in the format's own settings.",
                '/my/', 'ai course format banner cards index player colours'],
            ['gradebook', 'Teaching', 'teacher', 'chart-column',
                'Mark and review in the gradebook',
                'See every learner and every assessment in one grid.',
                ['Open your course and choose Grades.', 'Choose how marks show: S / NYS, marks, percent, grade or points.', 'Click a Needs marking cell to open the marking screen.', 'Use the Cohort row for averages across the group.'],
                'Each teacher can pick their own display and colours.',
                '/my/', 'gradebook grades marking mark assess cells nys competent'],
            ['participants', 'Teaching', 'teacher', 'users',
                'Manage people in a course',
                'Enrol, filter and message the people in your course.',
                ['Open your course and choose Participants.', 'Search or filter by role, group or progress.', 'Select people to message them or change their enrolment.', 'Use Enrol users to add more.'],
                'Filters can be combined, for example "Learners" in "Group A".',
                '/my/', 'participants enrol users people roles filter message'],
            ['groups', 'Teaching', 'teacher', 'users-round',
                'Set up groups',
                'Split a course into groups for activities and marking.',
                ['Open your course and choose Groups.', 'Create a group, then add members.', 'Choose the group mode on an activity to use it.'],
                'You can filter marking and the gradebook by group.',
                '/my/', 'groups cohorts split class teams'],
            ['completion', 'Teaching', 'teacher', 'check-check',
                'Track activity completion',
                'Choose what counts as done so progress shows correctly.',
                ['Open your course and choose Completion.', 'Pick the completion rule for each activity.', 'Learners see ticks and progress as they finish.'],
                'Progress on My courses is based on these rules.',
                '/my/', 'completion progress tick done track rules'],
            ['people', 'Admin', 'admin', 'users',
                'Add and manage people',
                'Create users, upload many at once, and act on groups of people.',
                ['Choose All users to search and edit people.', 'Choose Add a user for a single person.', 'Choose Upload users to add many from a spreadsheet.', 'Choose Bulk actions to message, enrol or suspend a selection.'],
                'Upload shows a preview first, so mistakes are caught before anything is saved.',
                '/theme/lmslabs/people.php', 'users add create upload csv bulk accounts import suspend'],
            ['cohorts', 'Admin', 'admin', 'contact-round',
                'Use cohorts',
                'Group people once, then enrol them everywhere.',
                ['Choose Cohorts from the left menu.', 'Create a cohort and add people.', 'Enrol the whole cohort into a course in one step.'],
                'Cohorts are ideal for classes or intakes.',
                '/cohort/index.php', 'cohorts groups intake class bulk enrol'],
            ['courses', 'Admin', 'admin', 'folder-tree',
                'Manage courses and categories',
                'Organise, restore and bulk-create courses.',
                ['Choose Manage courses to browse categories and courses.', 'Use Add a course for one, or Upload courses for many.', 'Choose Restore a course to bring in a backup.', 'Use Programs to build pathways shown on My courses.'],
                'Drag categories to reorder them.',
                '/theme/lmslabs/manage.php', 'courses categories manage restore backup upload programs pathways'],
            ['setup', 'Admin', 'admin', 'sparkles',
                'Get started checklist',
                'Seven steps to a finished site, ticked off as you go.',
                ['Choose Get started from the Site group.', 'Follow the highlighted step, then come back.', 'Finished steps show a green tick.'],
                'Hide the sidebar card once you no longer need it.',
                '/theme/lmslabs/getstarted.php', 'setup get started checklist onboarding logo email'],
            ['appearance', 'Admin', 'admin', 'palette',
                'Brand your site',
                'Logo, colours and the site description for everyone.',
                ['Choose Appearance from the Site group.', 'Upload your logo and set the default accent colour.', 'Save to apply it across the site.'],
                'Cohort Branding can give each client their own look.',
                '/theme/lmslabs/appearance.php', 'brand logo colour color appearance theme settings'],
            ['tools', 'Admin', 'admin', 'puzzle',
                'Admin Tools and LMS Labs plugins',
                'Installed LMS Labs tools appear under Admin Tools in the left menu.',
                ['Install the plugin from Site administration, Plugins.', 'Open Admin Tools in the left menu.', 'Choose the tool to open its settings or dashboard.'],
                'Only plugins you have installed are listed.',
                '/my/', 'plugins tools 2fa speed up admin lms labs install'],
            ['boost', 'Admin', 'admin', 'square-terminal',
                'Switch to the full Moodle view',
                'Reach every advanced Moodle setting when you need it.',
                ['Open the Site group in the left menu.', 'Choose Boost view.', 'Switch back with the same link when you are done.'],
                'The Premium SaaS view has everyday tasks. Boost has everything.',
                '/theme/lmslabs/switch.php', 'boost classic technical advanced settings full moodle'],
            ['purge', 'Admin', 'admin', 'rotate-ccw',
                'Purge caches',
                'Clear caches after a change that does not show up.',
                ['Open Admin Tools in the left menu.', 'Choose Purge caches.', 'Reload the page you were on.'],
                'Safe to use at any time.',
                '/theme/lmslabs/purge.php', 'cache purge clear refresh not showing slow'],
            ['tut_programs', 'Full tutorials', 'admin', 'layers',
                'Programs: build a learning pathway from start to finish',
                'A complete walk-through: create a program, choose who sees it, order the courses and add headings.',
                ['Open Programs from the Courses group in the left menu. This page lists every program and shows what learners see on My courses.',
                 'Click New program (top right).',
                 'Name it the way learners will read it, for example "Diploma of Leadership, 2026 intake". Add a one-line description if you want one, such as "Complete the core units first, then choose two electives".',
                 'Under Who sees this program, choose Everyone to show it to every learner, or Specific people to show it only to learners who match your rules.',
                 'For specific people, tick cohorts, course categories or courses (anyone enrolled in them matches), type group names and press Enter, or add a profile rule such as Department is Operations. Use "Any" when one match is enough and "All" when a learner must match every rule.',
                 'Under Courses and rules, click Add courses, search by part of the course name and tick the ones you want. Courses you add appear as cards.',
                 'Drag the cards into the order learners should follow. The order becomes Step 1, Step 2 and so on on My courses.',
                 'Click Add heading to split the courses into stages, for example "Core units" and "Electives". Drag courses under the heading they belong to.',
                 'Optional: set an unlock rule on a course so it opens only after the previous course is done (see the unlock rules guide).',
                 'Click Save program. Learners see it straight away.',
                 'Check your work: open My courses, or use the preview on the Programs page to see it the way a learner does.',
                 'Shortcut: in Courses and categories, click Make a program on any category to turn its courses into a program in one click, then refine it here.'],
                'Teachers and Site Owners can always open every course. Rules and unlock steps only apply to learners.',
                '/theme/lmslabs/programs.php', 'program programs pathway courses order steps headings create build learning path tutorial full'],
            ['tut_programs_rules', 'Full tutorials', 'admin', 'lock',
                'Programs: unlock rules, hiding courses and who sees what',
                'Make courses open in sequence, after a delay, or stay hidden, and show different programs to different learners.',
                ['Open Programs and click Edit on the program you want to change.',
                 'In Courses and rules, find the course you want to control.',
                 'Choose its rule: Unlocks when the previous course is completed, Unlocks when everything in the previous course is submitted, Unlocks a set time after enrolment (for example 7 days), or Hidden from learners in this program.',
                 'Completed uses your completion settings, so check the course has completion rules first (Course, Completion).',
                 'Submitted is ideal for assessment-heavy units where learners may wait for marking: it opens the next course once every assessment is handed in, not marked.',
                 'Delay rules are good for drip-fed content. The learner sees "Opens" with the date on the locked card.',
                 'Locked cards show learners exactly what they must do, for example "Opens when you complete Work Health and Safety".',
                 'Click Save program, then preview it as a learner to confirm the card shows the lock message.',
                 'To show different programs to different people, create one program per group and use Specific people on each. A learner in several programs sees each one on My courses.',
                 'To retire a program, click Delete on the Programs page. Learners keep their courses and enrolments; only the layout and rules are removed.'],
                'If a learner says a course is locked, open the program and check the rule on the course before it.',
                '/theme/lmslabs/programs.php', 'program rules unlock locked sequence delay hidden completion submit prerequisite drip'],
            ['tut_speedup', 'Full tutorials', 'admin', 'gauge',
                'Speed Up: find out what is slowing your site',
                'Check that Speed Up is recording, run the one-minute Quick Check and read your results.',
                ['Open Speed Up from Admin Tools in the left menu. Speed Up is free from LMS Labs and works on Moodle 4.4 to 5.3.',
                 'Step 1, Set up: Speed Up checks that recording is on, that cron is processing data and whether the Excimer PHP extension is installed. Anything not ready shows plain steps to fix it.',
                 'Make sure cron runs every minute. If the page says data has not been processed, cron is the usual cause.',
                 'Optional but recommended: install the free Excimer extension on your server (for example "apt install php-excimer") and restart PHP. This lets Speed Up name the plugin that is making a page slow.',
                 'Step 2, Quick Check: click Run Quick Check. It is a safe one-minute test of your database, storage, cache and web server, each rated good, fair or poor.',
                 'Read each rating. Anything fair or poor shows what to change.',
                 'Step 3, Collect: leave Speed Up recording. Your real users do the testing and results are reliable after about a day of normal use.',
                 'Step 4, Fix: open Fix to see one problem per screen, ranked by how many hours of waiting it costs your users each day.',
                 'Open All reports for the detail: response times over time, Plugins ranked by the time they add, Pages ranked by total waiting time, Slowest requests, security and background tasks.',
                 'Look at the Speed Up health panel to confirm recording, sampling and the last processing time are all healthy.'],
                'Speed Up also adds OPcache, cache, session, log size and PHP security checks to the Moodle Performance overview report.',
                '/admin/tool/speedup/index.php', 'speed up slow performance site fast excimer opcache quick check plugins pages cron tutorial full'],
            ['tut_speedup_fix', 'Full tutorials', 'admin', 'zap',
                'Speed Up: fix a slow site and confirm it worked',
                'Work through the ranked fixes, apply one at a time and prove each one helped.',
                ['Open Speed Up, then Fix. The top fix saves your users the most waiting time, so start there.',
                 'Read What to change. Each fix has numbered steps, and Show me how opens the technical detail for your server.',
                 'Read What to keep so you do not undo something that is working.',
                 'Make the change. Common ones are enabling OPcache, mapping busy caches to a fast store such as Redis, turning off development settings, and shortening how long logs are kept.',
                 'Moodle development settings (debug footers, SQL tracing, template caching off) are listed by name so you can switch each one off.',
                 'Use How to confirm it worked. Come back after a day of normal use and compare the response times.',
                 'Open Plugins to see which add-ons cost the most time on every request. Update or remove the worst offender if you do not need it.',
                 'Open Pages to see which pages users wait for most, with typical and slowest 5% times. Open a slow request to see its call tree.',
                 'Check the software security result. If your PHP is missing security releases or is near end of life, plan an upgrade.',
                 'Repeat with the next fix. Use Purge caches in Admin Tools after settings changes so the change takes effect.'],
                'Speed Up is built to stay out of the way: it measures after the page has been sent, so it does not slow users down.',
                '/admin/tool/speedup/index.php', 'speed up fix slow opcache redis cache logs performance confirm'],
            ['tut_2fa', 'Full tutorials', 'admin', 'shield-check',
                'Two-factor sign-in (2FA): turn it on and enrol your team',
                'Require a code from an authenticator app at sign-in for the roles you choose.',
                ['Install the Simple 2FA and SSO plugin from Site administration, Plugins, then complete the upgrade.',
                 'Open 2FA and SSO from Admin Tools in the left menu.',
                 'Under 2FA Role Enforcement, leave Enforce 2FA for Site Administrators ticked. This is recommended.',
                 'Tick Teachers and Students if they must use 2FA too. Every other role on your site is listed below them, so you can require 2FA for exactly the roles you want.',
                 'Leave Bypass 2FA for web service token requests ticked if you use integrations that call Moodle with a token, such as the LMS Dashboard Hub.',
                 'Save changes.',
                 'Next time a person in an enforced role signs in, they see a QR code. They scan it with Microsoft Authenticator, Google Authenticator or any authenticator app.',
                 'On a phone they can tap Tap to open in Authenticator App. On a computer they type the manual entry key into their app (Add account, Other account).',
                 'They enter the 6-digit code and click Verify, then Continue to Dashboard.',
                 'Too many wrong codes locks 2FA for a few minutes, and a code cannot be used twice, so shoulder-surfers cannot replay it.',
                 'New phone? On the 2FA screen the person clicks Set up a new authenticator app. This creates a new QR code and cancels the old one.',
                 'Test it yourself first: sign in as a test teacher in a private window before rolling it out to everyone.'],
                'Roll out to admins first, then teachers, then learners, so you can support people as they go.',
                '/admin/settings.php?section=authsettingsimple2fa', '2fa two factor authenticator totp security login qr code google microsoft enforce roles tutorial full'],
            ['tut_sso', 'Full tutorials', 'admin', 'key-round',
                'Single sign-on (SSO): let people sign in with Google or Microsoft',
                'Add a Sign in with button to the login page using your identity provider.',
                ['Create an OAuth2 or OpenID Connect app with your identity provider, for example Google, Microsoft Entra, Okta. Add your Moodle site login address as the redirect address.',
                 'Copy the Client ID, Client Secret and the three endpoint addresses (Authorization URL, Token URL and User Info URL) from the provider.',
                 'Open 2FA and SSO from Admin Tools in the left menu.',
                 'Scroll to SSO / OAuth2 Configuration and tick Enable SSO.',
                 'Paste in the Client ID, Client Secret, Authorization URL, Token URL and User Info URL.',
                 'Under SSO Role Access, tick the roles allowed to sign in with SSO. A person whose roles are not ticked is told their role is not permitted.',
                 'Save changes, then click Purge caches in Admin Tools. A "Sign in with" button now appears on the login page.',
                 'Test in a private window with a real account. Make sure you can still sign in with your normal admin password in another window in case you need to change something.',
                 'Combine it with 2FA if you want both: SSO for convenience and an authenticator code for sensitive roles.',
                 'If someone cannot sign in, check their email matches the one in the identity provider and that their Moodle role is ticked under SSO Role Access.'],
                'Leave every SSO field blank if you only want 2FA. SSO is optional.',
                '/admin/settings.php?section=authsettingsimple2fa', 'sso single sign on oauth openid google microsoft okta login provider tutorial full'],
            ['tut_beacon', 'Full tutorials', 'admin', 'chart-pie',
                'Beacon reports: open, filter and export',
                'Use the curated report library to answer questions about progress, marking, attendance and funding.',
                ['Open Beacon Reports in the left menu under Reports. Admins and managers see site-wide data; a teacher inside a course sees only that course; learners see My reports.',
                 'Start on the dashboard: stat cards and gauges are precomputed, so they load instantly.',
                 'Pick a report from the library. Beacon includes 29 reports covering learner progress, completion, quiz performance, participation, attendance, grades and enrolment.',
                 'Use the filters to narrow the table. Active filters show as chips you can remove, and each filter shows counts.',
                 'Click any filterable value in a table, such as a course or a status, to filter by it straight away.',
                 'Watch the distribution bar above status columns: it shows the green, amber and red split of what you are looking at.',
                 'Try the Trainer marking queue to see submitted but unmarked assessments per trainer. Each row links to the learner, course, assignment and submission.',
                 'Try Funding participation evidence for durable proof of engagement for audits. It is built from completion, submission and attempt records, so it survives log clean-up.',
                 'Select rows to email the learners in bulk, or choose Export selected.',
                 'Export the whole report as CSV or PDF from the buttons above the table.',
                 'Click Save view to bookmark a filtered report so you can reopen it in one click.'],
                'Beacon is a curated report set, not a custom builder, so every number is grain-correct and ready to trust.',
                '/theme/lmslabs/beacon.php', 'beacon reports analytics progress completion marking queue funding audit export csv pdf filter dashboard tutorial full'],
            ['tut_beacon_schedule', 'Full tutorials', 'admin', 'mail',
                'Beacon reports: email a report on a schedule',
                'Send the right report to the right people every week without lifting a finger.',
                ['Open Beacon Reports and choose the report you want to send, for example completions.',
                 'Apply the filters you want people to see, such as one course category or one cohort.',
                 'Click Schedule email.',
                 'Give the schedule a name, for example "Weekly completions to the team".',
                 'Choose the format (CSV or PDF) and the frequency.',
                 'Add the recipients.',
                 'Save the schedule. Beacon sends it by your Moodle email settings, with no outside service.',
                 'Check Deliveries to see what was sent and when.',
                 'Open My reports to see your own saved views and schedules in one place.',
                 'Change or delete a schedule at any time from the same screen.'],
                'Make sure your Moodle outgoing email works first. Send a test from Site administration if unsure.',
                '/theme/lmslabs/beacon.php', 'beacon schedule email deliveries weekly report recipients csv pdf automatic'],
            ['tut_newsite', 'Full tutorials', 'admin', 'sparkles',
                'Set up a new site in about 15 minutes',
                'The fastest route from an empty Moodle to a branded site with people, courses and programs.',
                ['Open Get started from the Site group. It shows seven steps and ticks them as you finish.',
                 'Brand the site: add your logo, site name and accent colour in Appearance.',
                 'Add your first people with Add a new user, or Upload users to bring in many from a spreadsheet. Check the preview before saving.',
                 'Group them with Cohorts so you can enrol whole classes in one step.',
                 'Add your first course, or use Upload courses to create many at once.',
                 'Create a program so learners see their courses in order on My courses.',
                 'Turn on 2FA for admins in 2FA and SSO.',
                 'Run Speed Up Quick Check once so you know your server is healthy.',
                 'Learn the shortcuts: Ctrl K to jump anywhere, the colour dot to change the accent, and the Site administration icon for everything else.',
                 'When you need a deep technical setting, use Advanced settings. Use Back to Premium SaaS view to return in one click.'],
                'Hide the Get started card from the sidebar once all seven steps are green.',
                '/theme/lmslabs/getstarted.php', 'new site setup start first launch onboarding quick tutorial full'],
        ];
        $allpins = json_decode((string) @file_get_contents($CFG->dirroot . '/theme/lmslabs/pix/help/pins.json'), true) ?: [];
        $items = [];
        foreach ($rows as [$key, $topic, $aud, $icon, $title, $summary, $steps, $tip, $url, $keywords]) {
            $shot = '/theme/lmslabs/pix/help/' . $key . '.jpg';
            $pins = [];
            foreach (($allpins[$key] ?? []) as $p) {
                if ($p['n'] <= count($steps) && file_exists($CFG->dirroot . $shot)) {
                    $pins[] = $p + ['rx' => $p['r'][0], 'ry' => $p['r'][1], 'rw' => $p['r'][2], 'rh' => $p['r'][3],
                        'cx' => $p['c'][0], 'cy' => $p['c'][1], 'x1' => $p['l'][0], 'y1' => $p['l'][1],
                        'x2' => $p['l'][2], 'y2' => $p['l'][3]];
                }
            }
            $items[] = [
                'key' => $key,
                'pins' => $pins,
                'topic' => $topic,
                'audience' => $aud,
                'icon' => icons::svg($icon),
                'title' => $title,
                'summary' => $summary,
                'steps' => array_map(function ($text, $i) {
                    return ['n' => $i + 1, 'text' => $text];
                }, $steps, array_keys($steps)),
                'tip' => $tip,
                'url' => (new \moodle_url($url))->out(false),
                'shot' => file_exists($CFG->dirroot . $shot) ? (new \moodle_url($shot))->out(false) : '',
                'search' => \core_text::strtolower($title . ' ' . $summary . ' ' . implode(' ', $steps) . ' ' . $tip . ' ' . $keywords),
            ];
        }
        return $items;
    }
}
