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
 * Fake AI client that returns canned responses and records every call.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

/**
 * Fake AI client that returns canned responses and records every call.
 *
 * Injected through client_factory::set_test_client(); never touches the network.
 */
class fake_ai_client extends testable_ai_client {
    /** @var string|null Response returned by chat(); null simulates a provider failure. */
    public ?string $response = null;

    /** @var array[] Every call: ['system' => ..., 'user' => ..., 'json' => ...]. */
    public array $calls = [];

    /**
     * Records the call and returns the canned response.
     *
     * @param  string $systemprompt System-role message.
     * @param  string $usermessage  User-role message.
     * @param  bool   $jsonmode     Whether JSON output was requested.
     * @return string|null
     */
    public function chat(string $systemprompt, string $usermessage, bool $jsonmode = false): ?string {
        $this->calls[] = ['system' => $systemprompt, 'user' => $usermessage, 'json' => $jsonmode];
        return $this->response;
    }
}
