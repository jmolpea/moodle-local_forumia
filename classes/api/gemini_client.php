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
 * Google Gemini HTTP client for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\api;

/**
 * Wrapper around the Google Gemini generateContent API.
 *
 * The API key is sent via the x-goog-api-key header (never in the URL, so it
 * cannot leak into logs). All shared behaviour lives in {@see ai_client_base}.
 */
class gemini_client extends ai_client_base {
    /**
     * Human-readable provider name for log messages.
     *
     * @return string
     */
    protected function get_provider_name(): string {
        return 'Gemini';
    }

    /**
     * Allowed endpoint hosts. Fixed — the endpoint is not configurable.
     *
     * @return string[]
     */
    protected function get_allowed_hosts(): array {
        return ['generativelanguage.googleapis.com'];
    }

    /**
     * generateContent endpoint. The model is part of the URL path; it is read
     * from config in the parent constructor before this method is called, and
     * urlencode() guards against a malformed stored value altering the path.
     *
     * @return string
     */
    protected function get_default_endpoint(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            . urlencode($this->model) . ':generateContent';
    }

    /**
     * Default model.
     *
     * @return string
     */
    protected function get_default_model(): string {
        return 'gemini-2.5-flash';
    }

    /**
     * Config setting holding the Gemini API key.
     *
     * @return string
     */
    protected function get_apikey_setting(): string {
        return 'gemini_apikey';
    }

    /**
     * Config setting holding the Gemini model.
     *
     * @return string
     */
    protected function get_model_setting(): string {
        return 'gemini_model';
    }

    /**
     * Gemini uses the x-goog-api-key header.
     *
     * @return array<string, string>
     */
    protected function build_headers(): array {
        return ['x-goog-api-key' => $this->apikey];
    }

    /**
     * Builds the generateContent payload.
     *
     * @param  string $systemprompt The system prompt.
     * @param  string $usermessage  The user message.
     * @param  bool   $jsonmode     When true, force a strict JSON object response.
     * @return array
     */
    protected function build_payload(string $systemprompt, string $usermessage, bool $jsonmode = false): array {
        $generationconfig = [
            'maxOutputTokens' => 800,
            'temperature'     => 0.7,
        ];
        // Gemini forces valid JSON output via responseMimeType.
        if ($jsonmode) {
            $generationconfig['responseMimeType'] = 'application/json';
        }
        return [
            'system_instruction' => [
                'parts' => [['text' => $systemprompt]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $usermessage]],
                ],
            ],
            'generationConfig' => $generationconfig,
        ];
    }

    /**
     * Extracts the first candidate text from a generateContent response.
     *
     * Parts flagged with "thought" (reasoning summaries emitted by Gemini 2.5
     * thinking models) are skipped — only the actual answer text is returned.
     *
     * @param  array $data Decoded JSON response.
     * @return string|null
     */
    protected function extract_text(array $data): ?string {
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (!empty($part['thought'])) {
                continue;
            }
            if (isset($part['text']) && $part['text'] !== '') {
                return $part['text'];
            }
        }
        return null;
    }
}
