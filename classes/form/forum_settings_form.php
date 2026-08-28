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
 * Per-forum configuration form for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for configuring the AI assistant on a specific forum.
 *
 * Expects three values in $customdata: 'forumid', 'cmid' and 'course'.
 */
class forum_settings_form extends \moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition(): void {
        global $DB;

        $mform   = $this->_form;
        $forumid = $this->_customdata['forumid'];
        $cmid    = $this->_customdata['cmid'];
        $course  = $this->_customdata['course'];

        $mform->addElement('hidden', 'forumid', $forumid);
        $mform->setType('forumid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // 1. Enable toggle.
        $mform->addElement('advcheckbox', 'enabled', get_string('forum_enabled', 'local_forumia'), '');
        $mform->setDefault('enabled', 0);

        // 2. Bot user selector.
        $candidateuserids = [];

        // Site default bot.
        $defaultbotsetting = get_config('local_forumia', 'defaultbot');
        if (!empty($defaultbotsetting)) {
            if (ctype_digit((string) $defaultbotsetting)) {
                $candidateuserids[] = (int) $defaultbotsetting;
            } else {
                $botuser = $DB->get_record('user', ['username' => clean_param($defaultbotsetting, PARAM_USERNAME)]);
                if ($botuser) {
                    $candidateuserids[] = (int) $botuser->id;
                }
            }
        }

        // Teachers and managers in the course.
        // Use $DB->get_record('role', ...) — get_role_by_shortname() does not exist in Moodle 4.5.
        $context      = \context_course::instance($course->id);
        $managerroles = ['editingteacher', 'teacher', 'manager', 'coursecreator'];
        foreach ($managerroles as $roleshortname) {
            $role = $DB->get_record('role', ['shortname' => $roleshortname]);
            if (!$role) {
                continue;
            }
            $users = get_role_users($role->id, $context, false, 'u.id', 'u.id');
            foreach ($users as $u) {
                $candidateuserids[] = (int) $u->id;
            }
        }

        $candidateuserids = array_unique($candidateuserids);

        // Build select options from the candidate user IDs.
        $useroptions = ['' => get_string('choosedots')];
        if (!empty($candidateuserids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($candidateuserids, SQL_PARAMS_NAMED);
            $candidateusers = $DB->get_records_select(
                'user',
                "id $insql AND deleted = 0 AND suspended = 0",
                $inparams,
                'lastname ASC, firstname ASC',
                'id, firstname, lastname, username, firstnamephonetic, lastnamephonetic, middlename, alternatename'
            );
            foreach ($candidateusers as $u) {
                $useroptions[$u->id] = fullname($u) . ' (' . $u->username . ')';
            }
        }

        $mform->addElement('select', 'bot_userid', get_string('forum_botuser', 'local_forumia'), $useroptions);
        $mform->setType('bot_userid', PARAM_INT);

        // 3. Response mode.
        $radioarray = [
            $mform->createElement('radio', 'response_mode', '', get_string('forum_mode_immediate', 'local_forumia'), 'immediate'),
            $mform->createElement('radio', 'response_mode', '', get_string('forum_mode_daily', 'local_forumia'), 'daily'),
        ];
        $mform->addGroup($radioarray, 'response_mode_group', get_string('forum_mode', 'local_forumia'), ['<br>'], false);
        $mform->setDefault('response_mode', 'immediate');

        // 3b. Delay response (immediate mode only).
        $mform->addElement(
            'advcheckbox',
            'delay_response',
            get_string('forum_delay_response', 'local_forumia'),
            get_string('forum_delay_response_label', 'local_forumia')
        );
        $mform->setDefault('delay_response', 0);
        $mform->addElement('static', 'delay_response_note', '', get_string('forum_delay_response_desc', 'local_forumia'));
        // Only relevant for immediate mode.
        $mform->hideIf('delay_response', 'response_mode', 'neq', 'immediate');
        $mform->hideIf('delay_response_note', 'response_mode', 'neq', 'immediate');

        // 4. Prompt for immediate mode.
        $mform->addElement(
            'textarea',
            'immediate_prompt',
            get_string('forum_prompt_immediate', 'local_forumia'),
            ['rows' => 18, 'cols' => 70, 'placeholder' => get_string('forum_prompt_immediate_placeholder', 'local_forumia')]
        );
        $mform->setType('immediate_prompt', PARAM_TEXT);
        // Pre-fill with a ready-made, well-structured prompt so teachers can edit
        // rather than write from scratch. set_data() overrides this for forums
        // that already have a saved configuration.
        $mform->setDefault('immediate_prompt', get_string('forum_prompt_immediate_default', 'local_forumia'));
        $mform->addElement('static', 'immediate_prompt_note', '', get_string('forum_prompt_immediate_desc', 'local_forumia'));

        // 5. Prompt for daily mode.
        $mform->addElement(
            'textarea',
            'daily_prompt',
            get_string('forum_prompt_daily', 'local_forumia'),
            ['rows' => 18, 'cols' => 70, 'placeholder' => get_string('forum_prompt_daily_placeholder', 'local_forumia')]
        );
        $mform->setType('daily_prompt', PARAM_TEXT);
        $mform->setDefault('daily_prompt', get_string('forum_prompt_daily_default', 'local_forumia'));

        // 6. Disclaimer.
        $mform->addElement(
            'textarea',
            'disclaimer',
            get_string('forum_disclaimer', 'local_forumia'),
            ['rows' => 4, 'cols' => 70]
        );
        $mform->setDefault('disclaimer', get_string('disclaimer_default', 'local_forumia'));
        $mform->setType('disclaimer', PARAM_TEXT);
        $mform->addElement('static', 'disclaimer_note', '', get_string('forum_disclaimer_desc', 'local_forumia'));

        // 7. Daily request limit (forum).
        $globalmax = (int) get_config('local_forumia', 'siteratelimit_max') ?: 50;
        $mform->addElement(
            'text',
            'max_requests_day',
            get_string('forum_maxrequests', 'local_forumia'),
            ['size' => 6]
        );
        $mform->setType('max_requests_day', PARAM_INT);
        $mform->setDefault('max_requests_day', $globalmax);
        $mform->addElement('static', 'max_requests_day_note', '', get_string('forum_maxrequests_desc', 'local_forumia'));

        // 8. Grading prompt (only used when the forum has whole-forum grading enabled).
        $mform->addElement(
            'textarea',
            'grading_prompt',
            get_string('forum_grading_prompt', 'local_forumia'),
            ['rows' => 16, 'cols' => 70,
             'placeholder' => get_string('forum_grading_prompt_placeholder', 'local_forumia')]
        );
        $mform->setType('grading_prompt', PARAM_TEXT);
        $mform->setDefault('grading_prompt', get_string('forum_grading_prompt_default', 'local_forumia'));
        $mform->addElement('static', 'grading_prompt_note', '', get_string('forum_grading_prompt_desc', 'local_forumia'));

        // 9. Daily request limit per user.
        $mform->addElement(
            'text',
            'max_requests_user_day',
            get_string('forum_maxrequests_user', 'local_forumia'),
            ['size' => 6]
        );
        $mform->setType('max_requests_user_day', PARAM_INT);
        $mform->setDefault('max_requests_user_day', 1);
        $mform->addElement('static', 'max_requests_user_day_note', '', get_string('forum_maxrequests_user_desc', 'local_forumia'));

        // 10. Inactivity discussion starter.
        $mform->addElement(
            'advcheckbox',
            'inactivity_enabled',
            get_string('forum_inactivity_enabled', 'local_forumia'),
            get_string('forum_inactivity_enabled_label', 'local_forumia')
        );
        $mform->setDefault('inactivity_enabled', 0);
        $mform->addElement('static', 'inactivity_enabled_note', '', get_string('forum_inactivity_enabled_desc', 'local_forumia'));

        $mform->addElement(
            'text',
            'inactivity_days',
            get_string('forum_inactivity_days', 'local_forumia'),
            ['size' => 6]
        );
        $mform->setType('inactivity_days', PARAM_INT);
        $mform->setDefault('inactivity_days', 7);
        $mform->addElement('static', 'inactivity_days_note', '', get_string('forum_inactivity_days_desc', 'local_forumia'));
        $mform->hideIf('inactivity_days', 'inactivity_enabled', 'notchecked');
        $mform->hideIf('inactivity_days_note', 'inactivity_enabled', 'notchecked');

        // 10b. Minimum gap between reactivation replies in the same discussion.
        $mform->addElement(
            'text',
            'inactivity_repeat_days',
            get_string('forum_inactivity_repeat_days', 'local_forumia'),
            ['size' => 6]
        );
        $mform->setType('inactivity_repeat_days', PARAM_INT);
        $mform->setDefault('inactivity_repeat_days', 7);
        $mform->addElement(
            'static',
            'inactivity_repeat_days_note',
            '',
            get_string('forum_inactivity_repeat_days_desc', 'local_forumia')
        );
        $mform->hideIf('inactivity_repeat_days', 'inactivity_enabled', 'notchecked');
        $mform->hideIf('inactivity_repeat_days_note', 'inactivity_enabled', 'notchecked');

        // 10c. Optional deadline after which reactivation stops.
        $mform->addElement(
            'date_selector',
            'inactivity_deadline',
            get_string('forum_inactivity_deadline', 'local_forumia'),
            ['optional' => true]
        );
        $mform->addElement('static', 'inactivity_deadline_note', '', get_string('forum_inactivity_deadline_desc', 'local_forumia'));
        $mform->hideIf('inactivity_deadline', 'inactivity_enabled', 'notchecked');
        $mform->hideIf('inactivity_deadline_note', 'inactivity_enabled', 'notchecked');

        $mform->addElement(
            'textarea',
            'inactivity_prompt',
            get_string('forum_inactivity_prompt', 'local_forumia'),
            ['rows' => 14, 'cols' => 70,
             'placeholder' => get_string('forum_inactivity_prompt_placeholder', 'local_forumia')]
        );
        $mform->setType('inactivity_prompt', PARAM_TEXT);
        $mform->setDefault('inactivity_prompt', get_string('forum_inactivity_prompt_default', 'local_forumia'));
        $mform->addElement('static', 'inactivity_prompt_note', '', get_string('forum_inactivity_prompt_desc', 'local_forumia'));
        $mform->hideIf('inactivity_prompt', 'inactivity_enabled', 'notchecked');
        $mform->hideIf('inactivity_prompt_note', 'inactivity_enabled', 'notchecked');

        // Submit button.
        $this->add_action_buttons(true, get_string('forum_save', 'local_forumia'));
    }

    /**
     * Validates the submitted form data.
     *
     * @param  array $data  Submitted form data.
     * @param  array $files Uploaded files (not used).
     * @return array        Associative array of field => error message.
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        if (!empty($data['enabled'])) {
            if (empty($data['bot_userid'])) {
                $errors['bot_userid'] = get_string('error_nobotuser', 'local_forumia', $data['forumid']);
            } else {
                $user = $DB->get_record('user', ['id' => $data['bot_userid'], 'deleted' => 0, 'suspended' => 0]);
                if (!$user) {
                    $errors['bot_userid'] = get_string('error_nobotuser', 'local_forumia', $data['forumid']);
                }
            }

            if (!in_array($data['response_mode'] ?? '', ['immediate', 'daily'], true)) {
                $errors['response_mode_group'] = get_string('required');
            }

            if (isset($data['max_requests_day']) && (int) $data['max_requests_day'] < 1) {
                $errors['max_requests_day'] = get_string('required');
            }

            // 0 is allowed for max_requests_user_day (means unlimited).
            if (isset($data['max_requests_user_day']) && (int) $data['max_requests_user_day'] < 0) {
                $errors['max_requests_user_day'] = get_string('error_maxrequests_user_invalid', 'local_forumia');
            }

            // Inactivity threshold must be at least 1 day when the feature is on.
            if (!empty($data['inactivity_enabled']) && (int) ($data['inactivity_days'] ?? 0) < 1) {
                $errors['inactivity_days'] = get_string('error_inactivity_days_invalid', 'local_forumia');
            }

            // Repeat interval must be at least 1 day when the feature is on.
            if (!empty($data['inactivity_enabled']) && (int) ($data['inactivity_repeat_days'] ?? 0) < 1) {
                $errors['inactivity_repeat_days'] = get_string('error_inactivity_repeat_invalid', 'local_forumia');
            }
        }

        return $errors;
    }
}
