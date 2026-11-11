# Changelog

All notable changes to the Premium SaaS theme (theme_lmslabs) are listed here.

## [v1.5.37] - 2026-11-11

### Fixed
- LMS Labs plugin tool pages (Cohort branding, Training matrix, Recertification and the rest) now give plain Moodle tables the full content width and the theme's soft card edge, so a table no longer stops part-way across the page or loses its bottom and right borders.
- Icon-only buttons (such as the back arrow on the cohort members screen, program move/edit buttons and people paging) no longer get a "?" tip icon squashed inside them; they show their tip on hover.

## [v1.5.36] - 2026-11-10

### Fixed
- Pages using the Premium SaaS layout no longer fail with "Class theme_lmslabs\\local\\setup not found". The Get started helper class was missing from the v1.5.35 package; it is restored as `classes/local/getstarted.php` (renamed so no theme file ends in setup.php), with the checklist steps, progress and the hide-card preference.
- The sidebar Get started card can no longer break a page: if its helper is unavailable or a check fails, the card is simply left out.
- The hide-card preference is now included in privacy exports.

## [v1.5.35] - 2026-11-09
- Include the Course Builder and conditional Course Report entries in the LMS Labs course navigation.

## [v1.5.34] - 2026-11-07
- Add the AI Course Builder entry and conditional Course Report link to course navigation.

## [v1.5.33] - 2026-11-05

### Added
- Report Studio now appears in the left menu under Reports › Beacon Reports, after Charts, and is highlighted while you're on it. It only shows when the installed Beacon includes Report Studio (Beacon 2.14 or later).
- The "Beacon Reports is not installed" page and the Beacon help guide now mention Report Studio.

## [v1.5.32] - 2026-11-04

### Fixed
- AI course format: when the course index is hidden, the banner and cards now fill the full width instead of leaving a blank column on the left. Reopening the index brings the column back.

## [v1.5.31] - 2026-11-04

### Fixed
- Pages no longer show the same heading twice. When a plugin repeats the page title just under the top bar, the repeat is hidden and the title with its (i) explanation stays.

## [v1.5.30] - 2026-11-04

### Changed
- When your site has a logo, it stands alone at the top of the menu with no site name beside it, sized by height so wide and square logos both fit. Without a logo, the initial and site name still show.

## [v1.5.29] - 2026-11-04

### Fixed
- Beacon Reports, Help centre, Members, My badges and Notifications now check the right permission at the top of the page, so the release checks no longer warn.

## [v1.5.28] - 2026-11-04

### Added
- Remedial learning (local_aiquizremedial) fits the theme: trainers get Remedial learning and learners get My revision in the Workplace training menu, its pages open full width with no course index, and every filter, button and page heading has a tip.
- A page heading with the course name for Remedial learning insights opened inside a course.

## [v1.5.27] - 2026-11-04

### Changed
- Help centre screenshots retaken to show the Workplace training menu, field tips and the full-width plugin pages. The help chapter is renamed Workplace training to match the video.

## [v1.5.26] - 2026-11-04

### Added
- Every page heading on the LMS Labs plugin pages and the main admin pages has a (i) that explains, in a sentence or two, what the page is for.
- Field and button tips for Email students, Group management, Course availability, Recertification, Auto-suspend, Course prerequisites, Training matrix, Training pathways, Evidence export, Media optimiser and Cohort branding.

### Changed
- Plugin pages opened for one course (Group management, Course availability, Recertification, Auto-suspend, Prerequisites) now use the full width, with no course index down the left.
- The Training menu group is now called Workplace training.

## [v1.5.25] - 2026-11-04

### Added
- The Help centre now follows the full video tutorial chapter by chapter: every screen, in the same order, with a 4K screenshot, numbered pins and the video timestamp for each step.
- Guides for Email students, Group management, Course availability, Recertification, Auto-suspend, Course prerequisites, Evidence export, Media optimiser and Cohort branding.

## [v1.5.24] - 2026-11-06

### Added
- Help centre guides for Training matrix and pathways, and for the LMS Labs add-ons in the left menu.

### Fixed
- Licence notices from plugins are shown once, in a clear lock card, instead of repeating on a blank page.
- Spacing under tables on plugin pages, and a cleaner list box where a plugin uses a multi-select.
- The Training matrix header is a quiet card in your accent colour.
- Developer notices about optional sources are hidden from learners on My training.
- The Purge all caches link no longer shows at the foot of every page (it stays in the menu).

## [v1.5.23] - 2026-11-06

### Fixed
- Group access banner from the Group management plugin was hidden behind the sidebar and top bar. It now shows under the top bar.
- Training matrix, staff cards and pathways blocks now use the theme accent, spacing and type, and stay compact on the dashboard.

## [v1.5.22] - 2026-11-06

### Added
- LMS Labs add-ons in the menu: Email students, Group management, Course availability, Recertification, Auto-suspend, Course prerequisites, Training matrix, Training pathways, Evidence export, Media optimiser and Cohort branding appear in the right menu section when installed, and open the plugin's own page.
- New Training menu section for the training matrix, pathways and evidence export. Learners see "My training" and "My pathways".
- Add-ons a site does not have yet show to site admins in one quiet, collapsed "LMS Labs add-ons" group with an Add-on tag, a tooltip and an info page. A theme setting hides it.
- Notifications page redesigned: grouped by day, unread dot, one-click mark as read, icons by type.

### Removed
- Tenants and branding. Per-cohort logos, colours and site names now come from the Cohort branding plugin. The old table and logos are removed on upgrade.

## [v1.5.21] - 2026-11-03
### Added
- Gradebook: choose how marks show (S / NYS, marks, percent, grade or points), filled green and magenta cells, a colour changer for each teacher, smart column widths, sticky learner column, a live Cohort row and stat chips. A "Needs marking" cell opens the marking screen (Fast Grade for assignments, the AI essay grader for quiz essays when installed).
- Course settings: an "AI course format" card so teachers can change the banner, arrows, course index and cards without leaving the page.
- My courses: new top section, kept compact: title row (Programs button beside it), the Continue card first and a short progress card beside it with a segmented status bar and a one-line legend. Numbers inside the progress rings are now centred.
- My courses and Today: the resume card shows a progress ring and the next activities as circles.
### Changed
- Release checks: request values are read through Moodle functions instead of PHP superglobals, free-text fields no longer use raw parameter types, site name and email-host settings are listed under this theme, and code style fixes (function spacing, class braces, multi-line calls) across all PHP files.
- Help centre: every numbered circle now joins a thin line to an accent-coloured outline box around the exact part of the screen. All guide screenshots were retaken in 4K on Moodle 5.3 and each box is measured from the live page, so it always sits on the right control. Hovering a step lights its box.
- Help centre: fixed a database error that stopped the Help centre opening for teachers who are not allowed to create courses site-wide.
- Back to Premium SaaS view from the Moodle site administration pages now opens the Dashboard instead of an unstyled copy of the admin tree.
- Help centre: screenshots now show at full resolution in a wider column, with numbered markers on the screenshot that match the steps (hover a step to light its marker). Longer tutorials use tall screenshots that scroll.
- Speed: the Dashboard Today panel (progress, marking counts, calendar) is cached for a minute per person, so it no longer runs those queries on every load.
- Speed: every link click now lights the menu item and starts a progress bar immediately; pages cross-fade while the sidebar stays put (browser view transitions).
- Help centre: all screenshots recaptured sharper and larger; popup now shows text on the left and the screenshot on the right, and opens above the whole page; card images load as you scroll.
- Help centre: guides open in a large centred popup (big screenshot, steps beside it, close button) instead of the narrow side panel; the page and cards use the full width.
- Help centre: new Full tutorials topic with 10 long guides (Programs, Speed Up, 2FA, SSO, Beacon reports, new site setup).
- The top-bar admin menu now has a Help centre link.
- Dashboard greeting box: removed the coloured gradient shading; it is now plain white.
- Faster view switching: "Advanced settings" and "Back to Premium SaaS view" now go straight to the page in one step (no separate switch request), with an instant progress bar.
- Double headings: a heading in the page body that repeats the page title (for example Purge caches) is now hidden.
- Dashboard and My courses: the Continue card and its neighbour are now both soft grey, with an accent edge and a small lift on hover.
- AI course format: the player-style course index (course title, total time, time on each activity) and the format's own banner arrows and tutor now load inside Premium SaaS course pages.
- AI course format pages show the format exactly as designed, with the site's accent colour: 51px section rows, edge to edge, level with the banner.
- AI course format banner arrow appears when you hover the banner (always visible on touch screens and when the banner is closed).
- Site administration menu icon is now always in the top bar for site managers, in the same position on every page.
- Footer: "Data retention summary" and "Get the mobile app" links removed.
- Admin Tools menu: Simple 2FA and SSO (auth_simple2fa) appears automatically for site admins when installed.
- Help centre: a searchable, filterable guide library with screenshots, in a new Help group in the left menu. Guides open in a side panel; search highlights matches, and it opens on the guides for your role.
- Get started page moved to getstarted.php: some web servers block any address ending in setup.php, which made the page fail to load.
- Empty "all clear" messages (dashboard, calendar, reports) are a quiet grey line with a small green tick instead of a solid green box.
- "Back to Premium SaaS view" button moved to the bottom left so it never covers Continue and other buttons.
- Dashboard and My courses: Continue and its neighbour card now match: same height, same flat edge, no raised glow, and an empty week is one calm centred line.
- Field tips use a modern "?" icon.
- Greens across the theme use #24c45f; the pale green and yellow tints are gone.

## [v1.4.0] - 2026-11-02
### Added
- Gradebook page: every learner and every mark in one grid. Type a mark and press Enter to save; the course total and averages update at once. Filters for work waiting to be marked and for gaps, learner search, CSV download.
- Overrides page for assignments and quizzes: one screen per activity, quick buttons for extra time, a new due date or extra attempts, for people or groups.
- Course Badges page: create, switch on, award and review badges in one place.
- Question bank page with banks, categories, preview and edit links.
- Tenants and branding: tenants with their own cohort, accent colour, logo and site name, picked from a sign-in link.
- Search is aware of where you are: search this course or the whole site, Tab switches between them.
- Hosting provider page rebuilt in the Premium SaaS style.
- Tooltips on every control of Programs, People, Courses and categories, Groups, Members and Get started.
### Changed
- The LMS Labs bar no longer shows on the sign-in page, and learners do not see it inside a course.
- Theme pages keep the Premium SaaS look when an administrator is in Boost view.
### Fixed
- Message drawer script error on Moodle 5.0 to 5.2 (its markup now loads before its script).
- Course index script error on theme pages inside a course.

## [v1.3.0] - 2026-11-02
### Added
- People page replaces Moodle's browse list of users: quick-filter cards, one row of filters (cohort, category, course, group, role, status, profile field), removable chips, saved views with a default, sort, and a bulk bar (suspend, reactivate, add to cohort, Moodle bulk actions).
- Courses and categories on one page: category sections with course cards, Visible/Hidden badges, one-click visibility switch, drag to reorder, filters, search and a Make a program button per category.
- Program badges on course cards (step number and unlock rule).
- Grading page in every course: assignments and essay quizzes with counts waiting, one click into Fast Grade or AI Essay Grader when installed.
- Hosting provider settings page (site administrators only) with a switch to hide the LMS Labs bar.
- LMS Labs AI credits promo button (10% more credits) in the LMS Labs bar.
- One-click file uploads on theme settings.
- LMS Labs flask browser tab icon.
### Changed
- Theme renamed to Premium SaaS (component stays theme_lmslabs).
- AI Course Format courses use Boost's own course index drawer so the format's player and index work as designed.
- Fast Grade's floating button sits inside the frame and above sticky Save bars.
- Speed Up shows only to people who can open it; Site Owners get read-only access when it is installed.
### Fixed
- Pages frozen (no scrolling) on AI Course Format courses.
- Trainers in Student view could be locked out of a program course with no way back.
- A "completed" unlock rule on a course without completion tracking could never be met; it now falls back to "everything submitted".
- Program lock check could fail if the completion library was not loaded.
- The bulk edit footer left an 8 px sliver visible.

## [v1.2.0] - 2026-11-01
### Added
- Programs by audience (cohort, category, course, group, standard and custom profile fields, any/all).
- Redesigned groups, group members and cohort members pages.
- Beacon Reports sub-menu and Admin Tools section (Speed Up, Purge caches).
- Get started checklist for Site Owners.
### Fixed
- Logo URL handling on sites with protocol-relative file URLs.

## [v1.1.0] - 2026-11-01
### Added
- My courses as Programs with progress rings, headings, ordering and unlock rules.
- Immersive course view for learners.
- Premium login page; site, brand, typography, contact and SMTP settings.
- Site Owner role.

## [v1.0.0] - 2026-11-01
### Added
- First release: left sidebar app shell on Boost, one-click accent colour, one-click student view, command search (Ctrl K), Boost view for site administrators, LMS Labs footer bar.
