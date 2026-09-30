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
 * AI grade evaluations for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

use core_grades\component_gradeitem;

/**
 * Stores AI evaluations of a student's forum participation and applies them.
 *
 * Every student is evaluated at most once per forum: the table has a unique
 * index on (forumid, userid) and rows are never deleted by the workflow, only
 * moved between states. That is what stops later posts from producing a new
 * draft grade each time.
 *
 * Grades only ever reach mod_forum through its own grading API
 * (component_gradeitem), so forum_grades and the gradebook stay consistent and
 * the gradebook records a real teacher as the grader:
 * - suggest mode: the teacher who accepts the suggestion;
 * - automatic mode: the teacher who switched automatic mode on, and only for a
 *   student who has no grade yet. An existing grade is never overwritten.
 */
class grade_suggestions {
    /** @var string Table holding the evaluations. */
    public const TABLE = 'local_forumia_suggestion';

    /** @var int Waiting for a teacher. */
    public const STATUS_PENDING = 0;
    /** @var int Accepted by a teacher. */
    public const STATUS_ACCEPTED = 1;
    /** @var int Discarded by a teacher. */
    public const STATUS_DISCARDED = 2;
    /** @var int Applied automatically. */
    public const STATUS_APPLIED = 3;
    /** @var int The AI returned no valid grade; the teacher grades manually. */
    public const STATUS_FAILED = 4;

    /** @var int Grading mode: off. */
    public const MODE_OFF = 0;
    /** @var int Grading mode: suggestions a teacher confirms. */
    public const MODE_SUGGEST = 1;
    /** @var int Grading mode: applied automatically to students without a grade. */
    public const MODE_AUTO = 2;

    /**
     * Returns the whole-forum grade item for a forum.
     *
     * @param  \stdClass $forum Forum record.
     * @return component_gradeitem
     */
    public static function get_gradeitem(\stdClass $forum): component_gradeitem {
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        return component_gradeitem::instance('mod_forum', \context_module::instance($cm->id), 'forum');
    }

    /**
     * Returns true when AI grading can work in this forum.
     *
     * Requires whole-forum grading with a point maximum and simple direct
     * grading: a scale or an advanced grading method (rubric, marking guide)
     * cannot be filled in from a single number.
     *
     * @param  \stdClass $forum Forum record.
     * @return bool
     */
    public static function forum_supports_ai_grading(\stdClass $forum): bool {
        if ((int) ($forum->grade_forum ?? 0) <= 0) {
            return false;
        }
        return !self::get_gradeitem($forum)->is_using_advanced_grading();
    }

    /**
     * Returns true if the student has already been evaluated in this forum.
     *
     * @param  int $forumid Forum ID.
     * @param  int $userid  Student ID.
     * @return bool
     */
    public static function is_evaluated(int $forumid, int $userid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['forumid' => $forumid, 'userid' => $userid]);
    }

    /**
     * Records an evaluation. A second evaluation of the same student is refused.
     *
     * @param  int         $forumid   Forum ID.
     * @param  int         $userid    Student ID.
     * @param  int|null    $grade     Grade proposed by the AI, or null if none was valid.
     * @param  int         $grademax  Forum maximum grade at evaluation time.
     * @param  string      $rationale Justification for the teacher.
     * @param  int         $postcount Number of posts assessed.
     * @return \stdClass|null         The new record, or null if the student was already evaluated.
     */
    public static function record(
        int $forumid,
        int $userid,
        ?int $grade,
        int $grademax,
        string $rationale,
        int $postcount
    ): ?\stdClass {
        global $DB;

        if (self::is_evaluated($forumid, $userid)) {
            return null;
        }
        $now    = time();
        $record = (object) [
            'forumid'      => $forumid,
            'userid'       => $userid,
            'grade'        => $grade,
            'grademax'     => $grademax,
            'rationale'    => $rationale,
            'postcount'    => $postcount,
            'status'       => $grade === null ? self::STATUS_FAILED : self::STATUS_PENDING,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        try {
            $record->id = $DB->insert_record(self::TABLE, $record);
        } catch (\dml_exception $e) {
            // The unique index caught a concurrent evaluation of the same student.
            return null;
        }
        return $record;
    }

    /**
     * Returns the evaluations a teacher still has to act on, oldest first.
     *
     * @param  int $forumid Forum ID.
     * @return \stdClass[]
     */
    public static function get_for_review(int $forumid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal([self::STATUS_PENDING, self::STATUS_FAILED], SQL_PARAMS_NAMED);
        $params['forumid'] = $forumid;
        return $DB->get_records_select(self::TABLE, "forumid = :forumid AND status $insql", $params, 'timecreated ASC, id ASC');
    }

    /**
     * Returns the pending (gradeable) evaluations of a forum.
     *
     * @param  int $forumid Forum ID.
     * @return \stdClass[]
     */
    public static function get_pending(int $forumid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['forumid' => $forumid, 'status' => self::STATUS_PENDING], 'timecreated ASC, id ASC');
    }

    /**
     * Stores the grade through mod_forum's grading API, recording $grader.
     *
     * mod_forum pushes the grade to the gradebook with the current $USER as the
     * user who modified it. When the grader is not the current user (automatic
     * mode, running from cron) the session user is switched to the grader for
     * the duration of the call and always restored.
     *
     * @param  component_gradeitem $gradeitem  Forum grade item.
     * @param  \stdClass           $gradeduser Student.
     * @param  \stdClass           $grader     Teacher recorded as grader.
     * @param  int                 $grade      Grade to store.
     * @return void
     */
    private static function store_grade(
        component_gradeitem $gradeitem,
        \stdClass $gradeduser,
        \stdClass $grader,
        int $grade
    ): void {
        global $USER;

        $formdata = (object) ['grade' => (float) $grade];
        if ((int) $USER->id === (int) $grader->id) {
            $gradeitem->store_grade_from_formdata($gradeduser, $grader, $formdata);
            return;
        }
        $realuser = $USER;
        try {
            \core\session\manager::set_user($grader);
            $gradeitem->store_grade_from_formdata($gradeduser, $grader, $formdata);
        } finally {
            \core\session\manager::set_user($realuser);
        }
    }

    /**
     * Checks that an evaluation can still be applied in its forum.
     *
     * @param  \stdClass           $evaluation Evaluation record.
     * @param  \stdClass           $forum      Forum record.
     * @param  component_gradeitem $gradeitem  Forum grade item.
     * @return void
     * @throws \moodle_exception If it cannot.
     */
    private static function require_applicable(\stdClass $evaluation, \stdClass $forum, component_gradeitem $gradeitem): void {
        if ((int) $evaluation->status !== self::STATUS_PENDING || $evaluation->grade === null) {
            throw new \moodle_exception('suggestions_error_notpending', 'local_forumia');
        }
        if ($gradeitem->is_using_advanced_grading()) {
            throw new \moodle_exception('suggestions_error_advanced', 'local_forumia');
        }
        $grademax = (int) ($forum->grade_forum ?? 0);
        if ($grademax <= 0 || $evaluation->grade < 0 || $evaluation->grade > $grademax) {
            throw new \moodle_exception('suggestions_error_invalid', 'local_forumia');
        }
    }

    /**
     * Applies a pending evaluation as the student's whole-forum grade.
     *
     * An explicit teacher action, so it may replace an existing grade: the
     * review page shows the current grade next to the suggestion.
     *
     * @param  int       $evaluationid Evaluation ID.
     * @param  \stdClass $grader       The teacher applying the grade (normally $USER).
     * @return void
     * @throws \moodle_exception If the grade cannot be applied.
     */
    public static function accept(int $evaluationid, \stdClass $grader): void {
        global $DB;

        $evaluation = $DB->get_record(self::TABLE, ['id' => $evaluationid], '*', MUST_EXIST);
        $forum      = $DB->get_record('forum', ['id' => $evaluation->forumid], '*', MUST_EXIST);
        $gradeduser = \core_user::get_user($evaluation->userid, '*', MUST_EXIST);
        $gradeitem  = self::get_gradeitem($forum);

        $gradeitem->require_user_can_grade($gradeduser, $grader);
        self::require_applicable($evaluation, $forum, $gradeitem);

        self::store_grade($gradeitem, $gradeduser, $grader, (int) $evaluation->grade);
        self::set_status($evaluation, self::STATUS_ACCEPTED);
    }

    /**
     * Accepts every pending evaluation of a forum.
     *
     * @param  int       $forumid Forum ID.
     * @param  \stdClass $grader  The teacher applying the grades.
     * @return int[]              [number applied, number that could not be applied].
     */
    public static function accept_all(int $forumid, \stdClass $grader): array {
        $applied = 0;
        $failed  = 0;
        foreach (self::get_pending($forumid) as $evaluation) {
            try {
                self::accept((int) $evaluation->id, $grader);
                $applied++;
            } catch (\moodle_exception $e) {
                $failed++;
            }
        }
        return [$applied, $failed];
    }

    /**
     * Applies an evaluation automatically, if and only if it is safe to.
     *
     * Conditions: the forum is in automatic mode, the teacher who enabled it is
     * still an active user allowed to grade this student, and the student has
     * no grade yet. Otherwise the evaluation simply stays pending for a teacher
     * to review, so nothing is lost and nothing is overwritten.
     *
     * @param  \stdClass $evaluation Evaluation record (pending).
     * @param  \stdClass $forum      Forum record.
     * @param  \stdClass $config     Forum configuration record.
     * @return bool                  True if the grade was applied.
     */
    public static function apply_automatically(\stdClass $evaluation, \stdClass $forum, \stdClass $config): bool {
        global $DB;

        if ((int) ($config->grading_mode ?? 0) !== self::MODE_AUTO || empty($config->grading_userid)) {
            return false;
        }
        $grader = $DB->get_record('user', ['id' => $config->grading_userid, 'deleted' => 0, 'suspended' => 0]);
        $student = \core_user::get_user($evaluation->userid);
        if (!$grader || !$student) {
            return false;
        }
        $gradeitem = self::get_gradeitem($forum);
        if (!$gradeitem->user_can_grade($student, $grader) || $gradeitem->user_has_grade($student)) {
            return false;
        }
        try {
            self::require_applicable($evaluation, $forum, $gradeitem);
            self::store_grade($gradeitem, $student, $grader, (int) $evaluation->grade);
        } catch (\moodle_exception $e) {
            return false;
        }
        self::set_status($evaluation, self::STATUS_APPLIED);
        return true;
    }

    /**
     * Discards an evaluation without changing any grade.
     *
     * The row is kept (as discarded) so the student is not evaluated again.
     *
     * @param  int $evaluationid Evaluation ID.
     * @return void
     */
    public static function discard(int $evaluationid): void {
        global $DB;
        $evaluation = $DB->get_record(self::TABLE, ['id' => $evaluationid], '*', MUST_EXIST);
        self::set_status($evaluation, self::STATUS_DISCARDED);
    }

    /**
     * Updates the status of an evaluation.
     *
     * @param  \stdClass $evaluation Evaluation record.
     * @param  int       $status     New status.
     * @return void
     */
    private static function set_status(\stdClass $evaluation, int $status): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id'           => $evaluation->id,
            'status'       => $status,
            'timemodified' => time(),
        ]);
    }
}
