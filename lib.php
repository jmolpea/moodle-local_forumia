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
 * Library functions for local_forumia.
 *
 * Implements Moodle hooks for navigation and forum settings integration.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the forum module's settings navigation to add an "IA Assistant" link.
 *
 * This hook is called by Moodle when building the settings navigation tree for
 * any activity module. We intercept only forum instances and inject our link.
 *
 * @param  \settings_navigation $settingsnav The current settings navigation node.
 * @param  \context            $context     The current page context.
 * @return void
 */
function local_forumia_extend_settings_navigation(\settings_navigation $settingsnav, \context $context): void {
    global $PAGE, $DB;

    // Only act on forum module pages.
    if ($PAGE->cm === null || $PAGE->cm->modname !== 'forum') {
        return;
    }

    $forumnode = $settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING);
    if ($forumnode === false) {
        return;
    }

    $context    = \context_module::instance($PAGE->cm->id);
    $coursecontext = \context_course::instance($PAGE->cm->course);

    // Only show the link to users who can manage IA settings.
    if (!has_capability('local/forumia:managesettings', $coursecontext)) {
        return;
    }

    // Retrieve the forum record to get its ID.
    $forum = $DB->get_record('forum', ['id' => $PAGE->cm->instance]);
    if (!$forum) {
        return;
    }

    $url = new \moodle_url('/local/forumia/forum_settings.php', [
        'forumid' => $forum->id,
        'cmid'    => $PAGE->cm->id,
    ]);

    $forumnode->add(
        get_string('forum_settings_link', 'local_forumia'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_forumia_settings',
        new \pix_icon('i/settings', '')
    );
}
