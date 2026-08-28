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
 * Anthropic (Claude) HTTP client for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\api;

/**
 * Wrapper around the Anthropic Messages API (POST /v1/messages).
 *
 * All shared behaviour (SSRF-safe endpoint validation, error handling,
 * rate-limit pause, key masking) lives in {@see ai_client_base}.
 */
class anthropic_client extends ai_client_base {
    /** @var string Anthropic API version header value. */
    private const API_VERSION = '2023-06-01';

    /**
     * Human-readable provider name for log messages.
     *
     * @return string
     */
    protected function get_provider_name(): string {
        return 'Anthropic';
    }

    /**
     * Allowed endpoint hosts. Fixed — the endpoint is not configurable.
     *
     * @return string[]
     */
    protected function get_allowed_hosts(): array {
        return ['api.anthropic.com'];
    }

    /**
     * Fixed Messages API endpoint.
     *
     * @return string
     */
    protected function get_default_endpoint(): string {
        return 'https://api.anthropic.com/v1/messages';
    }

    /**
     * Default model.
     *
     * @return string
     */
    protected function get_default_model(): string {
        return 'claude-haiku-4-5';
    }

    /**
     * Config setting holding the Anthropic API key.
     *
     * @return string
     */
    protected function get_apikey_setting(): string {
        return 'anthropic_apikey';
    }

    /**
     * Config setting holding the Anthropic model.
     *
     * @return string
     */
    protected function get_model_setting(): string {
        return 'anthropic_model';
    }

    /**
     * Anthropic uses the x-api-key header plus a required version header.
     *
     * @return array<string, string>
     */
    protected function build_headers(): array {
        return [
            'x-api-key'         => $this->apikey,
            'anthropic-version' => self::API_VERSION,
        ];
    }

    /**
     * Builds the Messages API payload.
     *
     * The system prompt is a top-level field (not a message role).
     * Sampling parameters are omitted: recent Claude models (Sonnet 5,
     * Opus 4.8) reject temperature/top_p with HTTP 400.
     *
     * @param  string $systemprompt The system prompt.
     * @param  string $usermessage  The user message.
     * @param  bool   $jsonmode     Ignored — Anthropic has no native JSON mode;
     *                              strict JSON is requested via the prompt and
     *                              recovered by the caller's tolerant parser.
     * @return array
     */
    protected function build_payload(string $systemprompt, string $usermessage, bool $jsonmode = false): array {
        return [
            'model'      => $this->model,
            'max_tokens' => 800,
            'system'     => $systemprompt,
            'messages'   => [
                ['role' => 'user', 'content' => $usermessage],
            ],
        ];
    }

    /**
     * Extracts the first text block from a Messages API response.
     *
     * Content is an array of typed blocks; scan for the first "text" block
     * rather than assuming position 0 (a thinking block may come first).
     * A stop_reason of "refusal" yields no text block and returns null,
     * which the caller treats as a normal failure (no post is published).
     *
     * @param  array $data Decoded JSON response.
     * @return string|null
     */
    protected function extract_text(array $data): ?string {
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                return $block['text'];
            }
        }
        return null;
    }
}
