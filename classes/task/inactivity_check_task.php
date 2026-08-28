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
 * Scheduled task: forum inactivity check for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\task;

use core\task\scheduled_task;
use local_forumia\forum_processor;
use local_forumia\api\ai_client_base;

/**
 * Checks forums with the inactivity feature enabled and posts an AI-generated
 * discussion starter when a forum has been silent for the configured number
 * of days.
 *
 * Re-triggering is prevented by the last_inactivity_post timestamp stored per
 * forum, so running the task more than once per day is harmless.
 */
class inactivity_check_task extends scheduled_task {
    /**
     * Returns the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_inactivity_name', 'local_forumia');
    }

    /**
     * Executes the inactivity check task.
     *
     * @return void
     */
    public function execute(): void {
        if (ai_client_base::is_globally_disabled()) {
            mtrace('[local_forumia] Plugin is globally disabled. Skipping inactivity check task.');
            return;
        }

        if (ai_client_base::is_rate_limited()) {
            mtrace('[local_forumia] Provider rate limit is active. Skipping inactivity check task.');
            return;
        }

        mtrace('[local_forumia] Starting inactivity check task.');
        forum_processor::process_inactivity_checks();
        mtrace('[local_forumia] Inactivity check task complete.');
    }
}
