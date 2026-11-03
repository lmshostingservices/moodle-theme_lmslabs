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
 * Question bank: banks, categories and questions in one screen, with search, preview and new question in a click.
 *
 * @package    theme_lmslabs
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use theme_lmslabs\local\icons;
use theme_lmslabs\local\qbank;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$ctx = context_course::instance($course->id);
$banks = qbank::banks($course);
if (!$banks) {
    // Nothing to show yet: the course has no question bank or the person may not see one.
    require_capability('moodle/question:viewall', $ctx);
}
$url = new moodle_url('/theme/lmslabs/questionbank.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('questionbank', 'question'));
$PAGE->set_heading(get_string('questionbank', 'question'));
$PAGE->requires->js(new moodle_url('/theme/lmslabs/javascript/questionbank.js'));

$pick = optional_param('bank', 0, PARAM_INT);
$current = $banks[0] ?? null;
foreach ($banks as $b) {
    if ($pick && $b['ctx']->id == $pick) {
        $current = $b;
    }
}
$data = [
    'banks' => [], 'hasbanks' => (bool)$banks, 'courseid' => $course->id,
    'classicurl' => \theme_lmslabs\local\router::classic('/question/edit.php', ['courseid' => $course->id]),
    'i' => ['search' => icons::svg('search'), 'plus' => icons::svg('plus'), 'eye' => icons::svg('eye'),
        'pencil' => icons::svg('pencil'), 'help' => icons::svg('file-question'), 'folder' => icons::svg('folder'),
        'upload' => icons::svg('upload'), 'download' => icons::svg('download'), 'x' => icons::svg('x')],
];
foreach ($banks as $b) {
    $data['banks'][] = ['name' => $b['name'], 'selected' => $current && $b['ctx']->id == $current['ctx']->id,
        'url' => (new moodle_url($url, ['bank' => $b['ctx']->id]))->out(false), 'kind' => $b['kind']];
}
if ($current) {
    $target = qbank::target($current, $course);
    $cats = qbank::categories($current['ctx']);
    $questions = qbank::questions($current['ctx']);
    $canedit = has_any_capability(['moodle/question:editall', 'moodle/question:editmine'], $current['ctx']);
    $back = $url->out_as_local_url(false);
    $types = [];
    foreach ($questions as &$q) {
        $types[$q['type']] = $q['typelabel'];
        $q['previewurl'] = (new moodle_url('/question/bank/previewquestion/preview.php', ['id' => $q['id']] + $target))->out(false);
        $q['editurl'] = $canedit ? (new moodle_url(
            '/question/bank/editquestion/question.php',
            ['id' => $q['id'], 'returnurl' => $back] + $target
        ))->out(false) : null;
    }
    unset($q);
    asort($types);
    $firstcat = $cats ? $cats[0]['id'] : 0;
    $newtypes = [];
    foreach (['multichoice' => 'qb_t_multichoice', 'truefalse' => 'qb_t_truefalse', 'shortanswer' => 'qb_t_shortanswer',
            'essay' => 'qb_t_essay', 'match' => 'qb_t_match'] as $t => $s) {
        if ($firstcat && $canedit && core_component::get_component_directory('qtype_' . $t) && question_bank::qtype_enabled($t)) {
            $newtypes[] = ['label' => get_string($s, 'theme_lmslabs'), 'type' => $t,
                'url' => (new moodle_url(
                    '/question/bank/editquestion/question.php',
                    ['qtype' => $t, 'category' => $firstcat, 'returnurl' => $back] + $target
                ))->out(false)];
        }
    }
    $data += [
        'bankname' => $current['name'],
        'categories' => $cats, 'hascategories' => (bool)$cats,
        'questions' => $questions, 'hasquestions' => (bool)$questions, 'qcount' => count($questions),
        'types' => array_map(function ($k, $v) {
            return ['type' => $k, 'label' => $v];
        }, array_keys($types), array_values($types)),
        'newtypes' => $newtypes, 'cannew' => (bool)$newtypes,
        'manageurl' => (new moodle_url('/question/bank/managecategories/category.php', $target))->out(false),
        'importurl' => has_capability('moodle/question:add', $current['ctx']) ?
            (new moodle_url('/question/bank/importquestions/import.php', $target))->out(false) : null,
        'exporturl' => (new moodle_url('/question/bank/exportquestions/export.php', $target))->out(false),
        'allcats' => get_string('qb_allcats', 'theme_lmslabs'),
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_lmslabs/questionbank', $data);
echo $OUTPUT->footer();
