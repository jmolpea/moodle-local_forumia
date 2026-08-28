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
 * DeepSeek HTTP client for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\api;

/**
 * Wrapper around the DeepSeek chat completions API.
 *
 * DeepSeek is wire-compatible with the OpenAI chat completions format.
 * All shared behaviour lives in {@see ai_client_base}.
 */
class deepseek_client extends ai_client_base {
    /**
     * Human-readable provider name for log messages.
     *
     * @return string
     */
    protected function get_provider_name(): string {
        return 'DeepSeek';
    }

    /**
     * Allowed endpoint hosts. Fixed — the endpoint is not configurable.
     *
     * @return string[]
     */
    protected function get_allowed_hosts(): array {
        return ['api.deepseek.com'];
    }

    /**
     * Fixed chat completions endpoint.
     *
     * @return string
     */
    protected function get_default_endpoint(): string {
        return 'https://api.deepseek.com/chat/completions';
    }

    /**
     * Default model.
     *
     * @return string
     */
    protected function get_default_model(): string {
        return 'deepseek-chat';
    }

    /**
     * Config setting holding the DeepSeek API key.
     *
     * @return string
     */
    protected function get_apikey_setting(): string {
        return 'deepseek_apikey';
    }

    /**
     * Config setting holding the DeepSeek model.
     *
     * @return string
     */
    protected function get_model_setting(): string {
        return 'deepseek_model';
    }

    /**
     * DeepSeek uses a Bearer token in the Authorization header.
     *
     * @return array<string, string>
     */
    protected function build_headers(): array {
        return ['Authorization' => 'Bearer ' . $this->apikey];
    }

    /**
     * Builds the chat completions payload (OpenAI-compatible format).
     *
     * @param  string $systemprompt The system-role message.
     * @param  string $usermessage  The user-role message.
     * @param  bool   $jsonmode     When true, force a strict JSON object response.
     * @return array
     */
    protected function build_payload(string $systemprompt, string $usermessage, bool $jsonmode = false): array {
        $payload = [
            'model'      => $this->model,
            'messages'   => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $usermessage],
            ],
            'max_tokens'  => 800,
            'temperature' => 0.7,
        ];
        // DeepSeek supports OpenAI-compatible JSON output mode.
        if ($jsonmode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        return $payload;
    }

    /**
     * Extracts the first choice text from a chat completions response.
     *
     * @param  array $data Decoded JSON response.
     * @return string|null
     */
    protected function extract_text(array $data): ?string {
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
