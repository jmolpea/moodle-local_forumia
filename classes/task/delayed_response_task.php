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
 * Adhoc task that generates and publishes an immediate-mode AI response.
 *
 * Queued by forum_processor for every student post in an immediate-mode
 * forum, so the AI provider is always called from cron and never inside the
 * web request that saved the post. It runs on the next cron pass, or one hour
 * after the post when delay_response is enabled on the forum.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\task;

use local_forumia\forum_processor;

/**
 * Adhoc task for AI responses in immediate mode.
 */
class delayed_response_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name shown in the Moodle admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_delayed_response_name', 'local_forumia');
    }

    /**
     * Executes the delayed IA response.
     *
     * Reads forumid, postid and authorid from the custom data stored when
     * the task was queued, then delegates to forum_processor.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();

        if (empty($data->forumid) || empty($data->postid) || empty($data->authorid)) {
            // Defensive: malformed task data — nothing to do.
            mtrace('[local_forumia] delayed_response_task: missing custom data, skipping.');
            return;
        }

        // Failures here are configuration problems (no API key, blocked
        // endpoint, missing licence) that a retry would not fix, and provider
        // errors are already handled inside the client. Log and finish rather
        // than let the task manager retry the same post again and again.
        try {
            forum_processor::process_new_post(
                (int) $data->forumid,
                (int) $data->postid,
                (int) $data->authorid,
                true   // Running from the task: do the actual work.
            );
        } catch (\Throwable $e) {
            mtrace('[local_forumia] delayed_response_task failed for post ' . (int) $data->postid . ': ' . $e->getMessage());
        }
    }
}
