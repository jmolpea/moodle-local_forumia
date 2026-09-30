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
 * Privacy provider tests.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_forumia\privacy\provider;

/**
 * Privacy provider tests.
 *
 * @covers \local_forumia\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass Forum. */
    private \stdClass $forum;

    /** @var \context_module Forum context. */
    private \context_module $context;

    /** @var \stdClass Assistant account. */
    private \stdClass $bot;

    /** @var \stdClass Student with a pending suggestion. */
    private \stdClass $student;

    /** @var \stdClass Teacher recorded for automatic grading. */
    private \stdClass $teacher;

    /**
     * Creates a forum with an assistant account and a grade suggestion.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $generator     = $this->getDataGenerator();
        $course        = $generator->create_course();
        $this->forum   = $generator->create_module('forum', ['course' => $course->id, 'grade_forum' => 10]);
        $this->context = \context_module::instance($this->forum->cmid);
        $this->bot     = $generator->create_user();
        $this->student = $generator->create_user();
        $this->teacher = $generator->create_user();

        $DB->insert_record('local_forumia_config', (object) [
            'forumid' => $this->forum->id, 'enabled' => 1, 'bot_userid' => $this->bot->id,
            'response_mode' => 'immediate', 'grading_mode' => 2, 'grading_userid' => $this->teacher->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        grade_suggestions::record((int) $this->forum->id, (int) $this->student->id, 6, 10, 'Reason.', 1);
    }

    /**
     * The automatic-grading teacher is reported, and deleting them downgrades
     * automatic grading to suggestions.
     */
    public function test_automatic_grading_teacher(): void {
        global $DB;

        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($this->teacher->id)->get_contextids());

        provider::delete_data_for_user(new approved_contextlist($this->teacher, 'local_forumia', [$this->context->id]));

        $config = $DB->get_record('local_forumia_config', ['forumid' => $this->forum->id]);
        $this->assertEquals(0, $config->grading_userid);
        $this->assertEquals(1, $config->grading_mode);
        $this->assertEquals($this->bot->id, $config->bot_userid, 'Other links are untouched.');
    }

    /**
     * Both the assistant account and the student are found in the forum context.
     */
    public function test_contexts_and_users_are_reported(): void {
        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($this->bot->id)->get_contextids());
        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($this->student->id)->get_contextids());

        $userlist = new userlist($this->context, 'local_forumia');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->bot->id, $this->student->id, $this->teacher->id], $userlist->get_userids());
    }

    /**
     * The student's suggestion is exported.
     */
    public function test_export(): void {
        $this->export_context_data_for_user($this->student->id, $this->context, 'local_forumia');

        $data = writer::with_context($this->context)->get_data([
            get_string('pluginname', 'local_forumia'),
            get_string('privacy:gradesuggestion', 'local_forumia'),
        ]);
        $this->assertEquals(6, $data->grade);
    }

    /**
     * Deleting a user removes the suggestion and unlinks the assistant account.
     */
    public function test_delete_for_user(): void {
        global $DB;

        foreach ([$this->student, $this->bot] as $user) {
            provider::delete_data_for_user(new approved_contextlist($user, 'local_forumia', [$this->context->id]));
        }

        $this->assertFalse($DB->record_exists('local_forumia_suggestion', ['forumid' => $this->forum->id]));
        $config = $DB->get_record('local_forumia_config', ['forumid' => $this->forum->id]);
        $this->assertEquals(0, $config->bot_userid);
        $this->assertEquals(0, $config->enabled);
    }

    /**
     * Deleting a list of users in a context.
     */
    public function test_delete_for_users(): void {
        global $DB;

        provider::delete_data_for_users(new approved_userlist($this->context, 'local_forumia', [$this->student->id]));

        $this->assertFalse($DB->record_exists('local_forumia_suggestion', ['forumid' => $this->forum->id]));
        $this->assertEquals($this->bot->id, $DB->get_field('local_forumia_config', 'bot_userid', ['forumid' => $this->forum->id]));
    }

    /**
     * Deleting everything in the context.
     */
    public function test_delete_all_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertFalse($DB->record_exists('local_forumia_suggestion', ['forumid' => $this->forum->id]));
        $this->assertEquals(0, $DB->get_field('local_forumia_config', 'bot_userid', ['forumid' => $this->forum->id]));
    }
}
