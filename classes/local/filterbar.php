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
 * The filter bar shared by list pages: a search box, one-click quick cards, filter pills,
 * removable chips and saved views. Plain GET form, so every filtered list has a shareable URL.
 *
 * Pill definitions: key => [
 *     'label' => string, 'icon' => lucide name, 'multi' => bool,
 *     'options' => [['value' => string|int, 'label' => string, 'group' => ?string]],
 * ]
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterbar {
    /** @var string page key for saved views */
    protected $page;
    /** @var moodle_url */
    protected $base;
    /** @var array pill definitions */
    protected $defs;
    /** @var array quick card definitions: key => [label, icon, count] */
    protected $quick;
    /** @var array current values: key => array of strings */
    protected $values = [];
    /** @var string */
    protected $q = '';
    /** @var string */
    protected $quickvalue = '';
    /** @var string profile field filter: field key */
    protected $pffield = '';
    /** @var string profile field filter: value */
    protected $pfvalue = '';
    /** @var bool offer the profile field filter */
    protected $profilefilter;
    /** @var string current sort */
    protected $sort;
    /** @var array sort options value => label */
    protected $sorts;

    /** @var array|null Values to use instead of the request (for "select all matching" on the bulk page). */
    public static $inject = null;

    /**
     * One text value from the request, or from the injected values.
     *
     * @param string $name
     * @param string $type PARAM_* type.
     * @return string
     */
    private function opt(string $name, string $type): string {
        if (self::$inject !== null) {
            return isset(self::$inject[$name]) && is_string(self::$inject[$name]) ?
                (string)clean_param(self::$inject[$name], $type) : '';
        }
        return (string)optional_param($name, '', $type);
    }

    /**
     * A list of text values from the request, or from the injected values.
     *
     * @param string $name
     * @return array
     */
    private function arr(string $name): array {
        if (self::$inject !== null) {
            $raw = self::$inject[$name] ?? [];
            return is_array($raw) ? array_values(clean_param_array($raw, PARAM_TEXT)) : [];
        }
        return array_values(optional_param_array($name, [], PARAM_TEXT));
    }

    /**
     * Read the request.
     *
     * @param string $page
     * @param moodle_url $base
     * @param array $defs
     * @param array $quick
     * @param array $sorts
     * @param bool $profilefilter
     */
    public function __construct(string $page, moodle_url $base, array $defs, array $quick = [], array $sorts = [],
            bool $profilefilter = false) {
        $this->page = $page;
        $this->base = $base;
        $this->defs = $defs;
        $this->quick = $quick;
        $this->sorts = $sorts;
        $this->profilefilter = $profilefilter;
        $this->q = trim($this->opt('q', PARAM_TEXT));
        $this->quickvalue = $this->opt('quick', PARAM_ALPHANUMEXT);
        if ($this->quickvalue !== '' && !isset($quick[$this->quickvalue])) {
            $this->quickvalue = '';
        }
        foreach ($defs as $key => $def) {
            $raw = $this->arr('f_' . $key);
            $valid = array_map('strval', array_column($def['options'], 'value'));
            $this->values[$key] = array_values(array_intersect(array_map('strval', $raw), $valid));
        }
        if ($profilefilter) {
            $this->pffield = $this->opt('pf', PARAM_ALPHANUMEXT);
            $this->pfvalue = trim($this->opt('pv', PARAM_TEXT));
            if ($this->pffield === '' || $this->pfvalue === '') {
                $this->pffield = $this->pfvalue = '';
            }
        }
        $this->sort = $this->opt('sort', PARAM_ALPHANUMEXT);
        if (!isset($sorts[$this->sort])) {
            $this->sort = $sorts ? array_key_first($sorts) : '';
        }
    }

    /**
     * Current values for a pill.
     *
     * @param string $key
     * @return array
     */
    public function get(string $key): array {
        return $this->values[$key] ?? [];
    }

    /**
     * Search text.
     *
     * @return string
     */
    public function q(): string {
        return $this->q;
    }

    /**
     * Active quick card key, or ''.
     *
     * @return string
     */
    public function quick(): string {
        return $this->quickvalue;
    }

    /**
     * Profile field filter, or null.
     *
     * @return array|null [field, value]
     */
    public function profile(): ?array {
        return $this->pffield !== '' ? [$this->pffield, $this->pfvalue] : null;
    }

    /**
     * Current sort key.
     *
     * @return string
     */
    public function sort(): string {
        return $this->sort;
    }

    /**
     * Is anything filtered?
     *
     * @return bool
     */
    public function is_filtered(): bool {
        return $this->q !== '' || $this->quickvalue !== '' || $this->pffield !== '' ||
            (bool)array_filter($this->values);
    }

    /**
     * URL parameters for the current state, with optional overrides.
     *
     * @param array $override
     * @return array
     */
    public function params(array $override = []): array {
        $p = [];
        if ($this->q !== '') {
            $p['q'] = $this->q;
        }
        if ($this->quickvalue !== '') {
            $p['quick'] = $this->quickvalue;
        }
        foreach ($this->values as $key => $vals) {
            foreach (array_values($vals) as $i => $v) {
                $p['f_' . $key . '[' . $i . ']'] = $v;
            }
        }
        if ($this->pffield !== '') {
            $p['pf'] = $this->pffield;
            $p['pv'] = $this->pfvalue;
        }
        if ($this->sort !== '' && $this->sorts && $this->sort !== array_key_first($this->sorts)) {
            $p['sort'] = $this->sort;
        }
        foreach ($override as $k => $v) {
            if ($v === null) {
                unset($p[$k]);
            } else {
                $p[$k] = $v;
            }
        }
        return $p;
    }

    /**
     * URL with the current filters (and overrides).
     *
     * @param array $override
     * @return moodle_url
     */
    public function url(array $override = []): moodle_url {
        return new moodle_url($this->base, $this->params($override));
    }

    /**
     * URL without one value of one pill.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    protected function without(string $key, string $value): string {
        $saved = $this->values[$key];
        $this->values[$key] = array_values(array_diff($saved, [$value]));
        $url = $this->url()->out(false);
        $this->values[$key] = $saved;
        return $url;
    }

    /**
     * Template data.
     *
     * @param array $profileoptions [['value','label']] for the profile field filter
     * @return array
     */
    public function export(array $profileoptions = []): array {
        global $USER;
        $pills = [];
        $chips = [];
        foreach ($this->defs as $key => $def) {
            $selected = $this->values[$key];
            $options = [];
            $lastgroup = null;
            foreach ($def['options'] as $o) {
                $group = $o['group'] ?? null;
                $options[] = [
                    'value' => $o['value'],
                    'label' => $o['label'],
                    'checked' => in_array((string)$o['value'], $selected, true),
                    'groupstart' => $group !== null && $group !== $lastgroup ? $group : null,
                    'hint' => $o['hint'] ?? null,
                ];
                $lastgroup = $group;
                if (in_array((string)$o['value'], $selected, true)) {
                    $chips[] = ['label' => $def['label'] . ': ' . $o['label'], 'url' => $this->without($key, (string)$o['value'])];
                }
            }
            $pills[] = [
                'key' => $key,
                'name' => 'f_' . $key . '[]',
                'label' => $def['label'],
                'icon' => icons::svg($def['icon']),
                'multi' => $def['multi'] ?? true,
                'options' => $options,
                'hasoptions' => (bool)$options,
                'searchable' => count($options) > 8,
                'count' => count($selected),
                'active' => (bool)$selected,
                'summary' => count($selected) === 1 ? self::label_for($def['options'], $selected[0]) : null,
            ];
        }
        if ($this->q !== '') {
            $chips[] = ['label' => get_string('fb_search', 'theme_lmslabs') . ': “' . s($this->q) . '”',
                'url' => $this->url(['q' => null])->out(false)];
        }
        $profile = null;
        if ($this->profilefilter && $profileoptions) {
            $profile = [
                'icon' => icons::svg('contact-round'),
                'active' => $this->pffield !== '',
                'value' => $this->pfvalue,
                'options' => array_map(function ($o) {
                    return $o + ['selected' => $o['value'] === $this->pffield];
                }, $profileoptions),
            ];
            if ($this->pffield !== '') {
                $label = self::label_for($profileoptions, $this->pffield) ?? $this->pffield;
                $chips[] = ['label' => $label . ': “' . s($this->pfvalue) . '”',
                    'url' => $this->url(['pf' => null, 'pv' => null])->out(false)];
            }
        }

        $quick = [];
        foreach ($this->quick as $key => [$label, $icon, $count]) {
            $active = $this->quickvalue === $key || ($key === 'all' && $this->quickvalue === '');
            $quick[] = [
                'key' => $key,
                'label' => $label,
                'icon' => icons::svg($icon),
                'count' => $count,
                'active' => $active,
                'url' => $this->url(['quick' => $key === 'all' ? null : $key, 'page' => null])->out(false),
            ];
        }

        $sorts = [];
        foreach ($this->sorts as $value => $label) {
            $sorts[] = ['value' => $value, 'label' => $label, 'selected' => $value === $this->sort];
        }

        // Hidden inputs that carry state the form itself doesn't show.
        $hidden = [];
        if ($this->quickvalue !== '') {
            $hidden[] = ['name' => 'quick', 'value' => $this->quickvalue];
        }

        $views = views::for_user($USER->id, $this->page, $this->base, $this->url()->get_query_string(false));
        return [
            'action' => $this->base->out_omit_querystring(),
            'baseparams' => array_map(function ($k, $v) {
                return ['name' => $k, 'value' => $v];
            }, array_keys($this->base->params()), $this->base->params()),
            'q' => $this->q,
            'pills' => $pills,
            'profile' => $profile,
            'quick' => $quick,
            'hasquick' => (bool)$quick,
            'chips' => $chips,
            'haschips' => (bool)$chips,
            'filtered' => $this->is_filtered(),
            'clearurl' => (new moodle_url($this->base, ['all' => 1]))->out(false),
            'sorts' => $sorts,
            'hassorts' => count($sorts) > 1,
            'hidden' => $hidden,
            'views' => $views['views'],
            'hasviews' => (bool)$views['views'],
            'viewcount' => count($views['views']),
            'viewsurl' => (new moodle_url('/theme/lmslabs/views.php'))->out(false),
            'page' => $this->page,
            'current' => $this->url()->out_as_local_url(false),
            'sesskey' => sesskey(),
            'i' => [
                'search' => icons::svg('search'), 'chevron' => icons::svg('chevron-down', 'll-ico ll-fb-caret'),
                'x' => icons::svg('x'), 'bookmark' => icons::svg('bookmark'), 'star' => icons::svg('star'),
                'sort' => icons::svg('arrow-up-down'), 'filter' => icons::svg('sliders-horizontal'),
            ],
        ];
    }

    /**
     * Label for a value in an options list.
     *
     * @param array $options
     * @param string $value
     * @return string|null
     */
    protected static function label_for(array $options, string $value): ?string {
        foreach ($options as $o) {
            if ((string)$o['value'] === $value) {
                return $o['label'];
            }
        }
        return null;
    }
}
