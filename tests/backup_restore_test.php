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
 * Backup and restore round-trip tests for the per-forum configuration.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore round-trip tests.
 *
 * The reason this file exists: seven configuration columns were once missing
 * from the backup element. Restores did not fail — they silently wrote column
 * defaults instead, so a teacher's tuned prompts and rubric vanished on course
 * rollover with no error anywhere. This test compares every column so the same
 * omission cannot happen again unnoticed.
 *
 * @covers \backup_local_forumia_plugin
 * @covers \restore_local_forumia_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Columns that must survive a backup and restore unchanged.
     *
     * Deliberately spelled out rather than derived from the table: the point is
     * to fail when a new column is added and not wired into the backup.
     *
     * @var string[]
     */
    private const PRESERVED_COLUMNS = [
        'enabled',
        'response_mode',
        'daily_prompt',
        'immediate_prompt',
        'disclaimer',
        'max_requests_day',
        'max_requests_user_day',
        'delay_response',
        'grading_prompt',
        'inactivity_enabled',
        'inactivity_days',
        'inactivity_repeat_days',
        'inactivity_prompt',
        'inactivity_deadline',
    ];

    /**
     * Every configuration column survives a backup and restore.
     */
    public function test_configuration_survives_a_round_trip(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $generator->create_module('forum', ['course' => $course->id]);
        $bot       = $generator->create_user();
        $generator->enrol_user($bot->id, $course->id, 'student');

        // Deliberately non-default values everywhere, so a column that silently
        // falls back to its default is caught.
        $original = (object) [
            'forumid'                => $forum->id,
            'enabled'                => 1,
            'bot_userid'             => $bot->id,
            'response_mode'          => 'daily',
            'daily_prompt'           => 'Custom daily prompt for the round trip.',
            'immediate_prompt'       => 'Custom immediate prompt for the round trip.',
            'disclaimer'             => 'Custom disclaimer.',
            'max_requests_day'       => 37,
            'max_requests_user_day'  => 4,
            'delay_response'         => 1,
            'grading_prompt'         => 'Custom rubric: relevance 50%, clarity 50%.',
            'inactivity_enabled'     => 1,
            'inactivity_days'        => 11,
            'inactivity_repeat_days' => 5,
            'inactivity_prompt'      => 'Custom reactivation prompt.',
            'inactivity_deadline'    => 1893456000,
            'last_inactivity_post'   => 1700000000,
            'timecreated'            => 1600000000,
            'timemodified'           => 1600000001,
        ];
        $DB->insert_record('local_forumia_config', $original);

        $newcourseid = $this->backup_and_restore($course->id, $USER->id);

        $restoredforum = $DB->get_record('forum', ['course' => $newcourseid], '*', MUST_EXIST);
        $restored = $DB->get_record('local_forumia_config', ['forumid' => $restoredforum->id]);

        $this->assertNotFalse($restored, 'The configuration was not restored at all.');

        foreach (self::PRESERVED_COLUMNS as $column) {
            $this->assertEquals(
                $original->$column,
                $restored->$column,
                "Column '{$column}' was lost or reset during backup/restore. "
                . 'Add it to backup_local_forumia_plugin::define_module_plugin_structure().'
            );
        }
    }

    /**
     * The bot user is remapped to the restored account.
     */
    public function test_bot_user_is_remapped(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $generator->create_module('forum', ['course' => $course->id]);
        $bot       = $generator->create_user();
        $generator->enrol_user($bot->id, $course->id, 'student');

        $DB->insert_record('local_forumia_config', (object) [
            'forumid'      => $forum->id,
            'enabled'      => 1,
            'bot_userid'   => $bot->id,
            'response_mode' => 'immediate',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $newcourseid = $this->backup_and_restore($course->id, $USER->id);

        $restoredforum = $DB->get_record('forum', ['course' => $newcourseid], '*', MUST_EXIST);
        $restored = $DB->get_record('local_forumia_config', ['forumid' => $restoredforum->id], '*', MUST_EXIST);

        $this->assertEquals($bot->id, $restored->bot_userid);
    }

    /**
     * The inactivity run marker is reset, not carried over.
     *
     * It records when the task last ran against the SOURCE forum. Copying it
     * would make the restored forum look already-processed and suppress its
     * first reactivation.
     */
    public function test_last_inactivity_marker_is_reset(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $generator->create_module('forum', ['course' => $course->id]);
        $bot       = $generator->create_user();
        $generator->enrol_user($bot->id, $course->id, 'student');

        $DB->insert_record('local_forumia_config', (object) [
            'forumid'              => $forum->id,
            'enabled'              => 1,
            'bot_userid'           => $bot->id,
            'response_mode'        => 'immediate',
            'inactivity_enabled'   => 1,
            'last_inactivity_post' => time(),
            'timecreated'          => time(),
            'timemodified'         => time(),
        ]);

        $newcourseid = $this->backup_and_restore($course->id, $USER->id);

        $restoredforum = $DB->get_record('forum', ['course' => $newcourseid], '*', MUST_EXIST);
        $restored = $DB->get_record('local_forumia_config', ['forumid' => $restoredforum->id], '*', MUST_EXIST);

        $this->assertEquals(0, $restored->last_inactivity_post);
    }

    /**
     * A forum with no assistant configuration restores cleanly.
     */
    public function test_forum_without_configuration_restores_without_a_row(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $generator->create_module('forum', ['course' => $course->id]);

        $newcourseid = $this->backup_and_restore($course->id, $USER->id);

        $restoredforum = $DB->get_record('forum', ['course' => $newcourseid], '*', MUST_EXIST);

        $this->assertFalse($DB->record_exists('local_forumia_config', ['forumid' => $restoredforum->id]));
    }

    /**
     * Backs a course up and restores it into a new one.
     *
     * @param  int $courseid Course to back up.
     * @param  int $userid   User performing the operation.
     * @return int           ID of the newly restored course.
     */
    private function backup_and_restore(int $courseid, int $userid): int {
        global $CFG;

        $backupid = 'local_forumia_test_' . $courseid;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $file    = $results['backup_destination'];

        $path = make_backup_temp_directory($backupid, false);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED-' . $courseid, 1);

        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
