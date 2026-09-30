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
 * Tests for the AI evaluation store.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests for the AI evaluation store.
 *
 * The contract under test: a student has at most one evaluation per forum,
 * recording one never touches a grade, and an accepted one goes through
 * mod_forum's grading API with the accepting teacher in the gradebook.
 *
 * @covers \local_forumia\grade_suggestions
 */
final class grade_suggestions_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Forum with whole-forum grading out of 10. */
    private \stdClass $forum;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /** @var \stdClass Editing teacher. */
    private \stdClass $teacher;

    /**
     * Builds a graded forum.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator     = $this->getDataGenerator();
        $this->course  = $generator->create_course();
        $this->forum   = $generator->create_module('forum', ['course' => $this->course->id, 'grade_forum' => 10]);
        $this->student = $generator->create_user();
        $this->teacher = $generator->create_user();
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
    }

    /**
     * Returns the student's whole-forum grade stored by mod_forum, or null.
     *
     * @return float|null
     */
    private function forum_grade(): ?float {
        global $DB;
        $grade = $DB->get_field('forum_grades', 'grade', [
            'forum' => $this->forum->id, 'itemnumber' => 1, 'userid' => $this->student->id,
        ]);
        return ($grade === false || $grade === null) ? null : (float) $grade;
    }

    /**
     * Records an evaluation for the student.
     *
     * @param  int|null $grade Grade, or null for a failed evaluation.
     * @return \stdClass|null
     */
    private function record(?int $grade): ?\stdClass {
        return grade_suggestions::record((int) $this->forum->id, (int) $this->student->id, $grade, 10, 'Because.', 2);
    }

    /**
     * Recording an evaluation writes no grade anywhere.
     */
    public function test_recording_does_not_grade(): void {
        $this->record(8);

        $this->assertNull($this->forum_grade());
        $this->assertCount(1, grade_suggestions::get_pending((int) $this->forum->id));
    }

    /**
     * A student is never evaluated twice in the same forum.
     */
    public function test_second_evaluation_is_refused(): void {
        $this->assertNotNull($this->record(4));
        $this->assertNull($this->record(6));

        $pending = grade_suggestions::get_pending((int) $this->forum->id);
        $this->assertCount(1, $pending);
        $this->assertEquals(4, reset($pending)->grade);
    }

    /**
     * Accepting applies the grade through mod_forum, credited to the teacher.
     */
    public function test_accept_applies_the_grade_as_the_teacher(): void {
        $evaluation = $this->record(7);

        $this->setUser($this->teacher);
        grade_suggestions::accept((int) $evaluation->id, $this->teacher);

        $this->assertEquals(7.0, $this->forum_grade());
        $this->assertEmpty(grade_suggestions::get_pending((int) $this->forum->id));
        $this->assertTrue(grade_suggestions::is_evaluated((int) $this->forum->id, (int) $this->student->id));

        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'forum', 'iteminstance' => $this->forum->id,
            'itemnumber' => 1, 'courseid' => $this->course->id,
        ]);
        $gradebook = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $this->student->id]);
        $this->assertEquals(7.0, (float) $gradebook->finalgrade);
        $this->assertEquals($this->teacher->id, $gradebook->usermodified);
    }

    /**
     * Discarding changes nothing and keeps the student marked as evaluated.
     */
    public function test_discard_changes_nothing(): void {
        $evaluation = $this->record(2);

        grade_suggestions::discard((int) $evaluation->id);

        $this->assertNull($this->forum_grade());
        $this->assertEmpty(grade_suggestions::get_for_review((int) $this->forum->id));
        $this->assertTrue(grade_suggestions::is_evaluated((int) $this->forum->id, (int) $this->student->id));
    }

    /**
     * A failed evaluation is listed for review but cannot be accepted.
     */
    public function test_failed_evaluation_cannot_be_accepted(): void {
        $evaluation = $this->record(null);
        $this->assertEquals(grade_suggestions::STATUS_FAILED, $evaluation->status);
        $this->assertCount(1, grade_suggestions::get_for_review((int) $this->forum->id));

        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        grade_suggestions::accept((int) $evaluation->id, $this->teacher);
    }

    /**
     * A suggestion above a lowered maximum grade is refused.
     */
    public function test_suggestion_above_current_maximum_is_refused(): void {
        global $DB;

        $evaluation = $this->record(9);
        $DB->set_field('forum', 'grade_forum', 5, ['id' => $this->forum->id]);

        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        grade_suggestions::accept((int) $evaluation->id, $this->teacher);
    }

    /**
     * Forums without point-based whole-forum grading are not supported.
     */
    public function test_ungraded_forum_does_not_support_ai_grading(): void {
        $ungraded = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);

        $this->assertFalse(grade_suggestions::forum_supports_ai_grading($ungraded));
        $this->assertTrue(grade_suggestions::forum_supports_ai_grading($this->forum));
    }
}
