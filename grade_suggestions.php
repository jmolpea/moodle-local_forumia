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
 * Teacher review of AI grade evaluations for a forum.
 *
 * Accessible via the forum's Settings navigation to users with mod/forum:grade.
 * In suggest mode nothing the AI proposes reaches the forum grades or the
 * gradebook until a teacher accepts it here. In automatic mode this page shows
 * the evaluations that could not be applied on their own.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_forumia\grade_suggestions;

require_once(__DIR__ . '/../../config.php');

$cmid   = required_param('cmid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

$cm     = get_coursemodule_from_id('forum', $cmid, 0, false, MUST_EXIST);
$forum  = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/forum:grade', $context);

$pageurl = new moodle_url('/local/forumia/grade_suggestions.php', ['cmid' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('suggestions_title', 'local_forumia'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

if ($action === 'acceptall') {
    // Confirmation page first: accepting everything replaces existing grades.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('suggestions_acceptall_confirm', 'local_forumia'),
        new moodle_url($pageurl, ['action' => 'acceptallconfirmed', 'sesskey' => sesskey()]),
        $pageurl
    );
    echo $OUTPUT->footer();
    die();
} else if ($action === 'acceptallconfirmed') {
    require_sesskey();
    [$applied, $failed] = grade_suggestions::accept_all((int) $forum->id, $USER);
    $a = (object) ['applied' => $applied, 'failed' => $failed];
    $type = $failed ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
    redirect($pageurl, get_string('suggestions_acceptedall', 'local_forumia', $a), null, $type);
} else if ($action !== '' && $id > 0) {
    require_sesskey();
    // The evaluation must belong to this forum: the capability check above is
    // scoped to this forum's context only.
    $DB->get_record(grade_suggestions::TABLE, ['id' => $id, 'forumid' => $forum->id], 'id', MUST_EXIST);

    if ($action === 'accept') {
        try {
            grade_suggestions::accept($id, $USER);
            $message = get_string('suggestions_accepted', 'local_forumia');
            $type    = \core\output\notification::NOTIFY_SUCCESS;
        } catch (moodle_exception $e) {
            $message = $e->getMessage();
            $type    = \core\output\notification::NOTIFY_ERROR;
        }
        redirect($pageurl, $message, null, $type);
    } else if ($action === 'discard') {
        grade_suggestions::discard($id);
        redirect($pageurl, get_string('suggestions_discarded', 'local_forumia'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('suggestions_title', 'local_forumia') . ': ' . format_string($forum->name));
echo html_writer::tag('p', get_string('suggestions_intro', 'local_forumia'));

$evaluations = grade_suggestions::get_for_review((int) $forum->id);
if (empty($evaluations)) {
    echo $OUTPUT->notification(get_string('suggestions_none', 'local_forumia'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    die();
}

if (!empty(grade_suggestions::get_pending((int) $forum->id))) {
    $acceptall = new single_button(
        new moodle_url($pageurl, ['action' => 'acceptall']),
        get_string('suggestions_acceptall', 'local_forumia'),
        'get',
        single_button::BUTTON_PRIMARY
    );
    echo html_writer::div($OUTPUT->render($acceptall), 'mb-3');
}

$table = new html_table();
$table->head = [
    get_string('suggestions_student', 'local_forumia'),
    get_string('suggestions_posts', 'local_forumia'),
    get_string('suggestions_suggested', 'local_forumia'),
    get_string('suggestions_rationale', 'local_forumia'),
    get_string('suggestions_current', 'local_forumia'),
    get_string('suggestions_actions', 'local_forumia'),
];
$table->attributes['class'] = 'generaltable local-forumia-suggestions';

foreach ($evaluations as $evaluation) {
    $student = core_user::get_user($evaluation->userid);

    // Read the stored grade directly: the grade item's get_grade_for_user()
    // would create an empty grade row as a side effect.
    $current = $DB->get_field('forum_grades', 'grade', [
        'forum'      => $forum->id,
        'itemnumber' => 1,
        'userid'     => $evaluation->userid,
    ]);
    $currentcell = ($current === false || $current === null)
        ? get_string('suggestions_nograde', 'local_forumia')
        : format_float($current, 2, true, true) . ' / ' . (int) $forum->grade_forum;

    $actionurl = new moodle_url($pageurl, ['id' => $evaluation->id]);
    $discard   = $OUTPUT->single_button(
        new moodle_url($actionurl, ['action' => 'discard']),
        get_string('suggestions_discard', 'local_forumia')
    );
    if ((int) $evaluation->status === grade_suggestions::STATUS_FAILED) {
        $suggestedcell = get_string('suggestions_failed', 'local_forumia');
        $actions       = $discard;
    } else {
        $suggestedcell = (int) $evaluation->grade . ' / ' . (int) $evaluation->grademax;
        $actions       = $OUTPUT->single_button(
            new moodle_url($actionurl, ['action' => 'accept']),
            get_string('suggestions_accept', 'local_forumia'),
            'post',
            ['type' => single_button::BUTTON_PRIMARY]
        ) . ' ' . $discard;
    }

    $table->data[] = [
        $student ? fullname($student) : '-',
        (int) $evaluation->postcount,
        $suggestedcell,
        s((string) $evaluation->rationale),
        $currentcell,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
