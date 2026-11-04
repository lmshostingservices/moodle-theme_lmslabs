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

/**
 * Theme functions.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Default accent: premium SaaS blue. */
define('THEME_LMSLABS_DEFAULT_ACCENT', '#2563eb');

/**
 * Preset accent swatches offered in the one-click colour picker.
 *
 * @return array hex => label
 */
function theme_lmslabs_accent_presets(): array {
    return [
        '#2563eb' => 'Blue',
        '#4f46e5' => 'Indigo',
        '#7c3aed' => 'Violet',
        '#c026d3' => 'Fuchsia',
        '#e11d48' => 'Rose',
        '#ea580c' => 'Orange',
        '#ca8a04' => 'Gold',
        '#16a34a' => 'Green',
        '#0d9488' => 'Teal',
        '#0284c7' => 'Sky',
        '#475569' => 'Slate',
        '#111827' => 'Ink',
    ];
}

/**
 * Normalise a colour to #rrggbb or return null.
 *
 * @param string|null $value
 * @return string|null
 */
function theme_lmslabs_clean_hex(?string $value): ?string {
    $value = strtolower(trim((string)$value));
    if (preg_match('/^#?([0-9a-f]{3})$/', $value, $m)) {
        $value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
    }
    if (preg_match('/^#?([0-9a-f]{6})$/', $value, $m)) {
        return '#' . $m[1];
    }
    return null;
}

/**
 * Font choices: bundled Geist plus popular Google Fonts.
 *
 * @return array value => label
 */
function theme_lmslabs_font_choices(): array {
    $google = ['Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Nunito', 'Nunito Sans', 'Raleway',
        'Work Sans', 'DM Sans', 'Manrope', 'Plus Jakarta Sans', 'Outfit', 'Figtree', 'Onest', 'Urbanist', 'Sora',
        'Lexend', 'Rubik', 'Mulish', 'Karla', 'Source Sans 3', 'IBM Plex Sans', 'Noto Sans', 'PT Sans', 'Ubuntu',
        'Fira Sans', 'Barlow', 'Hind', 'Heebo', 'Quicksand', 'Cabin', 'Josefin Sans', 'Libre Franklin', 'Archivo',
        'Public Sans', 'Red Hat Display', 'Space Grotesk', 'Albert Sans', 'Instrument Sans', 'Be Vietnam Pro',
        'Atkinson Hyperlegible', 'Merriweather', 'Lora', 'Playfair Display', 'Source Serif 4', 'Noto Serif',
        'Libre Baskerville', 'Crimson Pro', 'EB Garamond'];
    $choices = ['' => get_string('font_geist', 'theme_lmslabs'), 'system' => get_string('font_system', 'theme_lmslabs')];
    foreach ($google as $name) {
        $choices[$name] = $name;
    }
    return $choices;
}

/**
 * The font in use: [css family stack, google fonts stylesheet url or null].
 *
 * @return array
 */
function theme_lmslabs_font(): array {
    $fallback = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    $custom = trim((string)get_config('theme_lmslabs', 'fontcustom'));
    $name = $custom !== '' ? $custom : (string)get_config('theme_lmslabs', 'font');
    $name = preg_replace('/[^A-Za-z0-9 ]/', '', $name);
    if ($name === '') {
        return ['"Geist", ' . $fallback, null];
    }
    if ($name === 'system') {
        return [$fallback, null];
    }
    $url = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $name) . ':wght@400;500;600;700&display=swap';
    return ['"' . $name . '", ' . $fallback, $url];
}

/**
 * Base text size in pixels.
 *
 * @return int
 */
function theme_lmslabs_font_size(): int {
    $size = (int)get_config('theme_lmslabs', 'fontsize');
    return ($size >= 13 && $size <= 19) ? $size : 15;
}

/**
 * The one course format offered in this theme: the setting, else an installed AI format, else Topics.
 *
 * @return string
 */
function theme_lmslabs_course_format(): string {
    $formats = core_component::get_plugin_list('format');
    $chosen = (string)get_config('theme_lmslabs', 'courseformat');
    if ($chosen !== '' && isset($formats[$chosen])) {
        return $chosen;
    }
    foreach (array_keys($formats) as $name) {
        if (strpos($name, 'ai') !== false) {
            return $name;
        }
    }
    return 'topics';
}

/**
 * The current site accent colour.
 *
 * @return string
 */
function theme_lmslabs_accent(): string {
    return theme_lmslabs_clean_hex(get_config('theme_lmslabs', 'brandcolor')) ?? THEME_LMSLABS_DEFAULT_ACCENT;
}

/**
 * Inline CSS that sets the accent variables. Lives in the page head so a colour change
 * applies instantly without a theme cache purge.
 *
 * @param string|null $hex
 * @return string
 */
function theme_lmslabs_accent_css(?string $hex = null): string {
    $hex = $hex ?? theme_lmslabs_accent();
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    [$family] = theme_lmslabs_font();
    $size = theme_lmslabs_font_size();
    return ':root{--ll-accent:' . $hex . ';--ll-accent-rgb:' . "$r,$g,$b" . ';--ll-font:' . $family .
        ';--ll-size:' . $size . 'px;}';
}

/**
 * Boost's layouts for the running version, with Boost's drawers layout swapped for ours.
 *
 * @return array [layouts, activityheaderconfig]
 */
function theme_lmslabs_boost_layouts(): array {
    $file = core_component::get_plugin_directory('theme', 'boost') . '/config.php';
    $boost = (function () use ($file) {
        global $CFG;
        $THEME = new stdClass();
        require($file);
        return $THEME;
    })();
    $layouts = $boost->layouts;
    foreach ($layouts as $name => $layout) {
        if (($layout['file'] ?? '') === 'drawers.php') {
            $layouts[$name]['file'] = 'saas.php';
        }
    }
    $layouts['login']['file'] = 'login.php';
    return [$layouts, $boost->activityheaderconfig ?? []];
}

/**
 * Main SCSS: Boost's default preset plus this theme.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_lmslabs_get_main_scss_content($theme): string {
    $boostdir = core_component::get_plugin_directory('theme', 'boost');
    $scss = file_get_contents($boostdir . '/scss/preset/default.scss');
    $scss .= "\n" . file_get_contents(__DIR__ . '/scss/lmslabs.scss');
    return $scss;
}

/**
 * Variables injected before Bootstrap compiles.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_lmslabs_get_pre_scss($theme): string {
    $accent = theme_lmslabs_accent();
    $pre = file_get_contents(__DIR__ . '/scss/pre.scss');
    return '$primary: ' . $accent . ";\n" . $pre;
}

/**
 * Extra SCSS added after everything else.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_lmslabs_get_extra_scss($theme): string {
    return (string)get_config('theme_lmslabs', 'customscss');
}

/**
 * Is the major Moodle version at least the given branch (e.g. 500)?
 *
 * @param int $branch
 * @return bool
 */
function theme_lmslabs_branch_at_least(int $branch): bool {
    global $CFG;
    return (int)$CFG->branch >= $branch;
}

/**
 * Brand logo URL: the theme's own upload, then Moodle's compact logo, then Moodle's logo.
 *
 * @param int $size
 * @return string|null
 */
function theme_lmslabs_logo_url(int $size = 80): ?string {
    global $OUTPUT, $PAGE;
    $theme = theme_config::load('lmslabs');
    if (!empty($theme->settings->logo)) {
        $url = theme_lmslabs_absolute_url($theme->setting_file_url('logo', 'logo'));
        if ($url) {
            return $url;
        }
    }
    // Ask for a wide box so a landscape logo keeps its shape (it is shown by height).
    $url = $OUTPUT->get_compact_logo_url($size * 4, $size) ?: $OUTPUT->get_logo_url($size * 4, $size);
    return $url ? $url->out(false) : null;
}

/**
 * Contact details for the help card, or null when none are set.
 *
 * @return array|null
 */
function theme_lmslabs_contact(): ?array {
    $email = trim((string)get_config('theme_lmslabs', 'contactemail'));
    $phone = trim((string)get_config('theme_lmslabs', 'contactphone'));
    if ($email === '' && $phone === '') {
        return null;
    }
    return [
        'email' => $email ?: null,
        'phone' => $phone ?: null,
        'phonelink' => preg_replace('/[^0-9+]/', '', $phone),
    ];
}

/**
 * Serve the uploaded logo.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_lmslabs_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'loginimage')) {
        $theme = theme_config::load('lmslabs');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * Login page image URL, if one was uploaded.
 *
 * @return string|null
 */
function theme_lmslabs_login_image_url(): ?string {
    $theme = theme_config::load('lmslabs');
    if (empty($theme->settings->loginimage)) {
        return null;
    }
    return theme_lmslabs_absolute_url($theme->setting_file_url('loginimage', 'loginimage'));
}

/**
 * theme_config::setting_file_url() returns a protocol-relative URL ("//host/..."); make it absolute.
 *
 * @param string|null $url
 * @return string|null
 */
function theme_lmslabs_absolute_url(?string $url): ?string {
    global $CFG;
    if (empty($url)) {
        return null;
    }
    if (strpos($url, '//') === 0) {
        return parse_url($CFG->wwwroot, PHP_URL_SCHEME) . ':' . $url;
    }
    return $url;
}

/**
 * Course formats that rely on Boost's course index drawer and get it unchanged.
 *
 * @return string[]
 */
function theme_lmslabs_drawer_formats(): array {
    return ['aicourse'];
}
