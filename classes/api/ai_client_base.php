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
 * Abstract base HTTP client for AI providers used by local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\api;

use core\http_client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use moodle_exception;

/**
 * Shared logic for all AI provider clients (OpenAI, Anthropic, Gemini, DeepSeek).
 *
 * Concrete subclasses only describe the provider specifics: allowed hosts,
 * default endpoint/model, config setting names, request shape and response
 * parsing. Everything else — license gate, endpoint validation (SSRF
 * protection), error handling, rate-limit pause, global disable and admin
 * notification — lives here so it behaves identically for every provider.
 *
 * Security notes:
 * - The API key is never written to logs, error messages or stack traces.
 * - All errors are normalised before being passed to mtrace/debugging.
 */
abstract class ai_client_base {
    /** @var string API endpoint URL. */
    protected string $endpoint;

    /** @var string Model identifier. */
    protected string $model;

    /** @var string API key — never logged or exposed externally. */
    protected string $apikey;

    /** @var string Masked placeholder used in safe log messages. */
    protected const APIKEY_MASK = '[REDACTED]';

    /**
     * Human-readable provider name used in log messages (e.g. "OpenAI").
     *
     * @return string
     */
    abstract protected function get_provider_name(): string;

    /**
     * Allowed hostname suffixes for the API endpoint.
     *
     * Only HTTPS endpoints whose host exactly matches or is a subdomain of one
     * of these values are accepted. This prevents SSRF attacks where a
     * compromised admin config redirects requests to internal network services.
     *
     * @return string[]
     */
    abstract protected function get_allowed_hosts(): array;

    /**
     * Default API endpoint URL for this provider.
     *
     * @return string
     */
    abstract protected function get_default_endpoint(): string;

    /**
     * Default model identifier for this provider.
     *
     * @return string
     */
    abstract protected function get_default_model(): string;

    /**
     * Plugin config setting name that stores this provider's API key.
     *
     * @return string
     */
    abstract protected function get_apikey_setting(): string;

    /**
     * Plugin config setting name that stores this provider's model.
     *
     * @return string
     */
    abstract protected function get_model_setting(): string;

    /**
     * Builds the HTTP headers for a chat request.
     *
     * @return array<string, string>
     */
    abstract protected function build_headers(): array;

    /**
     * Builds the JSON payload for a chat request.
     *
     * @param  string $systemprompt The system-role message.
     * @param  string $usermessage  The user-role message.
     * @param  bool   $jsonmode     When true, request that the provider return a
     *                              strict JSON object (used for AI grading).
     *                              Providers without a native JSON mode ignore it
     *                              and rely on the prompt instructions instead.
     * @return array
     */
    abstract protected function build_payload(string $systemprompt, string $usermessage, bool $jsonmode = false): array;

    /**
     * Extracts the response text from the decoded JSON response body.
     *
     * @param  array $data Decoded JSON response.
     * @return string|null The text content, or null if not present.
     */
    abstract protected function extract_text(array $data): ?string;

    /**
     * Plugin config setting name for a configurable endpoint, or null when the
     * endpoint is fixed for this provider (safer — no SSRF surface).
     *
     * @return string|null
     */
    protected function get_endpoint_setting(): ?string {
        return null;
    }

    /**
     * HTTP timeout for the API call, in seconds.
     *
     * Providers override this when their models need longer (reasoning models
     * spend time thinking before the first token arrives).
     *
     * @return int
     */
    protected function get_timeout(): int {
        return 30;
    }

    /**
     * Constructor.
     *
     * Reads global plugin configuration. Throws if the API key is absent.
     *
     * @throws moodle_exception If no license or API key is configured.
     */
    public function __construct() {
        // License backstop: no AI call may proceed without a valid key bound to
        // this site. This guarantees the block holds for every call path
        // (observer, tasks) even if an entry-point check is ever bypassed.
        if (!\local_forumia\license\validator::is_valid()) {
            throw new moodle_exception('error_nolicense', 'local_forumia');
        }

        $apikey = get_config('local_forumia', $this->get_apikey_setting());
        if (empty($apikey)) {
            throw new moodle_exception('error_noapikey', 'local_forumia');
        }
        // Store the key in a private property — never expose it outside this class.
        $this->apikey = $apikey;
        $this->model  = get_config('local_forumia', $this->get_model_setting()) ?: $this->get_default_model();

        $endpointsetting = $this->get_endpoint_setting();
        if ($endpointsetting !== null) {
            $this->endpoint = get_config('local_forumia', $endpointsetting) ?: $this->get_default_endpoint();
        } else {
            $this->endpoint = $this->get_default_endpoint();
        }

        // Validate the endpoint against the allowed-host whitelist.
        // Done here (constructor) so the check runs for every call path.
        $this->validate_endpoint($this->endpoint);
    }

    /**
     * Validates the configured API endpoint against the allowed-host whitelist.
     *
     * Rules enforced:
     * 1. Scheme MUST be https — plain http is rejected to prevent credential
     *    interception and to block most SSRF vectors against internal HTTP APIs.
     * 2. Host MUST NOT be an IP address — numeric IPs almost always indicate
     *    an SSRF attempt targeting cloud metadata services or internal hosts.
     * 3. Host MUST exactly match or be a subdomain of an allowed host.
     *
     * @param  string $url The endpoint URL to validate.
     * @return void
     * @throws moodle_exception If the endpoint fails any validation rule.
     */
    protected function validate_endpoint(string $url): void {
        $parsed = parse_url($url);

        // Rule 1: HTTPS only.
        if (empty($parsed['scheme']) || strtolower($parsed['scheme']) !== 'https') {
            throw new moodle_exception('error_endpoint_https', 'local_forumia');
        }

        $host = strtolower($parsed['host'] ?? '');
        if ($host === '') {
            throw new moodle_exception('error_endpoint_blocked', 'local_forumia');
        }

        // Rule 2: Reject raw IP addresses (IPv4 and IPv6).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new moodle_exception('error_endpoint_blocked', 'local_forumia');
        }
        // IPv6 addresses may appear as [::1] in a URL — strip brackets and re-check.
        $hostnobrk = trim($host, '[]');
        if (filter_var($hostnobrk, FILTER_VALIDATE_IP) !== false) {
            throw new moodle_exception('error_endpoint_blocked', 'local_forumia');
        }

        // Rule 3: Host must be in the whitelist (exact match or subdomain).
        foreach ($this->get_allowed_hosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return; // Endpoint is safe.
            }
        }

        throw new moodle_exception('error_endpoint_blocked', 'local_forumia');
    }

    /**
     * Sends a chat completion request to the provider and returns the text response.
     *
     * @param  string $systemprompt The system-role message.
     * @param  string $usermessage  The user-role message.
     * @param  bool   $jsonmode     When true, ask the provider for a strict JSON
     *                              object response (used for AI grading).
     * @return string|null          The text of the response, or null on failure.
     */
    public function chat(string $systemprompt, string $usermessage, bool $jsonmode = false): ?string {
        $provider = $this->get_provider_name();

        try {
            $client   = new http_client(['timeout' => $this->get_timeout()]);
            $response = $client->post($this->endpoint, [
                'headers' => array_merge(['Content-Type' => 'application/json'], $this->build_headers()),
                'json'    => $this->build_payload($systemprompt, $usermessage, $jsonmode),
            ]);

            $statuscode = $response->getStatusCode();
            $body       = (string) $response->getBody();
            $data       = json_decode($body, true);

            if ($statuscode === 200 && is_array($data)) {
                $text = $this->extract_text($data);
                if ($text !== null && $text !== '') {
                    return trim($text);
                }
            }

            // Unexpected 2xx without the expected structure.
            $this->log_error('Unexpected ' . $provider . ' response structure. Status: ' . $statuscode);
            return null;
        } catch (RequestException $e) {
            $this->handle_request_exception($e);
            return null;
        } catch (ConnectException $e) {
            $this->log_error($provider . ' connection timeout or network error.');
            return null;
        } catch (\Throwable $e) {
            // Catch-all: ensure the API key never leaks in unexpected exception messages.
            $safe = $this->sanitise_exception_message($e->getMessage());
            $this->log_error('Unexpected error calling ' . $provider . ': ' . $safe);
            return null;
        }
    }

    /**
     * Handles Guzzle HTTP-level exceptions, dispatching on status code.
     *
     * @param RequestException $e The caught exception.
     * @return void
     */
    protected function handle_request_exception(RequestException $e): void {
        $provider = $this->get_provider_name();

        if (!$e->hasResponse()) {
            $this->log_error($provider . ' request failed with no HTTP response (network error or timeout).');
            return;
        }

        $statuscode = $e->getResponse()->getStatusCode();

        switch (true) {
            case $statuscode === 401 || $statuscode === 403:
                $this->log_error($provider . ' API key invalid or unauthorised (HTTP ' . $statuscode . ').');
                $this->disable_plugin_globally();
                $this->notify_admin_api_error();
                break;

            case $statuscode === 429:
                $this->log_error($provider . ' rate limit reached (HTTP 429). The assistant will pause for one hour.');
                $this->set_rate_limit_pause();
                break;

            case $statuscode === 400:
                // Bad request — most commonly an invalid model name or malformed payload.
                // Log the response body (API key is never in the response, only in request
                // headers, so the body is safe to log after sanitisation).
                $this->log_error(
                    $provider . ' bad request (HTTP 400). Likely cause: invalid model name or request parameter. '
                    . 'Detail: ' . $this->safe_response_body($e)
                );
                break;

            case $statuscode >= 500:
                $this->log_error($provider . ' server error (HTTP ' . $statuscode . '). Not retrying.');
                break;

            default:
                $this->log_error(
                    $provider . ' HTTP error: ' . $statuscode . '. Detail: ' . $this->safe_response_body($e)
                );
        }
    }

    /**
     * Extracts and sanitises the provider response body from a RequestException.
     *
     * The response body never contains the API key (keys are only sent in
     * request headers), but we run it through sanitise_exception_message()
     * as an extra safety measure before writing to the log.
     *
     * @param  RequestException $e The caught exception.
     * @return string              Sanitised response body, or a placeholder if unavailable.
     */
    protected function safe_response_body(RequestException $e): string {
        if (!$e->hasResponse()) {
            return '(no response body)';
        }
        $body = (string) $e->getResponse()->getBody();
        // Truncate to 500 chars to keep log entries readable.
        if (mb_strlen($body) > 500) {
            $body = mb_substr($body, 0, 500) . '…';
        }
        return $this->sanitise_exception_message($body);
    }

    /**
     * Disables the plugin globally by setting a config flag.
     *
     * A site administrator must re-enable it after fixing the API key.
     *
     * @return void
     */
    protected function disable_plugin_globally(): void {
        set_config('globally_disabled', 1, 'local_forumia');
        $this->log_error(get_string('error_apiunauthorized', 'local_forumia'));
    }

    /**
     * Sends an internal Moodle notification to all site administrators about the API error.
     *
     * @return void
     */
    protected function notify_admin_api_error(): void {
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        $subject = get_string('pluginname', 'local_forumia') . ': ' .
                   get_string('error_apiunauthorized', 'local_forumia');
        $body    = get_string('error_apiunauthorized_notification', 'local_forumia');

        $supportuser = \core_user::get_support_user();
        foreach ($admins as $admin) {
            $message                      = new \core\message\message();
            $message->component           = 'local_forumia';
            $message->name                = 'api_error';
            $message->userfrom            = $supportuser;
            $message->userto              = $admin;
            $message->subject             = $subject;
            $message->fullmessage         = $body;
            $message->fullmessageformat   = FORMAT_PLAIN;
            $message->fullmessagehtml     = \html_writer::tag('p', s($body));
            $message->smallmessage        = $subject;
            $message->notification        = 1;
            message_send($message);
        }
    }

    /**
     * Stores a timestamp so the processor can detect the rate-limit pause period.
     *
     * @return void
     */
    protected function set_rate_limit_pause(): void {
        set_config('ratelimit_until', time() + HOURSECS, 'local_forumia');
    }

    /**
     * Writes a safe error message to the Moodle debug log.
     *
     * The API key is never included in log messages.
     *
     * @param string $message Human-readable error description (must not contain the API key).
     * @return void
     */
    protected function log_error(string $message): void {
        debugging('[local_forumia] ' . $message, DEBUG_NORMAL);
    }

    /**
     * Replaces any occurrence of the API key in an exception message with a safe mask.
     *
     * @param  string $message Raw exception message.
     * @return string          Sanitised message safe for logging.
     */
    protected function sanitise_exception_message(string $message): string {
        if (!empty($this->apikey)) {
            $message = str_replace($this->apikey, self::APIKEY_MASK, $message);
        }
        return $message;
    }

    /**
     * Returns true if the plugin is currently in a global rate-limit pause.
     *
     * The pause flag is provider-agnostic (single shared config value).
     *
     * @return bool
     */
    public static function is_rate_limited(): bool {
        $until = (int) get_config('local_forumia', 'ratelimit_until');
        return $until > time();
    }

    /**
     * Returns true if the plugin has been globally disabled due to an API auth error.
     *
     * @return bool
     */
    public static function is_globally_disabled(): bool {
        return (bool) get_config('local_forumia', 'globally_disabled');
    }
}
