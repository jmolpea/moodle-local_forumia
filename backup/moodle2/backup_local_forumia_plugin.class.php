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
 * Backup plugin for local_forumia.
 *
 * Hooks into the mod_forum backup process and serialises the per-forum
 * IA assistant configuration stored in {local_forumia_config}.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup plugin class for local_forumia.
 */
class backup_local_forumia_plugin extends backup_local_plugin {
    /**
     * Tells the backup API that this plugin hooks into the 'forum' module.
     *
     * @return string
     */
    protected function get_activity_name() {
        return 'forum';
    }

    /**
     * Returns the backup structure for the local_forumia configuration.
     *
     * The element is appended as a child of the forum module backup tree.
     * The bot_userid FK is annotated so Moodle can map it to the correct
     * user when the backup is restored on a different site.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {

        $plugin = $this->get_plugin_element(null, null, null);

        // The optigroup element is transparent (no XML tag). We need an explicit
        // named wrapper so that restore_plugin::get_pathfor() can locate the data.
        // get_recommended_name() returns 'plugin_local_forumia_module', which is
        // exactly what get_pathfor('/forumia_config') expects in the restore class.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // IMPORTANT: this list must mirror every column of local_forumia_config
        // except the primary key and forumid. A column added to db/install.xml
        // and forgotten here is lost silently on every backup — the restore
        // succeeds and quietly writes the column default instead, so nobody
        // notices until a teacher's tuned prompts vanish in a course rollover.
        $config = new backup_nested_element('forumia_config', ['id'], [
            'enabled',
            'bot_userid',
            'response_mode',
            'daily_prompt',
            'immediate_prompt',
            'disclaimer',
            'max_requests_day',
            'max_requests_user_day',
            'delay_response',
            'grading_prompt',
            'inactivity_enabled',
            'inactivity_days',
            'inactivity_repeat_days',
            'inactivity_prompt',
            'inactivity_deadline',
            'last_inactivity_post',
            'timecreated',
            'timemodified',
        ]);

        $pluginwrapper->add_child($config);

        // Source: row in local_forumia_config whose forumid matches this forum.
        $config->set_source_table('local_forumia_config', ['forumid' => backup::VAR_ACTIVITYID]);

        // Annotate the bot user so Moodle can remap it on restore.
        $config->annotate_ids('user', 'bot_userid');

        return $plugin;
    }
}
