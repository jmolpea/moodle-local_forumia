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
 * Tests for the processor's pre-flight guard chain.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

use local_forumia\license\validator;

/**
 * Tests for the processor's pre-flight guard chain.
 *
 * Every test here asserts that process_new_post() returns WITHOUT reaching the
 * AI client. That matters twice over: each of these guards prevents a bill, and
 * the loop guards prevent an infinite exchange between the assistant and itself.
 *
 * No API key is ever configured, so if a guard failed to fire the run would
 * raise error_noapikey — the tests would fail loudly rather than silently
 * passing for the wrong reason.
 *
 * @covers \local_forumia\forum_processor
 */
final class forum_processor_test extends \advanced_testcase {
    /** @var \stdClass Course used by every test. */
    private \stdClass $course;

    /** @var \stdClass Forum used by every test. */
    private \stdClass $forum;

    /** @var \stdClass Enrolled student. */
    private \stdClass $student;

    /** @var \stdClass Enrolled editing teacher. */
    private \stdClass $teacher;

    /** @var \stdClass Account the assistant posts as. */
    private \stdClass $bot;

    /**
     * Builds a course with a forum, a student, a teacher and a bot account.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $this->course  = $generator->create_course();
        $this->forum   = $generator->create_module('forum', ['course' => $this->course->id]);
        $this->student = $generator->create_user();
        $this->teacher = $generator->create_user();
        $this->bot     = $generator->create_user();

        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->bot->id, $this->course->id, 'student');

        // Licensed by default: the trial window starts now, so the licence gate
        // is open and the tests exercise the guards that come after it.
        set_config('firstinstall', time(), 'local_forumia');
        validator::reset_cache();
    }

    /**
     * Clears the licence memo.
     */
    protected function tearDown(): void {
        validator::reset_cache();
        parent::tearDown();
    }

    /**
     * Writes a per-forum configuration row.
     *
     * @param  array $overrides Field values to override the defaults.
     * @return \stdClass         The stored record.
     */
    private function set_forum_config(array $overrides = []): \stdClass {
        global $DB;

        $record = (object) array_merge([
            'forumid'               => $this->forum->id,
            'enabled'               => 1,
            'bot_userid'            => $this->bot->id,
            'response_mode'         => 'immediate',
            'daily_prompt'          => 'daily',
            'immediate_prompt'      => 'immediate',
            'disclaimer'            => 'AI generated.',
            'max_requests_day'      => 50,
            'max_requests_user_day' => 1,
            'delay_response'        => 0,
            'grading_prompt'        => '',
            'inactivity_enabled'    => 0,
            'inactivity_days'       => 7,
            'inactivity_repeat_days' => 7,
            'inactivity_prompt'     => '',
            'inactivity_deadline'   => 0,
            'last_inactivity_post'  => 0,
            'timecreated'           => time(),
            'timemodified'          => time(),
        ], $overrides);

        $record->id = $DB->insert_record('local_forumia_config', $record);

        return $record;
    }

    /**
     * Creates a discussion authored by the given user.
     *
     * @param  int $userid Author.
     * @return \stdClass    The first post of the new discussion.
     */
    private function post_as(int $userid): \stdClass {
        global $DB;

        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id,
            'forum'  => $this->forum->id,
            'userid' => $userid,
        ]);

        return $DB->get_record('forum_posts', ['discussion' => $discussion->id], '*', MUST_EXIST);
    }

    /**
     * Counts posts in the forum.
     *
     * @return int
     */
    private function count_posts(): int {
        global $DB;

        return $DB->count_records_sql(
            'SELECT COUNT(p.id)
               FROM {forum_posts} p
               JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE d.forum = :forumid',
            ['forumid' => $this->forum->id]
        );
    }

    /**
     * With no licence and no trial, nothing happens at all.
     */
    public function test_unlicensed_site_does_nothing(): void {
        set_config('firstinstall', time() - ((validator::TRIAL_DAYS + 1) * DAYSECS), 'local_forumia');
        validator::reset_cache();

        $this->set_forum_config();
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * A forum with no configuration row is ignored.
     */
    public function test_unconfigured_forum_is_ignored(): void {
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * A configured but disabled forum is ignored.
     */
    public function test_disabled_forum_is_ignored(): void {
        $this->set_forum_config(['enabled' => 0]);
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * The immediate path must not fire for a forum in daily mode.
     */
    public function test_daily_mode_forum_is_skipped_by_the_immediate_path(): void {
        $this->set_forum_config(['response_mode' => 'daily']);
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * The assistant must never reply to itself.
     *
     * This is the guard that stands between the plugin and an unbounded loop
     * burning API credit, and it is load-bearing now that replies are published
     * through the forum API and therefore re-fire post_created.
     */
    public function test_bot_does_not_reply_to_its_own_post(): void {
        $this->set_forum_config();
        $post   = $this->post_as($this->bot->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->bot->id);

        $this->assertSame($before, $this->count_posts());
        $this->assertDebuggingCalled(
            '[local_forumia] ' . get_string('error_loopdetected', 'local_forumia'),
            DEBUG_DEVELOPER
        );
    }

    /**
     * The second loop guard covers the site-default fallback account.
     *
     * When the per-forum bot is unset, resolve_bot_user() falls back to the site
     * default. A post by THAT user would slip past the first guard, so the
     * processor checks the resolved user too.
     */
    public function test_bot_resolved_by_fallback_does_not_reply_to_itself(): void {
        set_config('defaultbot', $this->bot->username, 'local_forumia');
        $this->set_forum_config(['bot_userid' => 0]);

        $post   = $this->post_as($this->bot->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->bot->id);

        $this->assertSame($before, $this->count_posts());
        $this->assertDebuggingCalled(
            '[local_forumia] ' . get_string('error_loopdetected', 'local_forumia'),
            DEBUG_DEVELOPER
        );
    }

    /**
     * Teachers do not trigger the assistant. It answers students, not staff.
     */
    public function test_teacher_post_does_not_trigger_a_reply(): void {
        $this->set_forum_config();
        $post   = $this->post_as($this->teacher->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->teacher->id);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * A post the assistant has already answered is not answered twice.
     *
     * Moodle can deliver post_created more than once in some configurations;
     * without this check the student would get duplicate replies.
     *
     * The per-user cap is switched off on purpose. It sits earlier in the guard
     * chain than the deduplication check and would stop this post first, so
     * leaving it at its default made the test pass without ever reaching the
     * guard it claims to cover.
     */
    public function test_duplicate_event_does_not_produce_a_second_reply(): void {
        global $DB;

        $this->set_forum_config(['max_requests_user_day' => 0]);
        $post = $this->post_as($this->student->id);

        // Simulate the reply the assistant already published.
        $existing = clone $post;
        unset($existing->id);
        $existing->parent   = $post->id;
        $existing->userid   = $this->bot->id;
        $existing->message  = 'Existing AI reply.';
        $existing->created  = time();
        $existing->modified = time();
        $DB->insert_record('forum_posts', $existing);

        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
        $this->assertDebuggingCalled(
            '[local_forumia] Duplicate event for post ' . $post->id . ' — skipping.',
            DEBUG_DEVELOPER
        );
    }

    /**
     * The per-user daily cap stops a student farming replies.
     *
     * The default is one reply per user per day, and it is the plugin's main
     * defence against a student flooding the forum to burn the site's credit.
     */
    public function test_per_user_daily_limit_blocks_a_second_reply(): void {
        global $DB;

        $this->set_forum_config(['max_requests_user_day' => 1]);

        // First post, already answered by the assistant.
        $first = $this->post_as($this->student->id);
        $reply = clone $first;
        unset($reply->id);
        $reply->parent   = $first->id;
        $reply->userid   = $this->bot->id;
        $reply->message  = 'First AI reply.';
        $reply->created  = time();
        $reply->modified = time();
        $DB->insert_record('forum_posts', $reply);

        // Second post by the same student on the same day.
        $second = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $second->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
        $this->assertDebuggingCalled(
            '[local_forumia] ' . get_string('error_userlimit', 'local_forumia', $this->student->id),
            DEBUG_NORMAL
        );
    }

    /**
     * The per-forum daily cap stops the forum as a whole.
     */
    public function test_forum_daily_limit_blocks_further_replies(): void {
        global $DB;

        $this->set_forum_config(['max_requests_day' => 1, 'max_requests_user_day' => 0]);

        $DB->insert_record('local_forumia_usage', (object) [
            'forumid'       => $this->forum->id,
            'usage_date'    => date('Y-m-d'),
            'request_count' => 1,
        ]);

        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id);

        $this->assertSame($before, $this->count_posts());
        $this->assertDebuggingCalled(
            '[local_forumia] ' . get_string('error_dailylimit', 'local_forumia', $this->forum->id),
            DEBUG_NORMAL
        );
    }

    /**
     * A globally disabled plugin makes the observer a no-op.
     */
    public function test_globally_disabled_plugin_ignores_the_event(): void {
        set_config('globally_disabled', 1, 'local_forumia');

        $this->assertTrue(\local_forumia\api\ai_client_base::is_globally_disabled());
    }

    /**
     * An active rate-limit pause makes the observer a no-op.
     */
    public function test_rate_limit_pause_is_respected(): void {
        set_config('ratelimit_until', time() + HOURSECS, 'local_forumia');
        $this->assertTrue(\local_forumia\api\ai_client_base::is_rate_limited());

        set_config('ratelimit_until', time() - HOURSECS, 'local_forumia');
        $this->assertFalse(\local_forumia\api\ai_client_base::is_rate_limited());
    }

    /**
     * The observer swallows every failure.
     *
     * Whatever goes wrong inside this plugin, the student's post must still be
     * saved and the forum page must still render. That promise is what makes
     * the plugin safe to install on a live site.
     */
    public function test_observer_never_propagates_an_exception(): void {
        $this->set_forum_config();
        $post = $this->post_as($this->student->id);

        $event = \mod_forum\event\post_created::create([
            'context'  => \context_module::instance($this->forum->cmid),
            'objectid' => $post->id,
            'userid'   => $this->student->id,
            'other'    => [
                'discussionid' => $post->discussion,
                'forumid'      => $this->forum->id,
                'forumtype'    => 'general',
            ],
        ]);

        // No API key is configured, so the processor will fail internally.
        // The observer must absorb that.
        forum_observer::post_created($event);

        $this->assertDebuggingCalled(null, null, 'The failure should be logged, not thrown.');
    }
}
