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
 * Scheduled task: AI evaluation of forum participation for local_forumia.
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
 * Evaluates each student's forum participation once, after the configured delay.
 *
 * Runs hourly. A student already evaluated in a forum is never picked again,
 * so running it more often only makes evaluations happen sooner; it never
 * multiplies them.
 */
class grading_task extends scheduled_task {
    /**
     * Returns the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_grading_name', 'local_forumia');
    }

    /**
     * Executes the grading task.
     *
     * @return void
     */
    public function execute(): void {
        if (ai_client_base::is_globally_disabled()) {
            mtrace('[local_forumia] Plugin is globally disabled. Skipping grading task.');
            return;
        }

        if (ai_client_base::is_rate_limited()) {
            mtrace('[local_forumia] Provider rate limit is active. Skipping grading task.');
            return;
        }

        mtrace('[local_forumia] Starting grading task.');
        forum_processor::process_grading();
        mtrace('[local_forumia] Grading task complete.');
    }
}
