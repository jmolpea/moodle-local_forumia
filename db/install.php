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
 * Post-installation hook for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Runs once, immediately after the plugin tables have been created.
 *
 * Records the installation timestamp, which is what
 * {@see \local_forumia\license\validator} uses to work out whether the site is
 * still inside its trial window. Without this value there is no trial and the
 * plugin waits for a licence key.
 *
 * @return bool
 */
function xmldb_local_forumia_install() {
    // Never overwrite an existing value: a reinstall over an old configuration
    // must not silently hand out a fresh trial period.
    if (!get_config('local_forumia', 'firstinstall')) {
        set_config('firstinstall', time(), 'local_forumia');
    }

    return true;
}
