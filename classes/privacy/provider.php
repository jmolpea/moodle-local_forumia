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
 * Privacy provider for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_forumia.
 *
 * Data sent to the configured external AI provider (OpenAI, Anthropic, Google
 * Gemini or DeepSeek). Post text is always plain text (HTML stripped) and
 * truncated; names, email addresses and user IDs are never sent.
 * - Immediate mode: the text of the student post being answered and the forum
 *   description.
 * - Daily mode: the text of the student posts from the last 24 hours, with
 *   authors replaced by anonymous labels (Student 1, Student 2...).
 * - Reactivation mode: the forum name, the forum description, the discussion
 *   subject and the recent posts of the discussion by every participant,
 *   teachers included, with authors replaced by anonymous labels.
 * - AI grading: the forum description, all of one student's posts in the
 *   forum with their discussion subjects, and, for each reply, the text of
 *   the message it answers (which may be by a classmate or a teacher). Other
 *   authors are referred to only by role.
 *
 * Data stored by the plugin:
 * - local_forumia_config.bot_userid: the designated account that publishes
 *   the assistant's replies in a forum.
 * - local_forumia_config.grading_userid: the teacher who enabled automatic
 *   AI grading and is recorded as the grader.
 * - local_forumia_suggestion: one AI evaluation per student and forum.
 *
 * The replies themselves are ordinary forum posts, and applied grades are
 * ordinary forum grades: mod_forum's privacy provider handles both.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] User columns of local_forumia_config. */
    private const CONFIG_USER_FIELDS = ['bot_userid', 'grading_userid'];

    /**
     * Describes the data this plugin sends and stores.
     *
     * @param  collection $collection The metadata collection to populate.
     * @return collection             The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'ai_provider_api',
            [
                'forum_post_content' => 'privacy:metadata:ai_provider_api:forum_post_content',
                'forum_name'         => 'privacy:metadata:ai_provider_api:forum_name',
                'forum_intro'        => 'privacy:metadata:ai_provider_api:forum_intro',
                'discussion_subject' => 'privacy:metadata:ai_provider_api:discussion_subject',
            ],
            'privacy:metadata:ai_provider_api'
        );

        $collection->add_database_table(
            'local_forumia_config',
            [
                'bot_userid'     => 'privacy:metadata:config:bot_userid',
                'grading_userid' => 'privacy:metadata:config:grading_userid',
            ],
            'privacy:metadata:config'
        );

        $collection->add_database_table(
            'local_forumia_suggestion',
            [
                'userid'       => 'privacy:metadata:suggestion:userid',
                'grade'        => 'privacy:metadata:suggestion:grade',
                'grademax'     => 'privacy:metadata:suggestion:grademax',
                'rationale'    => 'privacy:metadata:suggestion:rationale',
                'postcount'    => 'privacy:metadata:suggestion:postcount',
                'status'       => 'privacy:metadata:suggestion:status',
                'timecreated'  => 'privacy:metadata:suggestion:timecreated',
                'timemodified' => 'privacy:metadata:suggestion:timemodified',
            ],
            'privacy:metadata:suggestion'
        );

        // The usage table is deliberately not declared: it holds only a forum
        // ID, a date and a request counter — no user reference of any kind.

        return $collection;
    }

    /**
     * SQL joining a forum module context to a table with a forumid column.
     *
     * @param  string $table Table name (without prefix).
     * @param  string $alias Alias for that table.
     * @return string
     */
    private static function context_join(string $table, string $alias): string {
        return "FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {{$table}} {$alias} ON {$alias}.forumid = cm.instance";
    }

    /**
     * Returns the forum ID for a forum module context, or null.
     *
     * @param  \context $context Context.
     * @return int|null
     */
    private static function get_forumid(\context $context): ?int {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return null;
        }
        $cm = get_coursemodule_from_id('forum', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'forum', 'userid' => $userid];

        $contextlist = new contextlist();
        foreach (self::CONFIG_USER_FIELDS as $field) {
            $contextlist->add_from_sql(
                'SELECT ctx.id ' . self::context_join('local_forumia_config', 'c') . " WHERE c.{$field} = :userid",
                $params
            );
        }
        $contextlist->add_from_sql(
            'SELECT ctx.id ' . self::context_join('local_forumia_suggestion', 's') . ' WHERE s.userid = :userid',
            $params
        );
        return $contextlist;
    }

    /**
     * Get the list of users who have data within the specified context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $forumid = self::get_forumid($userlist->get_context());
        if ($forumid === null) {
            return;
        }
        $params = ['forumid' => $forumid];
        foreach (self::CONFIG_USER_FIELDS as $field) {
            $userlist->add_from_sql(
                $field,
                "SELECT {$field} FROM {local_forumia_config} WHERE forumid = :forumid AND {$field} > 0",
                $params
            );
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_forumia_suggestion} WHERE forumid = :forumid', $params);
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The list of approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $forumid = self::get_forumid($context);
            if ($forumid === null) {
                continue;
            }
            $subcontext = [get_string('pluginname', 'local_forumia')];

            $config = $DB->get_record('local_forumia_config', ['forumid' => $forumid]);
            if ($config && ((int) $config->bot_userid === $userid || (int) $config->grading_userid === $userid)) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:forumroles', 'local_forumia')]),
                    (object) [
                        'assistantaccount'     => transform::yesno((int) $config->bot_userid === $userid),
                        'automaticgradinguser' => transform::yesno((int) $config->grading_userid === $userid),
                        'timemodified'         => transform::datetime($config->timemodified),
                    ]
                );
            }

            $evaluation = $DB->get_record('local_forumia_suggestion', ['forumid' => $forumid, 'userid' => $userid]);
            if ($evaluation) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:gradesuggestion', 'local_forumia')]),
                    (object) [
                        'grade'        => $evaluation->grade,
                        'grademax'     => $evaluation->grademax,
                        'rationale'    => $evaluation->rationale,
                        'postcount'    => $evaluation->postcount,
                        'status'       => get_string('privacy:status' . (int) $evaluation->status, 'local_forumia'),
                        'timecreated'  => transform::datetime($evaluation->timecreated),
                        'timemodified' => transform::datetime($evaluation->timemodified),
                    ]
                );
            }
        }
    }

    /**
     * Removes a user's links from a forum's configuration.
     *
     * The configuration row is kept (it holds the teacher's prompts). Removing
     * the assistant account disables the assistant; removing the automatic
     * grading teacher downgrades automatic grading to suggestions, because an
     * automatic grade must always have a real grader.
     *
     * @param  int      $forumid Forum ID.
     * @param  int|null $userid  Only links to this user; null for any user.
     * @return void
     */
    private static function unlink_user(int $forumid, ?int $userid): void {
        global $DB;

        $config = $DB->get_record('local_forumia_config', ['forumid' => $forumid]);
        if (!$config) {
            return;
        }
        $changed = false;
        if ((int) $config->bot_userid > 0 && ($userid === null || (int) $config->bot_userid === $userid)) {
            $config->bot_userid = 0;
            $config->enabled    = 0;
            $changed = true;
        }
        if ((int) $config->grading_userid > 0 && ($userid === null || (int) $config->grading_userid === $userid)) {
            $config->grading_userid = 0;
            if ((int) $config->grading_mode === 2) {
                $config->grading_mode = 1;
            }
            $changed = true;
        }
        if ($changed) {
            $config->timemodified = time();
            $DB->update_record('local_forumia_config', $config);
        }
    }

    /**
     * Delete all user data for the specified context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $forumid = self::get_forumid($context);
        if ($forumid === null) {
            return;
        }
        $DB->delete_records('local_forumia_suggestion', ['forumid' => $forumid]);
        self::unlink_user($forumid, null);
    }

    /**
     * Delete all user data for the specified user within the specified contexts.
     *
     * @param approved_contextlist $contextlist The list of approved contexts and users.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $forumid = self::get_forumid($context);
            if ($forumid === null) {
                continue;
            }
            $DB->delete_records('local_forumia_suggestion', ['forumid' => $forumid, 'userid' => $userid]);
            self::unlink_user($forumid, $userid);
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The userlist to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $forumid = self::get_forumid($userlist->get_context());
        if ($forumid === null) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_forumia_suggestion', ['forumid' => $forumid, 'userid' => (int) $userid]);
            self::unlink_user($forumid, (int) $userid);
        }
    }
}
