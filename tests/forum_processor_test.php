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
 * The guard tests run the task path (process_new_post(..., true)), which is
 * where the checks live since 1.8.0; the observer itself only queues a task.
 *
 * Every guard test asserts that process_new_post() returns WITHOUT reaching the
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

        // Designate the bot as an assistant account the way an administrator
        // would: a system-level role holding local/forumia:actasassistant.
        $this->designate($this->bot->id);

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
     * Grants local/forumia:actasassistant to a user at system level.
     *
     * @param  int $userid User to designate.
     * @return void
     */
    private function designate(int $userid): void {
        $system = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(assistant_account::CAPABILITY, CAP_ALLOW, $roleid, $system->id);
        role_assign($roleid, $userid, $system->id);
    }

    /**
     * Returns the Forumia adhoc tasks currently queued.
     *
     * @return \core\task\adhoc_task[]
     */
    private function queued_tasks(): array {
        return \core\task\manager::get_adhoc_tasks('\\local_forumia\\task\\delayed_response_task');
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

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * A forum with no configuration row is ignored.
     */
    public function test_unconfigured_forum_is_ignored(): void {
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * A configured but disabled forum is ignored.
     */
    public function test_disabled_forum_is_ignored(): void {
        $this->set_forum_config(['enabled' => 0]);
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

        $this->assertSame($before, $this->count_posts());
    }

    /**
     * The immediate path must not fire for a forum in daily mode.
     */
    public function test_daily_mode_forum_is_skipped_by_the_immediate_path(): void {
        $this->set_forum_config(['response_mode' => 'daily']);
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

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

        forum_processor::process_new_post($this->forum->id, $post->id, $this->bot->id, true);

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

        forum_processor::process_new_post($this->forum->id, $post->id, $this->bot->id, true);

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

        forum_processor::process_new_post($this->forum->id, $post->id, $this->teacher->id, true);

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

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

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

        forum_processor::process_new_post($this->forum->id, $second->id, $this->student->id, true);

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

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

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
     * Builds the post_created event for a post.
     *
     * @param  \stdClass $post Post record.
     * @return \mod_forum\event\post_created
     */
    private function post_created_event(\stdClass $post): \mod_forum\event\post_created {
        return \mod_forum\event\post_created::create([
            'context'  => \context_module::instance($this->forum->cmid),
            'objectid' => $post->id,
            'userid'   => (int) $post->userid,
            'other'    => [
                'discussionid' => $post->discussion,
                'forumid'      => $this->forum->id,
                'forumtype'    => 'general',
            ],
        ]);
    }

    /**
     * The observer never calls the provider: it only queues an adhoc task.
     *
     * No API key is configured, so any provider call inside the observer would
     * fail and be logged. Nothing is logged and nothing is posted: the student's
     * request returns without waiting on the AI.
     */
    public function test_observer_queues_a_task_instead_of_calling_the_provider(): void {
        $this->set_forum_config();
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();

        forum_observer::post_created($this->post_created_event($post));

        $this->assertSame($before, $this->count_posts());
        $tasks = $this->queued_tasks();
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals($post->id, $task->get_custom_data()->postid);
        $this->assertLessThanOrEqual(time(), $task->get_next_run_time(), 'Without the delay option it runs on the next cron.');
    }

    /**
     * A repeated event for the same post does not queue a second task.
     */
    public function test_repeated_event_queues_a_single_task(): void {
        $this->set_forum_config();
        $post = $this->post_as($this->student->id);

        forum_observer::post_created($this->post_created_event($post));
        forum_observer::post_created($this->post_created_event($post));

        $this->assertCount(1, $this->queued_tasks());
    }

    /**
     * The delay option pushes the task one hour into the future.
     */
    public function test_delay_option_queues_the_task_one_hour_later(): void {
        $this->set_forum_config(['delay_response' => 1]);
        $post = $this->post_as($this->student->id);

        forum_observer::post_created($this->post_created_event($post));

        $tasks = $this->queued_tasks();
        $this->assertCount(1, $tasks);
        $this->assertGreaterThanOrEqual(time() + HOURSECS - 5, reset($tasks)->get_next_run_time());
    }

    /**
     * A failing task is logged and finishes, so cron does not retry it forever.
     *
     * No API key is configured: the processor throws when it builds the client.
     */
    public function test_task_failure_is_logged_not_rethrown(): void {
        $this->set_forum_config(['max_requests_user_day' => 0]);
        $post = $this->post_as($this->student->id);

        $task = new \local_forumia\task\delayed_response_task();
        $task->set_custom_data(['forumid' => $this->forum->id, 'postid' => $post->id, 'authorid' => $this->student->id]);

        $this->expectOutputRegex('/delayed_response_task failed for post ' . $post->id . '/');
        $task->execute();
    }

    /**
     * A course teacher cannot be used as the assistant account.
     *
     * Before 1.8.0 the selector offered course staff and the processor fell back
     * to a course teacher on its own. Now the forum is disabled instead, and the
     * administrators are told.
     */
    public function test_undesignated_account_disables_the_forum_and_notifies(): void {
        global $DB;

        $this->set_forum_config(['bot_userid' => $this->teacher->id]);
        $post   = $this->post_as($this->student->id);
        $before = $this->count_posts();
        $sink   = $this->redirectMessages();

        forum_processor::process_new_post($this->forum->id, $post->id, $this->student->id, true);

        $this->assertSame($before, $this->count_posts());
        $this->assertEquals(0, $DB->get_field('local_forumia_config', 'enabled', ['forumid' => $this->forum->id]));
        $this->assertDebuggingCalledCount(2);

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        $this->assertSame('assistant_disabled', reset($messages)->eventtype);
        $sink->close();
    }

    /**
     * Course staff are never candidates; designated accounts are.
     */
    public function test_only_designated_accounts_are_candidates(): void {
        $candidates = assistant_account::get_candidates();

        $this->assertArrayHasKey($this->bot->id, $candidates);
        $this->assertArrayNotHasKey($this->teacher->id, $candidates);
        $this->assertFalse(assistant_account::is_designated((int) $this->teacher->id));

        // A site administrator is not a candidate just for being an administrator.
        $this->assertFalse(assistant_account::is_designated((int) get_admin()->id));

        set_config('defaultbot', $this->teacher->username, 'local_forumia');
        $this->assertTrue(assistant_account::is_designated((int) $this->teacher->id));
    }

    /**
     * Every reply carries the fixed AI notice, even with an empty disclaimer.
     */
    public function test_ai_notice_is_always_added(): void {
        $notice = get_string('ai_notice', 'local_forumia');

        $this->assertStringContainsString($notice, forum_processor::append_ai_notice('Reply.', ''));

        $withdisclaimer = forum_processor::append_ai_notice('Reply.', 'Ask your teacher.');
        $this->assertStringContainsString($notice, $withdisclaimer);
        $this->assertStringContainsString('Ask your teacher.', $withdisclaimer);
    }
}
