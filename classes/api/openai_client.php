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
 * OpenAI HTTP client for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\api;

/**
 * Wrapper around the OpenAI chat completions API.
 *
 * Also supports Azure OpenAI via the configurable endpoint setting.
 * All shared behaviour (SSRF-safe endpoint validation, error handling,
 * rate-limit pause, key masking) lives in {@see ai_client_base}.
 */
class openai_client extends ai_client_base {
    /**
     * Human-readable provider name for log messages.
     *
     * @return string
     */
    protected function get_provider_name(): string {
        return 'OpenAI';
    }

    /**
     * Allowed endpoint hosts.
     *
     * Supported providers:
     * - OpenAI direct:  api.openai.com
     * - Azure OpenAI:   <resource>.openai.azure.com
     *
     * @return string[]
     */
    protected function get_allowed_hosts(): array {
        return ['api.openai.com', 'openai.azure.com'];
    }

    /**
     * Default chat completions endpoint.
     *
     * @return string
     */
    protected function get_default_endpoint(): string {
        return 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * OpenAI is the only provider with a configurable endpoint (for Azure).
     *
     * @return string|null
     */
    protected function get_endpoint_setting(): ?string {
        return 'endpoint';
    }

    /**
     * Default model.
     *
     * @return string
     */
    protected function get_default_model(): string {
        return 'gpt-5.6-luna';
    }

    /**
     * Config setting holding the OpenAI API key.
     *
     * @return string
     */
    protected function get_apikey_setting(): string {
        return 'apikey';
    }

    /**
     * Config setting holding the OpenAI model.
     *
     * @return string
     */
    protected function get_model_setting(): string {
        return 'model';
    }

    /**
     * OpenAI uses a Bearer token in the Authorization header.
     *
     * @return array<string, string>
     */
    protected function build_headers(): array {
        return ['Authorization' => 'Bearer ' . $this->apikey];
    }

    /**
     * Whether the configured model is a GPT-5 family reasoning model.
     *
     * Covers gpt-5-nano, gpt-5-mini, gpt-5.1 and the gpt-5.6 tiers (Sol, Terra,
     * Luna), all of which use the reasoning parameter set. The older gpt-4.x
     * models keep the legacy max_tokens/temperature pair.
     *
     * @return bool
     */
    protected function is_reasoning_model(): bool {
        return (bool) preg_match('/^gpt-5/i', $this->model);
    }

    /**
     * Reasoning models think before answering, so they need a longer HTTP
     * timeout than the 30s that suits the older chat models.
     *
     * @return int Seconds.
     */
    protected function get_timeout(): int {
        return $this->is_reasoning_model() ? 120 : parent::get_timeout();
    }

    /**
     * Builds the chat completions payload.
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
        ];

        if ($this->is_reasoning_model()) {
            /* GPT-5 family (reasoning models). Three differences from the older
             * chat models, all of which are hard errors or silent empty replies:
             *
             * 1. max_tokens is rejected; the parameter is max_completion_tokens.
             * 2. Reasoning tokens are billed and counted as output tokens, so the
             *    budget must cover the hidden chain of thought AND the visible
             *    answer. If it runs out mid-reasoning the response comes back with
             *    finish_reason "length" and an EMPTY content string. 8000 leaves
             *    generous headroom over the ~800 tokens a forum reply needs.
             * 3. temperature must not be sent at all - reasoning models reject it.
             */
            $payload['max_completion_tokens'] = 8000;
            // Effort "low" keeps latency and output-token spend down while still
            // getting the reasoning benefit. Use "none" to disable it entirely.
            $payload['reasoning_effort'] = 'low';
        } else {
            $payload['max_tokens']  = 800;
            $payload['temperature'] = 0.7;
        }
        // OpenAI JSON mode guarantees a syntactically valid JSON object, which
        // the grading path relies on. Requires the word "json" in the prompt
        // (present in the grading system prompt).
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
        $text = $data['choices'][0]['message']['content'] ?? null;

        // A reasoning model that burns the whole token budget on its chain of
        // thought returns an empty content string with finish_reason "length".
        // Without this the failure surfaces as a generic "unexpected structure".
        if (($text === null || $text === '') && ($data['choices'][0]['finish_reason'] ?? '') === 'length') {
            $this->log_error(
                'OpenAI returned no content: the token budget was exhausted before the answer. '
                . 'Raise max_completion_tokens or lower reasoning_effort for model ' . $this->model . '.'
            );
        }

        return $text;
    }
}
