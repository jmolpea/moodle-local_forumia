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
 * Upgrade steps for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps for local_forumia.
 *
 * @param  int  $oldversion Version number of the currently installed plugin.
 * @return bool             True on success.
 */
function xmldb_local_forumia_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // Version 1.1.0 — 2025031001.
    // Add max_requests_user_day to local_forumia_config.
    // Limits the number of IA responses a single user can receive per day
    // in a given forum, preventing flood/spam abuse in immediate mode.
    // Default is 1 (one response per user per day).
    if ($oldversion < 2025031001) {
        $table = new xmldb_table('local_forumia_config');
        $field = new xmldb_field(
            'max_requests_user_day',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1', // Field default value is 1.
            'max_requests_day' // Add after this column.
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025031001, 'local', 'forumia');
    }

    // Version 1.2.0 — 2025031002.
    // Add delay_response to local_forumia_config.
    // When set to 1 (immediate mode only) the IA response is queued as an
    // adhoc task and published 1 hour after the student post, making the
    // reply feel more natural / less robotic.
    if ($oldversion < 2025031002) {
        $table = new xmldb_table('local_forumia_config');
        $field = new xmldb_field(
            'delay_response',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0', // Field default value is 0 (disabled).
            'max_requests_user_day' // Add after this column.
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025031002, 'local', 'forumia');
    }

    // Version 1.3.0 — 2026040800.
    // Add grading_prompt to local_forumia_config.
    // When the Moodle forum has whole-forum grading enabled (grade_forum > 0)
    // and this field is non-empty, the AI evaluates each student post and
    // assigns a grade via grade_update() in addition to posting a reply.
    if ($oldversion < 2026040800) {
        $table = new xmldb_table('local_forumia_config');
        $field = new xmldb_field(
            'grading_prompt',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'delay_response'  // Add after this column.
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026040800, 'local', 'forumia');
    }

    // Version 1.4.0 — 2026070700.
    // Adds the forum inactivity feature: when enabled on a forum and no post
    // has been published for inactivity_days days, the AI creates a new
    // discussion to encourage participation. last_inactivity_post records the
    // last trigger so the check does not fire again every day.
    if ($oldversion < 2026070700) {
        $table = new xmldb_table('local_forumia_config');

        $fields = [
            new xmldb_field('inactivity_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grading_prompt'),
            new xmldb_field('inactivity_days', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '7', 'inactivity_enabled'),
            new xmldb_field('inactivity_prompt', XMLDB_TYPE_TEXT, null, null, null, null, null, 'inactivity_days'),
            new xmldb_field('last_inactivity_post', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'inactivity_prompt'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026070700, 'local', 'forumia');
    }

    // Version 1.5.0 — 2026071000.
    // Reworks the inactivity feature to reply inside existing discussions
    // instead of starting a new one. Adds:
    // - inactivity_repeat_days: minimum gap between AI reactivation replies in
    // the same discussion, so a short inactivity threshold no longer nags daily.
    // - inactivity_deadline: timestamp after which reactivation stops. 0 falls
    // back to the forum's due date.
    if ($oldversion < 2026071000) {
        $table = new xmldb_table('local_forumia_config');

        $repeat = new xmldb_field(
            'inactivity_repeat_days',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '7',
            'inactivity_days'
        );
        if (!$dbman->field_exists($table, $repeat)) {
            $dbman->add_field($table, $repeat);
        }

        $deadline = new xmldb_field(
            'inactivity_deadline',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'inactivity_prompt'
        );
        if (!$dbman->field_exists($table, $deadline)) {
            $dbman->add_field($table, $deadline);
        }

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'forumia');
    }

    // Version 1.5.3 — 2026080501.
    // Moves the global OpenAI model default from gpt-4.1-nano to gpt-5.6-luna.
    //
    // Changing the default in settings.php only affects fresh installs: Moodle
    // stores the chosen value in config_plugins at install time, so existing
    // sites would silently stay on gpt-4.1-nano. This step migrates them.
    //
    // It only rewrites the value when it is still the OLD default (or unset).
    // A site that deliberately picked gpt-4o, gpt-5.1, etc. keeps its choice —
    // an upgrade step must not overwrite a decision the admin made on purpose.
    //
    // This touches ONLY the site-wide model setting in config_plugins. Per-forum
    // configuration in local_forumia_config is untouched (that table has no model
    // column — the model has always been a global setting).
    if ($oldversion < 2026080501) {
        $currentmodel = get_config('local_forumia', 'model');

        if ($currentmodel === false || $currentmodel === '' || $currentmodel === 'gpt-4.1-nano') {
            set_config('model', 'gpt-5.6-luna', 'local_forumia');
        }

        upgrade_plugin_savepoint(true, 2026080501, 'local', 'forumia');
    }

    // Version 1.7.0 — 2026082500.
    // Backfills 'firstinstall' for sites installed before the trial period
    // existed. db/install.php sets it on fresh installations; this covers the
    // upgrade path.
    //
    // The value is only written when it is absent, so an upgrade never resets
    // a trial that is already running or already spent.
    //
    // Note the consequence for existing sites: they get the trial window
    // starting from this upgrade, not from their original install date, which
    // we cannot recover. That is deliberate and generous — an unlicensed site
    // upgrading to this version gains a grace period rather than being cut off
    // the moment the licence gate becomes the only path.
    if ($oldversion < 2026082500) {
        if (!get_config('local_forumia', 'firstinstall')) {
            set_config('firstinstall', time(), 'local_forumia');
        }

        upgrade_plugin_savepoint(true, 2026082500, 'local', 'forumia');
    }

    // Version 1.8.0 — 2026093000. Changes requested in the Moodle Marketplace review.
    // - AI grading is decoupled from replies and becomes an explicit per-forum
    // opt-in: grading_mode (0 off, 1 suggest, 2 automatic), grading_delay
    // (hours after a student's first post) and grading_userid (the teacher
    // recorded as grader in automatic mode). Every existing forum starts with
    // grading OFF, including forums that kept the old pre-filled grading
    // prompt: nobody explicitly opted in to AI grading there.
    // - local_forumia_suggestion: one AI evaluation per student and forum.
    // - Removes three settings that no code ever read.
    if ($oldversion < 2026093000) {
        $table  = new xmldb_table('local_forumia_config');
        $fields = [
            new xmldb_field('grading_mode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grading_prompt'),
            new xmldb_field('grading_delay', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '12', 'grading_mode'),
            new xmldb_field('grading_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'grading_delay'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $key = new xmldb_key('fk_grading_userid', XMLDB_KEY_FOREIGN, ['grading_userid'], 'user', ['id']);
        $dbman->add_key($table, $key);

        $table = new xmldb_table('local_forumia_suggestion');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('forumid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grade', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('grademax', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rationale', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('postcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_forumid', XMLDB_KEY_FOREIGN, ['forumid'], 'forum', ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('idx_forumid_userid', XMLDB_INDEX_UNIQUE, ['forumid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        unset_config('userratelimit_enabled', 'local_forumia');
        unset_config('userratelimit_max', 'local_forumia');
        unset_config('dailyhour', 'local_forumia');

        upgrade_plugin_savepoint(true, 2026093000, 'local', 'forumia');
    }

    return true;
}
