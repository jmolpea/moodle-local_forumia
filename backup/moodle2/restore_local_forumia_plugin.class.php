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
 * Restore plugin for local_forumia.
 *
 * Reads the local_forumia configuration from a backup and inserts (or
 * updates) the corresponding row in {local_forumia_config} using the
 * new forum ID assigned during restore.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class for local_forumia.
 */
class restore_local_forumia_plugin extends restore_local_plugin {
    /**
     * Tells the restore API that this plugin hooks into the 'forum' module.
     *
     * @return string
     */
    protected function get_activity_name() {
        return 'forum';
    }

    /** @var stdClass|null Raw config data saved during parsing, written to DB in after_restore_module(). */
    private $forumiaconfig = null;

    /**
     * Returns the restore path elements for local_forumia.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element('forumia_config', $this->get_pathfor('/forumia_config')),
        ];
    }

    /**
     * Stores the forumia_config element for later processing.
     *
     * We cannot write to the DB here because this method is called during
     * restore_module_structure_step, which runs BEFORE the forum activity
     * step that creates the forum record and registers its ID mapping.
     * The actual DB work is deferred to after_restore_module().
     *
     * @param array|object $data Raw data from the backup XML.
     */
    public function process_forumia_config($data) {
        $this->forumiaconfig = (object)$data;
    }

    /**
     * Writes the stored forumia_config to the database.
     *
     * Called after all restore steps for this activity have completed,
     * so the forum record exists and get_task()->get_activityid() returns
     * its new instance ID.
     */
    public function after_restore_module() {
        global $DB;

        if ($this->forumiaconfig === null) {
            return; // Nothing to restore (forum had no IA config in the backup).
        }

        $data = clone $this->forumiaconfig;

        // The forum has been fully restored by now; get the new instance ID.
        $data->forumid = $this->get_task()->get_activityid();
        if (!$data->forumid) {
            return;
        }

        // Remap the bot user; disable the assistant if the user does not exist
        // in the target site to avoid broken API calls.
        $newbotuserid = $this->get_mappingid('user', $data->bot_userid);
        if ($newbotuserid) {
            $data->bot_userid = $newbotuserid;
        } else {
            $data->bot_userid = 0;
            $data->enabled   = 0;
        }

        // Reset the observability timestamp: it records when the inactivity
        // task last ran against the SOURCE forum. Carrying it over would make
        // the restored forum look as though it had already been processed, and
        // suppress its first reactivation run.
        $data->last_inactivity_post = 0;

        // Remove the original PK — the DB will assign a new one.
        unset($data->id);

        // Upsert: update if a config row already exists for this forum
        // (e.g. the forum was restored into an existing course slot).
        if ($existing = $DB->get_record('local_forumia_config', ['forumid' => $data->forumid])) {
            $data->id = $existing->id;
            $DB->update_record('local_forumia_config', $data);
        } else {
            $DB->insert_record('local_forumia_config', $data);
        }
    }
}
