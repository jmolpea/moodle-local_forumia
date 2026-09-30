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

use local_forumia\grade_suggestions;

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
        $mform   = $this->_form;
        $forumid = $this->_customdata['forumid'];
        $cmid    = $this->_customdata['cmid'];

        $mform->addElement('hidden', 'forumid', $forumid);
        $mform->setType('forumid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // 1. Enable toggle.
        $mform->addElement('advcheckbox', 'enabled', get_string('forum_enabled', 'local_forumia'), '');
        $mform->setDefault('enabled', 0);

        // 2. Assistant account selector. Only accounts a site administrator has
        // designated are offered (site default account, or holders of
        // local/forumia:actasassistant at system level). Course teachers and
        // managers are deliberately not candidates.
        $useroptions = ['' => get_string('choosedots')];
        foreach (\local_forumia\assistant_account::get_candidates() as $u) {
            $useroptions[$u->id] = fullname($u) . ' (' . $u->username . ')';
        }

        $mform->addElement('select', 'bot_userid', get_string('forum_botuser', 'local_forumia'), $useroptions);
        $mform->setType('bot_userid', PARAM_INT);
        $botusernote = count($useroptions) > 1 ? 'forum_botuser_desc' : 'forum_botuser_none';
        $mform->addElement('static', 'bot_userid_note', '', get_string($botusernote, 'local_forumia'));

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

        // 8. AI grading: explicit opt-in, off by default. Each student is
        // evaluated once, grading_delay hours after their first post.
        $mform->addElement('select', 'grading_mode', get_string('forum_grading_mode', 'local_forumia'), [
            grade_suggestions::MODE_OFF     => get_string('forum_grading_mode_off', 'local_forumia'),
            grade_suggestions::MODE_SUGGEST => get_string('forum_grading_mode_suggest', 'local_forumia'),
            grade_suggestions::MODE_AUTO    => get_string('forum_grading_mode_auto', 'local_forumia'),
        ]);
        $mform->setType('grading_mode', PARAM_INT);
        $mform->setDefault('grading_mode', grade_suggestions::MODE_OFF);
        $mform->addElement('static', 'grading_mode_note', '', get_string('forum_grading_mode_desc', 'local_forumia'));

        $mform->addElement('text', 'grading_delay', get_string('forum_grading_delay', 'local_forumia'), ['size' => 6]);
        $mform->setType('grading_delay', PARAM_INT);
        $mform->setDefault('grading_delay', 12);
        $mform->addElement('static', 'grading_delay_note', '', get_string('forum_grading_delay_desc', 'local_forumia'));
        $mform->hideIf('grading_delay', 'grading_mode', 'eq', grade_suggestions::MODE_OFF);
        $mform->hideIf('grading_delay_note', 'grading_mode', 'eq', grade_suggestions::MODE_OFF);

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
        $mform->hideIf('grading_prompt', 'grading_mode', 'eq', grade_suggestions::MODE_OFF);
        $mform->hideIf('grading_prompt_note', 'grading_mode', 'eq', grade_suggestions::MODE_OFF);

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
        $errors = parent::validation($data, $files);

        // AI grading needs a forum with point-based whole-forum grading, and
        // automatic mode records the saving teacher as the grader, so that
        // teacher must be allowed to grade this forum.
        $gradingmode = (int) ($data['grading_mode'] ?? grade_suggestions::MODE_OFF);
        if ($gradingmode !== grade_suggestions::MODE_OFF) {
            global $DB;
            $forum = $DB->get_record('forum', ['id' => $data['forumid']], '*', MUST_EXIST);
            if (!grade_suggestions::forum_supports_ai_grading($forum)) {
                $errors['grading_mode'] = get_string('error_grading_unsupported', 'local_forumia');
            }
            $delay = (int) ($data['grading_delay'] ?? 0);
            if ($delay < 1 || $delay > 720) {
                $errors['grading_delay'] = get_string('error_grading_delay_invalid', 'local_forumia');
            }
        }
        if ($gradingmode === grade_suggestions::MODE_AUTO) {
            $modulecontext = \context_module::instance((int) $data['cmid']);
            if (!has_capability('mod/forum:grade', $modulecontext)) {
                $errors['grading_mode'] = get_string('error_grading_auto_nocapability', 'local_forumia');
            }
        }

        if (!empty($data['enabled'])) {
            if (empty($data['bot_userid'])) {
                $errors['bot_userid'] = get_string('error_nobotuser', 'local_forumia', $data['forumid']);
            } else if (!\local_forumia\assistant_account::get_active_user((int) $data['bot_userid'])) {
                // Server-side check: the account must still be designated and
                // active, whatever value the browser submitted.
                $errors['bot_userid'] = get_string('error_botuser_notdesignated', 'local_forumia', $data['forumid']);
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
