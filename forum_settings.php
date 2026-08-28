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
 * Per-forum IA Assistant settings page for local_forumia.
 *
 * Accessible via the forum's Settings navigation: Settings > IA Assistant.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

// Parameters and access control.
$forumid = required_param('forumid', PARAM_INT);
$cmid    = required_param('cmid', PARAM_INT);

$forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
$cm    = get_coursemodule_from_id('forum', $cmid, 0, false, MUST_EXIST);

// SECURITY: Verify that the supplied forumid belongs to the supplied cmid.
// Without this check an attacker with managesettings on course A could pass a
// forumid from course B, bypassing the capability check (IDOR).
if ((int)$cm->instance !== $forumid) {
    throw new moodle_exception('error_invalidforum', 'local_forumia');
}

// The require_login() call sets PAGE context to module context (level 70).
require_login($cm->course, false, $cm);

$coursecontext = context_course::instance($cm->course);
require_capability('local/forumia:managesettings', $coursecontext);

// Page setup — use the module context; downgrading context level is not allowed.
$modulecontext = context_module::instance($cmid);

$PAGE->set_url('/local/forumia/forum_settings.php', ['forumid' => $forumid, 'cmid' => $cmid]);
$PAGE->set_context($modulecontext);
$PAGE->set_title(get_string('forum_settings_title', 'local_forumia'));
$PAGE->set_heading(format_string($forum->name) . ' – ' . get_string('forum_settings_title', 'local_forumia'));
$PAGE->set_pagelayout('admin');

// Load existing configuration.
$existingconfig = $DB->get_record('local_forumia_config', ['forumid' => $forumid]);
$course         = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

$mform = new \local_forumia\form\forum_settings_form(
    new moodle_url('/local/forumia/forum_settings.php'),
    ['forumid' => $forumid, 'cmid' => $cmid, 'course' => $course]
);

if ($existingconfig) {
    $mform->set_data($existingconfig);
}

// Handle form submission.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/forum/view.php', ['id' => $cmid]));
} else if ($data = $mform->get_data()) {
    $record = new stdClass();
    $record->forumid = clean_param($data->forumid, PARAM_INT);
    $record->enabled = clean_param($data->enabled ?? 0, PARAM_INT);
    $record->bot_userid = clean_param($data->bot_userid, PARAM_INT);
    $record->response_mode = clean_param($data->response_mode, PARAM_ALPHA);
    $record->immediate_prompt = clean_param($data->immediate_prompt ?? '', PARAM_TEXT);
    $record->daily_prompt = clean_param($data->daily_prompt ?? '', PARAM_TEXT);
    $record->disclaimer = clean_param($data->disclaimer ?? '', PARAM_TEXT);
    $record->grading_prompt = clean_param($data->grading_prompt ?? '', PARAM_TEXT);
    $record->max_requests_day = clean_param($data->max_requests_day ?? 50, PARAM_INT);
    $record->max_requests_user_day = clean_param($data->max_requests_user_day ?? 1, PARAM_INT);
    $record->delay_response = clean_param($data->delay_response ?? 0, PARAM_INT);
    $record->inactivity_enabled = clean_param($data->inactivity_enabled ?? 0, PARAM_INT);
    $record->inactivity_days = max(1, clean_param($data->inactivity_days ?? 7, PARAM_INT));
    $record->inactivity_repeat_days = max(1, clean_param($data->inactivity_repeat_days ?? 7, PARAM_INT));
    $record->inactivity_deadline = clean_param($data->inactivity_deadline ?? 0, PARAM_INT);
    $record->inactivity_prompt = clean_param($data->inactivity_prompt ?? '', PARAM_TEXT);
    $record->timemodified = time();

    if (!in_array($record->response_mode, ['immediate', 'daily'], true)) {
        $record->response_mode = 'immediate';
    }

    if ($existingconfig) {
        $record->id = $existingconfig->id;
        $DB->update_record('local_forumia_config', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_forumia_config', $record);
    }

    redirect(
        new moodle_url('/local/forumia/forum_settings.php', ['forumid' => $forumid, 'cmid' => $cmid]),
        get_string('forum_saved', 'local_forumia'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Render the page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('forum_settings_title', 'local_forumia'));

// Licence banner. A running trial is not a problem — the assistant works — so
// it renders as information; every other non-valid state is a warning, because
// in those the assistant is doing nothing at all.
$licensebanner = \local_forumia\license\validator::get_banner();
if ($licensebanner !== null) {
    $istrial = \local_forumia\license\validator::check()->status
        === \local_forumia\license\validator::STATUS_TRIAL;
    echo $OUTPUT->notification(
        $licensebanner,
        $istrial
            ? \core\output\notification::NOTIFY_INFO
            : \core\output\notification::NOTIFY_WARNING
    );
}

$mform->display();
echo $OUTPUT->footer();
