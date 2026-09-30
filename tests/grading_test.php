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
 * Tests for the delayed AI evaluation of forum participation.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

use local_forumia\api\client_factory;
use local_forumia\license\validator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/local/forumia/tests/fixtures/testable_ai_client.php');
require_once($CFG->dirroot . '/local/forumia/tests/fixtures/fake_ai_client.php');

/**
 * Tests for the delayed AI evaluation of forum participation.
 *
 * What must hold: a student is evaluated once, only after the delay, with all
 * of their posts and the messages they answered as context; later posts never
 * trigger another evaluation; automatic mode only fills empty grades and
 * records the enabling teacher as the grader.
 *
 * @covers \local_forumia\forum_processor
 * @covers \local_forumia\grade_suggestions
 */
final class grading_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Forum graded out of 10. */
    private \stdClass $forum;

    /** @var \stdClass Student being evaluated. */
    private \stdClass $student;

    /** @var \stdClass Classmate. */
    private \stdClass $classmate;

    /** @var \stdClass Editing teacher. */
    private \stdClass $teacher;

    /** @var \stdClass Designated assistant account. */
    private \stdClass $bot;

    /** @var fake_ai_client Injected AI client. */
    private fake_ai_client $client;

    /**
     * Builds a graded forum, its participants and the fake AI client.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator       = $this->getDataGenerator();
        $this->course    = $generator->create_course();
        $this->forum     = $generator->create_module('forum', ['course' => $this->course->id, 'grade_forum' => 10]);
        $this->student   = $generator->create_user();
        $this->classmate = $generator->create_user();
        $this->teacher   = $generator->create_user();
        $this->bot       = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $generator->enrol_user($this->classmate->id, $this->course->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->bot->id, $this->course->id, 'student');
        set_config('defaultbot', $this->bot->username, 'local_forumia');

        set_config('firstinstall', time(), 'local_forumia');
        validator::reset_cache();

        $this->client = new fake_ai_client();
        $this->client->response = '{"grade": 7, "rationale": "Solid first post, thin replies."}';
        client_factory::set_test_client($this->client);
    }

    /**
     * Removes the injected client.
     */
    protected function tearDown(): void {
        client_factory::set_test_client(null);
        validator::reset_cache();
        parent::tearDown();
    }

    /**
     * Writes the forum configuration.
     *
     * @param  int $mode    Grading mode.
     * @param  int $grader  Teacher recorded for automatic mode.
     * @return void
     */
    private function configure(int $mode, int $grader = 0): void {
        global $DB;
        $DB->insert_record('local_forumia_config', (object) [
            'forumid'        => $this->forum->id,
            'enabled'        => 1,
            'bot_userid'     => $this->bot->id,
            'response_mode'  => 'immediate',
            'max_requests_day' => 100,
            'grading_mode'   => $mode,
            'grading_delay'  => 12,
            'grading_userid' => $grader,
            'timecreated'    => time(),
            'timemodified'   => time(),
        ]);
    }

    /**
     * Creates a discussion and returns its first post, backdated.
     *
     * @param  int    $userid  Author.
     * @param  string $message Text.
     * @param  int    $hoursago How old the post is.
     * @return \stdClass
     */
    private function discussion(int $userid, string $message, int $hoursago): \stdClass {
        global $DB;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id, 'forum' => $this->forum->id, 'userid' => $userid, 'message' => $message,
        ]);
        $post = $DB->get_record('forum_posts', ['discussion' => $discussion->id], '*', MUST_EXIST);
        $DB->set_field('forum_posts', 'created', time() - $hoursago * HOURSECS, ['id' => $post->id]);
        return $DB->get_record('forum_posts', ['id' => $post->id]);
    }

    /**
     * Creates a reply, backdated.
     *
     * @param  \stdClass $parent   Post being answered.
     * @param  int       $userid   Author.
     * @param  string    $message  Text.
     * @param  int       $hoursago How old the post is.
     * @return \stdClass
     */
    private function reply(\stdClass $parent, int $userid, string $message, int $hoursago): \stdClass {
        global $DB;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $parent->discussion, 'parent' => $parent->id, 'userid' => $userid, 'message' => $message,
        ]);
        $DB->set_field('forum_posts', 'created', time() - $hoursago * HOURSECS, ['id' => $post->id]);
        return $DB->get_record('forum_posts', ['id' => $post->id]);
    }

    /**
     * Returns the student's evaluation, if any.
     *
     * @param  int $userid Student.
     * @return \stdClass|false
     */
    private function evaluation(int $userid) {
        global $DB;
        return $DB->get_record(grade_suggestions::TABLE, ['forumid' => $this->forum->id, 'userid' => $userid]);
    }

    /**
     * Returns the student's forum grade, or null.
     *
     * @param  int $userid Student.
     * @return float|null
     */
    private function forum_grade(int $userid): ?float {
        global $DB;
        $grade = $DB->get_field('forum_grades', 'grade', ['forum' => $this->forum->id, 'itemnumber' => 1, 'userid' => $userid]);
        return ($grade === false || $grade === null) ? null : (float) $grade;
    }

    /**
     * Nothing happens before the delay has passed.
     */
    public function test_student_is_not_evaluated_before_the_delay(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $this->discussion($this->student->id, 'My first idea.', 2);

        forum_processor::process_grading();

        $this->assertCount(0, $this->client->calls);
        $this->assertFalse($this->evaluation($this->student->id));
    }

    /**
     * One evaluation, with every post and the classmate's message as context.
     */
    public function test_one_evaluation_with_full_context(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $classmatepost = $this->discussion($this->classmate->id, 'Classmate opinion about topic X.', 1);
        $this->discussion($this->student->id, 'My original analysis.', 14);
        $this->reply($classmatepost, $this->student->id, 'I partly agree with you.', 13);

        forum_processor::process_grading();

        $this->assertCount(1, $this->client->calls, 'Only the student is due; the classmate posted an hour ago.');
        $call = $this->client->calls[0];
        $this->assertTrue($call['json']);
        $this->assertStringContainsString('ORIGINAL CONTRIBUTION', $call['user']);
        $this->assertStringContainsString('REPLY TO A CLASSMATE', $call['user']);
        $this->assertStringContainsString('Classmate opinion about topic X.', $call['user']);
        $this->assertStringContainsString('I partly agree with you.', $call['user']);
        $this->assertStringNotContainsString(fullname($this->classmate), $call['user']);

        $evaluation = $this->evaluation($this->student->id);
        $this->assertEquals(7, $evaluation->grade);
        $this->assertEquals(2, $evaluation->postcount);
        $this->assertEquals(grade_suggestions::STATUS_PENDING, $evaluation->status);
        $this->assertNull($this->forum_grade($this->student->id), 'Suggest mode never writes a grade.');
    }

    /**
     * Later posts never produce a new evaluation or a new provider call.
     */
    public function test_later_posts_do_not_trigger_a_new_evaluation(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $first = $this->discussion($this->student->id, 'First.', 14);
        forum_processor::process_grading();
        $this->assertCount(1, $this->client->calls);

        $this->reply($first, $this->student->id, 'Second.', 13);
        $this->discussion($this->student->id, 'Third.', 12);
        forum_processor::process_grading();
        forum_processor::process_grading();

        $this->assertCount(1, $this->client->calls);
        $this->assertEquals(1, $this->evaluation($this->student->id)->postcount);
    }

    /**
     * A discarded evaluation is not regenerated either.
     */
    public function test_discarded_evaluation_is_not_regenerated(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $this->discussion($this->student->id, 'First.', 14);
        forum_processor::process_grading();
        grade_suggestions::discard((int) $this->evaluation($this->student->id)->id);

        forum_processor::process_grading();

        $this->assertCount(1, $this->client->calls);
    }

    /**
     * An unusable AI answer is stored as failed and never retried.
     */
    public function test_invalid_answer_is_failed_and_not_retried(): void {
        $this->configure(grade_suggestions::MODE_AUTO, (int) $this->teacher->id);
        $this->client->response = 'I would give this student a grade: 9 out of 10.';
        $this->discussion($this->student->id, 'First.', 14);

        forum_processor::process_grading();
        forum_processor::process_grading();

        $this->assertCount(1, $this->client->calls);
        $this->assertEquals(grade_suggestions::STATUS_FAILED, $this->evaluation($this->student->id)->status);
        $this->assertNull($this->forum_grade($this->student->id));
    }

    /**
     * A provider failure writes nothing, so the student is retried later.
     */
    public function test_provider_failure_is_retried(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $this->client->response = null;
        $this->discussion($this->student->id, 'First.', 14);

        forum_processor::process_grading();
        $this->assertFalse($this->evaluation($this->student->id));

        $this->client->response = '{"grade": 6, "rationale": "Ok."}';
        forum_processor::process_grading();
        $this->assertEquals(6, $this->evaluation($this->student->id)->grade);
    }

    /**
     * Automatic mode applies the grade, recorded under the enabling teacher.
     */
    public function test_automatic_mode_applies_the_grade_as_the_teacher(): void {
        $this->configure(grade_suggestions::MODE_AUTO, (int) $this->teacher->id);
        $this->discussion($this->student->id, 'First.', 14);

        forum_processor::process_grading();

        $this->assertEquals(7.0, $this->forum_grade($this->student->id));
        $this->assertEquals(grade_suggestions::STATUS_APPLIED, $this->evaluation($this->student->id)->status);

        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'forum', 'iteminstance' => $this->forum->id,
            'itemnumber' => 1, 'courseid' => $this->course->id,
        ]);
        $gradebook = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $this->student->id]);
        $this->assertEquals(7.0, (float) $gradebook->finalgrade);
        $this->assertEquals($this->teacher->id, $gradebook->usermodified);
    }

    /**
     * Automatic mode never touches a student who already has a grade.
     */
    public function test_automatic_mode_never_overwrites_an_existing_grade(): void {
        $this->configure(grade_suggestions::MODE_AUTO, (int) $this->teacher->id);
        $this->discussion($this->student->id, 'First.', 14);

        $this->setUser($this->teacher);
        grade_suggestions::get_gradeitem($this->forum)->store_grade_from_formdata(
            \core_user::get_user($this->student->id),
            $this->teacher,
            (object) ['grade' => 3]
        );
        $this->setAdminUser();

        forum_processor::process_grading();

        $this->assertCount(0, $this->client->calls, 'No provider call for a student who is already graded.');
        $this->assertEquals(3.0, $this->forum_grade($this->student->id));
    }

    /**
     * Without a valid grader, automatic mode leaves the grade for review.
     */
    public function test_automatic_mode_without_grader_leaves_it_pending(): void {
        global $DB;

        $this->configure(grade_suggestions::MODE_AUTO, (int) $this->teacher->id);
        $DB->set_field('user', 'suspended', 1, ['id' => $this->teacher->id]);
        $this->discussion($this->student->id, 'First.', 14);

        forum_processor::process_grading();

        $this->assertNull($this->forum_grade($this->student->id));
        $this->assertEquals(grade_suggestions::STATUS_PENDING, $this->evaluation($this->student->id)->status);
    }

    /**
     * Teachers and the assistant account are never evaluated.
     */
    public function test_staff_and_assistant_are_never_evaluated(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $this->discussion($this->teacher->id, 'Welcome.', 20);
        $this->discussion($this->bot->id, 'AI note.', 20);

        forum_processor::process_grading();

        $this->assertCount(0, $this->client->calls);
    }

    /**
     * Accept all applies every pending suggestion under the teacher.
     */
    public function test_accept_all(): void {
        $this->configure(grade_suggestions::MODE_SUGGEST);
        $this->discussion($this->student->id, 'First.', 14);
        $this->discussion($this->classmate->id, 'Mine.', 14);
        forum_processor::process_grading();

        $this->setUser($this->teacher);
        [$applied, $failed] = grade_suggestions::accept_all((int) $this->forum->id, $this->teacher);

        $this->assertSame([2, 0], [$applied, $failed]);
        $this->assertEquals(7.0, $this->forum_grade($this->student->id));
        $this->assertEquals(7.0, $this->forum_grade($this->classmate->id));
        $this->assertEmpty(grade_suggestions::get_pending((int) $this->forum->id));
    }

    /**
     * Grades are only read from a well-formed JSON object, within range.
     *
     * @dataProvider grading_response_provider
     * @param string   $raw      Raw provider output.
     * @param int|null $expected Expected grade.
     */
    public function test_grading_response_parsing_is_strict(string $raw, ?int $expected): void {
        [$grade] = forum_processor::parse_grading_response($raw, 10);
        $this->assertSame($expected, $grade);
    }

    /**
     * Cases for {@see test_grading_response_parsing_is_strict()}.
     *
     * @return array
     */
    public static function grading_response_provider(): array {
        $fence = str_repeat(chr(96), 3);
        return [
            'valid object'       => ['{"grade": 7, "rationale": "Good."}', 7],
            'fenced object'      => [$fence . "json\n" . '{"grade": 8, "rationale": "Fine."}' . "\n" . $fence, 8],
            'prose with a grade' => ['Nice work. "grade": 9', null],
            'above maximum'      => ['{"grade": 11, "rationale": "Hm."}', null],
            'negative'           => ['{"grade": -1, "rationale": "Hm."}', null],
            'grade as text'      => ['{"grade": "10", "rationale": "Ok."}', null],
        ];
    }
}
