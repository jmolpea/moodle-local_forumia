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
 * Designated AI assistant accounts for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

/**
 * Decides which Moodle accounts may publish the assistant's replies.
 *
 * Only a site administrator can designate an assistant account, in one of two
 * ways:
 * - the site-wide "defaultbot" setting, or
 * - granting local/forumia:actasassistant to the account in the system context.
 *
 * Course teachers and managers are never candidates merely because of their
 * course role. The capability is checked without the site-admin "do anything"
 * shortcut, so an administrator's own account is not a candidate unless it is
 * explicitly designated.
 */
class assistant_account {
    /** @var string Capability that designates an assistant account. */
    public const CAPABILITY = 'local/forumia:actasassistant';

    /**
     * Returns the user ID of the site-wide default assistant account, or null.
     *
     * The setting accepts either a numeric user ID or a username.
     *
     * @return int|null
     */
    public static function get_default_userid(): ?int {
        global $DB;

        $setting = trim((string) get_config('local_forumia', 'defaultbot'));
        if ($setting === '') {
            return null;
        }

        if (ctype_digit($setting)) {
            return (int) $setting;
        }

        $userid = $DB->get_field('user', 'id', ['username' => clean_param($setting, PARAM_USERNAME), 'deleted' => 0]);
        return $userid ? (int) $userid : null;
    }

    /**
     * Returns true if the given user may publish the assistant's replies.
     *
     * @param  int  $userid Moodle user ID.
     * @return bool
     */
    public static function is_designated(int $userid): bool {
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }
        if ($userid === self::get_default_userid()) {
            return true;
        }
        return has_capability(self::CAPABILITY, \context_system::instance(), $userid, false);
    }

    /**
     * Returns the active, designated assistant accounts, ordered by name.
     *
     * @return \stdClass[] User records keyed by ID.
     */
    public static function get_candidates(): array {
        global $DB;

        $userids = [];
        $default = self::get_default_userid();
        if ($default !== null) {
            $userids[] = $default;
        }
        // Site administrators are not included unless they hold the capability
        // through a real role assignment: get_users_by_capability() ignores the
        // admin "do anything" shortcut.
        $holders = get_users_by_capability(\context_system::instance(), self::CAPABILITY, 'u.id');
        foreach ($holders as $holder) {
            $userids[] = (int) $holder->id;
        }
        $userids = array_unique($userids);
        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        return $DB->get_records_select(
            'user',
            "id $insql AND deleted = 0 AND suspended = 0",
            $params,
            'lastname ASC, firstname ASC',
            'id, username, ' . implode(', ', \core_user\fields::get_name_fields())
        );
    }

    /**
     * Returns the active user record for a designated account, or null.
     *
     * @param  int $userid Moodle user ID.
     * @return \stdClass|null
     */
    public static function get_active_user(int $userid): ?\stdClass {
        global $DB;

        if (!self::is_designated($userid)) {
            return null;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        return $user ?: null;
    }
}
