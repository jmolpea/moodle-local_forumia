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
 * Behat data generator for local_forumia.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for local_forumia.
 */
class behat_local_forumia_generator extends behat_generator_base {
    /**
     * Entities this generator can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'evaluations' => [
                'singular'      => 'evaluation',
                'datagenerator' => 'evaluation',
                'required'      => ['forum', 'user'],
                'switchids'     => ['forum' => 'forumid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Returns a forum ID from its activity idnumber.
     *
     * @param  string $idnumber Course module idnumber.
     * @return int
     */
    protected function get_forum_id(string $idnumber): int {
        global $DB;
        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], 'instance', MUST_EXIST);
        return (int) $cm->instance;
    }
}
