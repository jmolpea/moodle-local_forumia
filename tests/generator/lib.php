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
 * Data generator for local_forumia.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator for local_forumia.
 */
class local_forumia_generator extends component_generator_base {
    /**
     * Creates an AI evaluation of a student's participation.
     *
     * @param  array $record forumid, userid, grade (empty for a failed evaluation),
     *                       and optionally grademax, rationale and postcount.
     * @return stdClass
     */
    public function create_evaluation(array $record): stdClass {
        $grade = (isset($record['grade']) && $record['grade'] !== '') ? (int) $record['grade'] : null;
        return \local_forumia\grade_suggestions::record(
            (int) $record['forumid'],
            (int) $record['userid'],
            $grade,
            (int) ($record['grademax'] ?? 10),
            (string) ($record['rationale'] ?? ''),
            (int) ($record['postcount'] ?? 1)
        );
    }
}
