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
 * Tests for the AI endpoint allowlist (SSRF defence).
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/forumia/tests/fixtures/testable_ai_client.php');

/**
 * Tests for the AI endpoint allowlist.
 *
 * The admin-configurable endpoint is the plugin's only user-controlled URL, so
 * it is the only place an SSRF could originate. These tests pin the three rules
 * that close it: HTTPS only, no literal IPs, allowlisted hosts only.
 *
 * @covers \local_forumia\api\ai_client_base
 */
final class endpoint_validation_test extends \advanced_testcase {
    /** @var testable_ai_client Client under test. */
    private testable_ai_client $client;

    /**
     * Builds the client double.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->client = new testable_ai_client();
    }

    /**
     * URLs on allowlisted hosts are accepted.
     *
     * @dataProvider allowed_endpoint_provider
     * @param string $url An endpoint that must be accepted.
     */
    public function test_allowed_endpoints_are_accepted(string $url): void {
        $this->client->check_endpoint($url);

        // Reaching this line IS the assertion: validate_endpoint() returns
        // void and throws on failure.
        $this->assertTrue(true);
    }

    /**
     * Endpoints the allowlist must accept.
     *
     * @return array<string, array{string}>
     */
    public static function allowed_endpoint_provider(): array {
        return [
            'exact host'          => ['https://api.example.com/v1/chat'],
            'other exact host'    => ['https://example.org/v1/chat'],
            'subdomain'           => ['https://eu.example.org/v1/chat'],
            'deep subdomain'      => ['https://a.b.example.org/v1/chat'],
            'uppercase host'      => ['https://API.EXAMPLE.COM/v1/chat'],
            'port on allowed host' => ['https://api.example.com:443/v1/chat'],
        ];
    }

    /**
     * Endpoints outside the allowlist are rejected.
     *
     * @dataProvider blocked_endpoint_provider
     * @param string $url An endpoint that must be rejected.
     */
    public function test_blocked_endpoints_are_rejected(string $url): void {
        $this->expectException(\moodle_exception::class);
        $this->client->check_endpoint($url);
    }

    /**
     * Endpoints the allowlist must reject.
     *
     * The IP and metadata cases are the ones that matter: they are the
     * canonical SSRF targets in a cloud-hosted Moodle.
     *
     * @return array<string, array{string}>
     */
    public static function blocked_endpoint_provider(): array {
        return [
            'plain http'            => ['http://api.example.com/v1/chat'],
            'no scheme'             => ['api.example.com/v1/chat'],
            'file scheme'           => ['file:///etc/passwd'],
            'gopher scheme'         => ['gopher://api.example.com/'],
            'unrelated host'        => ['https://evil.example.net/v1/chat'],
            'suffix lookalike'      => ['https://notexample.org/v1/chat'],
            'allowlisted as prefix' => ['https://example.org.evil.net/v1/chat'],
            'localhost by name'     => ['https://localhost/v1/chat'],
            'loopback ipv4'         => ['https://127.0.0.1/v1/chat'],
            'private ipv4'          => ['https://192.168.1.10/v1/chat'],
            'link local ipv4'       => ['https://169.254.169.254/latest/meta-data/'],
            'loopback ipv6'         => ['https://[::1]/v1/chat'],
            'public ip'             => ['https://8.8.8.8/v1/chat'],
            'empty string'          => [''],
        ];
    }

    /**
     * The suffix check must not be fooled by a host that merely ends with the
     * allowlisted string. "notexample.org" is not a subdomain of "example.org".
     */
    public function test_suffix_match_requires_a_dot_boundary(): void {
        $this->expectException(\moodle_exception::class);
        $this->client->check_endpoint('https://notexample.org/v1/chat');
    }

    /**
     * The real provider clients ship sane allowlists.
     *
     * A regression here — someone adding a wildcard, or a typo widening a host —
     * would silently reopen the SSRF surface, so the shipped values are pinned.
     */
    public function test_shipped_providers_restrict_their_hosts(): void {
        $expected = [
            \local_forumia\api\openai_client::class    => ['api.openai.com', 'openai.azure.com'],
            \local_forumia\api\anthropic_client::class => ['api.anthropic.com'],
            \local_forumia\api\gemini_client::class    => ['generativelanguage.googleapis.com'],
            \local_forumia\api\deepseek_client::class  => ['api.deepseek.com'],
        ];

        foreach ($expected as $class => $hosts) {
            $method = new \ReflectionMethod($class, 'get_allowed_hosts');
            $method->setAccessible(true);

            $actual = $method->invoke($method->getDeclaringClass()->newInstanceWithoutConstructor());

            $this->assertSame($hosts, $actual, "Allowed hosts changed for {$class}");
            $this->assertNotContains('*', $actual, "Wildcard host in {$class}");
        }
    }
}
