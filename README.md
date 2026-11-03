# Premium SaaS theme for Moodle (by LMS Labs)

A clean, fast admin-panel theme for Moodle 4.5 to 5.3, built on Boost. From [LMS Labs](https://lms-labs.com).

## What it does

- **Left sidebar navigation** with only the daily work: users, cohorts, courses, Beacon Reports, site settings.
- **Ctrl K / ⌘K search** to jump to any page, search courses, or (site admins) search every setting.
- **Get started checklist** for Site Owners: logo and colour, site details, support contact, outgoing email, people, first course, first program. Steps tick themselves off; a small sidebar card shows progress until it's done or hidden.
- **One-click site colour** from the dot in the top bar. Applies to everyone instantly, no cache purge.
- **One-click Student view** inside a course, and one click back.
- **Programs**: create as many as you need and target each by cohort, course category, course, course group name, or any standard or custom profile field (any/all). Learners see every program they belong to.
- **My courses as a program**: progress rings and bars, your own headings (Group A, Group B…), drag-to-order, and unlock rules per course (days/hours after enrolment, previous course's assessments submitted, or previous course complete). Rules are enforced for learners; teachers and Site Owners can always open every course.
- **Immersive course view for learners**: no sidebar, just a way back to My courses.
- **Site Owner role** (created on install): runs the site day to day without site administrator access. Gets copies of the notices Moodle sends to site admins.
- **Boost view** for site admins (the hosting provider): every technical setting, remembered across logins, with a floating button back.
- **Redesigned groups and cohort screens**: groups as cards with member avatars and stats; add/remove members for groups and cohorts in a searchable two-panel manager. Moodle's classic screens stay one click away.
- **Makeover of core admin pages**: participants, enrolment methods, user selectors, forms, course manager, tables.
- **One simple settings page**: site name and description, logo, login image, colour, font (Geist built in, or any Google Font), text size, course format, contact details, and outgoing SMTP.
- Full width everywhere, mobile friendly, light theme.

## New in 1.3.0
- **People** replaces Moodle's browse list of users: quick-filter cards, one row of filters (cohort, category, course, group, role, status, profile field), removable chips, saved views with a default, and a bulk bar (suspend, reactivate, add to cohort, Moodle bulk actions).
- **Courses and categories** on one page: category sections with course cards, Visible/Hidden badges, a one-click visibility switch, drag to reorder, filters, and a **Make a program** button per category.
- **Grading** page in every course: assignments and essay quizzes with counts waiting, one click into Fast Grade or AI Essay Grader when installed.
- **AI Course Format sync**: courses in that format get Boost's own course index drawer, so the format's player and index work exactly as designed.
- **Hosting provider** page (site administrators only): switch off the LMS Labs bar at a client's request.
- Browser tab icon is the LMS Labs flask (a site favicon under Appearance > Logos still takes priority).
- Programs: a "completed" unlock rule on a course without completion tracking now falls back to "everything submitted"; trainers in Student view are never locked out.
- Site Owners can open Speed Up (read-only) when it is installed.

## Install

1. Unzip into `theme/lmslabs` (Moodle 5.1+: `public/theme/lmslabs`).
2. Visit Site administration > Notifications to install.
3. Choose **LMS Labs** under Appearance > Themes.
4. Assign **Site Owner** to your client under Site administration > Users > Permissions > Assign system roles.

## Companion plugins

- **Beacon Reports** (`local_beacon`) powers Reports › Beacon Reports: Overview, Stat cards, KPI gauges, Reports, Charts. Without it, those links explain how to get it.
- **Admin Tools** lists installed LMS Labs tools (Speed Up: `tool_speedup`) plus Purge caches. Add more in `classes/local/nav.php` (`ADMIN_TOOLS`).

## Licence

GPL v3 or later. Geist font: SIL OFL 1.1. Lucide icons: ISC.

Moodle™ is a registered trademark of Moodle Pty Ltd. LMS Labs is not affiliated with or endorsed by Moodle Pty Ltd.
