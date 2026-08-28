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
 * Test double for the AI client base class.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

/**
 * Concrete AI client that never touches the network.
 *
 * Exists so the endpoint allowlist — the plugin's SSRF defence — can be tested
 * directly. validate_endpoint() is protected and runs inside the constructor,
 * so this double exposes it as a public method that can be called with an
 * arbitrary URL.
 */
class testable_ai_client extends \local_forumia\api\ai_client_base {
    /**
     * Constructor that skips the licence and API key checks.
     *
     * Those are covered by their own tests; here they would only be noise.
     */
    public function __construct() {
        // Deliberately does not call parent::__construct().
        $this->apikey   = 'test-key-never-sent';
        $this->model    = 'test-model';
        $this->endpoint = $this->get_default_endpoint();
    }

    /**
     * Exposes the protected endpoint check to the test.
     *
     * @param  string $url URL to validate.
     * @return void
     * @throws \moodle_exception When the URL fails validation.
     */
    public function check_endpoint(string $url): void {
        $this->validate_endpoint($url);
    }

    /**
     * Provider label used in log messages.
     *
     * @return string
     */
    protected function get_provider_name(): string {
        return 'Testable';
    }

    /**
     * Hostnames this fake provider accepts.
     *
     * @return string[]
     */
    protected function get_allowed_hosts(): array {
        return ['api.example.com', 'example.org'];
    }

    /**
     * Default endpoint for this fake provider.
     *
     * @return string
     */
    protected function get_default_endpoint(): string {
        return 'https://api.example.com/v1/chat';
    }

    /**
     * Default model for this fake provider.
     *
     * @return string
     */
    protected function get_default_model(): string {
        return 'test-model';
    }

    /**
     * Config setting holding the API key.
     *
     * @return string
     */
    protected function get_apikey_setting(): string {
        return 'apikey';
    }

    /**
     * Config setting holding the model.
     *
     * @return string
     */
    protected function get_model_setting(): string {
        return 'model';
    }

    /**
     * Request headers.
     *
     * @return array<string, string>
     */
    protected function build_headers(): array {
        return [];
    }

    /**
     * Request payload.
     *
     * @param  string $systemprompt System-role message.
     * @param  string $usermessage  User-role message.
     * @param  bool   $jsonmode     Whether to request a strict JSON object.
     * @return array
     */
    protected function build_payload(string $systemprompt, string $usermessage, bool $jsonmode = false): array {
        return ['system' => $systemprompt, 'user' => $usermessage, 'json' => $jsonmode];
    }

    /**
     * Response parser.
     *
     * @param  array $data Decoded response.
     * @return string|null
     */
    protected function extract_text(array $data): ?string {
        return $data['text'] ?? null;
    }
}
