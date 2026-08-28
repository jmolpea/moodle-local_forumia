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
 * Tests for the offline licence validator.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

use local_forumia\license\validator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/forumia/tests/fixtures/testable_validator.php');

/**
 * Tests for the offline licence validator.
 *
 * @covers \local_forumia\license\validator
 */
final class license_validator_test extends \advanced_testcase {
    /** @var string PEM private key generated for this test run. */
    private $privatekey;

    /**
     * Generates a throwaway RSA keypair and points the validator at it.
     *
     * Positive cases verify through testable_validator, which checks against
     * the key generated here. Negative cases that must fail for ANY key run
     * against the real shipped public key.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('The openssl extension is required to test licence validation.');
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        // Export into a local first: openssl_pkey_export() takes its target by
        // reference, and a typed property cannot be passed uninitialised.
        $pem = '';
        openssl_pkey_export($resource, $pem);
        $this->privatekey = $pem;

        validator::reset_cache();
    }

    /**
     * Clears the validator's in-request memo between tests.
     */
    protected function tearDown(): void {
        validator::reset_cache();
        parent::tearDown();
    }

    /**
     * Builds a signed licence key for the given payload.
     *
     * @param  array $payload Licence payload.
     * @return string         A key in the {payload_b64}.{signature_b64} format.
     */
    private function make_key(array $payload): string {
        $payloadb64 = $this->b64url(json_encode($payload));
        openssl_sign($payloadb64, $signature, $this->privatekey, OPENSSL_ALGO_SHA256);

        return $payloadb64 . '.' . $this->b64url($signature);
    }

    /**
     * URL-safe base64 without padding.
     *
     * @param  string $data Raw data.
     * @return string
     */
    private function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Returns the test double whose PUBLIC_KEY matches our throwaway keypair.
     *
     * @return string Class name of the validator subclass under test.
     */
    private function validator_class(): string {
        $details  = openssl_pkey_get_details(openssl_pkey_get_private($this->privatekey));
        $publicpem = $details['key'];

        // Strip the PEM armour down to the base64 body the validator expects.
        $body = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $publicpem);

        testable_validator::$publickeyoverride = $body;

        return testable_validator::class;
    }

    /**
     * A correctly signed key bound to this site is accepted.
     */
    public function test_valid_key_for_this_site(): void {
        global $CFG;

        $class = $this->validator_class();
        set_config('license_key', $this->make_key([
            'wwwroot' => $CFG->wwwroot,
            'expires' => date('Y-m-d', time() + YEARSECS),
            'edition' => 'professional',
        ]), 'local_forumia');

        $result = $class::check();

        $this->assertSame(validator::STATUS_VALID, $result->status);
        $this->assertSame('professional', $result->edition);
        $this->assertTrue($class::is_valid());
    }

    /**
     * A key with no expiry date is treated as a lifetime licence.
     */
    public function test_key_without_expiry_is_lifetime(): void {
        global $CFG;

        $class = $this->validator_class();
        set_config('license_key', $this->make_key(['wwwroot' => $CFG->wwwroot]), 'local_forumia');

        $result = $class::check();

        $this->assertSame(validator::STATUS_VALID, $result->status);
        $this->assertNull($result->expires);
    }

    /**
     * A key issued for a different site is rejected. This is the whole point of
     * the wwwroot binding, so it gets its own test.
     */
    public function test_key_for_another_site_is_invalid(): void {
        $class = $this->validator_class();
        set_config('license_key', $this->make_key([
            'wwwroot' => 'https://not-this-site.example.org',
            'expires' => date('Y-m-d', time() + YEARSECS),
        ]), 'local_forumia');

        $this->assertSame(validator::STATUS_INVALID, $class::check()->status);
        $this->assertFalse($class::is_valid());
    }

    /**
     * A reviewer key (wwwroot "*") activates on any site.
     */
    public function test_wildcard_key_activates_on_any_site(): void {
        $class = $this->validator_class();
        set_config('license_key', $this->make_key([
            'wwwroot' => '*',
            'expires' => date('Y-m-d', time() + YEARSECS),
            'edition' => 'reviewer',
        ]), 'local_forumia');

        $result = $class::check();

        $this->assertSame(validator::STATUS_VALID, $result->status);
        $this->assertSame('reviewer', $result->edition);
    }

    /**
     * A wildcard key still has to be in date — reviewer keys must lapse.
     */
    public function test_expired_wildcard_key_is_expired(): void {
        $class = $this->validator_class();
        set_config('license_key', $this->make_key([
            'wwwroot' => '*',
            'expires' => date('Y-m-d', time() - (2 * DAYSECS)),
        ]), 'local_forumia');

        $this->assertSame(validator::STATUS_EXPIRED, $class::check()->status);
        $this->assertFalse($class::is_valid());
    }

    /**
     * A key whose expiry date has passed is reported as expired, not invalid —
     * the distinction drives which banner the admin sees.
     */
    public function test_expired_key(): void {
        global $CFG;

        $class = $this->validator_class();
        $expiry = date('Y-m-d', time() - (30 * DAYSECS));
        set_config('license_key', $this->make_key([
            'wwwroot' => $CFG->wwwroot,
            'expires' => $expiry,
        ]), 'local_forumia');

        $result = $class::check();

        $this->assertSame(validator::STATUS_EXPIRED, $result->status);
        $this->assertSame($expiry, $result->expires);
    }

    /**
     * Tampering with the payload invalidates the signature.
     *
     * This is the attack the asymmetric scheme exists to stop: editing the
     * expiry date in an otherwise genuine key.
     */
    public function test_tampered_payload_is_rejected(): void {
        global $CFG;

        $class = $this->validator_class();
        $key = $this->make_key([
            'wwwroot' => $CFG->wwwroot,
            'expires' => date('Y-m-d', time() - DAYSECS),
        ]);

        // Re-encode the payload with a future expiry, keeping the old signature.
        [, $signature] = explode('.', $key, 2);
        $forged = $this->b64url(json_encode([
            'wwwroot' => $CFG->wwwroot,
            'expires' => date('Y-m-d', time() + YEARSECS),
        ])) . '.' . $signature;

        set_config('license_key', $forged, 'local_forumia');

        $this->assertSame(validator::STATUS_INVALID, $class::check()->status);
    }

    /**
     * A key signed with the wrong private key is rejected by the shipped
     * public key.
     */
    public function test_key_signed_with_wrong_private_key_is_rejected(): void {
        global $CFG;

        // No override: validate against the real shipped PUBLIC_KEY.
        set_config('license_key', $this->make_key([
            'wwwroot' => $CFG->wwwroot,
            'expires' => date('Y-m-d', time() + YEARSECS),
        ]), 'local_forumia');

        $this->assertSame(validator::STATUS_INVALID, validator::check()->status);
        $this->assertFalse(validator::is_valid());
    }

    /**
     * Structurally broken keys are rejected without notices.
     *
     * @dataProvider malformed_key_provider
     * @param string $key The malformed key to feed the validator.
     */
    public function test_malformed_keys_are_rejected(string $key): void {
        set_config('license_key', $key, 'local_forumia');

        $this->assertSame(validator::STATUS_INVALID, validator::check()->status);
    }

    /**
     * Malformed key shapes.
     *
     * @return array<string, array{string}>
     */
    public static function malformed_key_provider(): array {
        return [
            'no separator'      => ['justsomerandomtext'],
            'leading dot'       => ['.signatureonly'],
            'trailing dot'      => ['payloadonly.'],
            'not base64'        => ['!!!!.!!!!'],
            'empty payload json' => ['e30.c2ln'],
        ];
    }

    /**
     * With no key and no install timestamp there is no trial: the plugin is
     * simply unlicensed.
     */
    public function test_no_key_and_no_install_date_is_missing(): void {
        unset_config('license_key', 'local_forumia');
        unset_config('firstinstall', 'local_forumia');
        validator::reset_cache();

        $this->assertSame(validator::STATUS_MISSING, validator::check()->status);
        $this->assertFalse(validator::is_valid());
        $this->assertSame(0, validator::trial_days_left());
    }

    /**
     * A freshly installed site runs on trial, and the trial counts as licensed.
     */
    public function test_fresh_install_is_on_trial(): void {
        unset_config('license_key', 'local_forumia');
        set_config('firstinstall', time(), 'local_forumia');
        validator::reset_cache();

        $result = validator::check();

        $this->assertSame(validator::STATUS_TRIAL, $result->status);
        $this->assertTrue(validator::is_valid(), 'A running trial must enable the AI features.');
        $this->assertSame(validator::TRIAL_DAYS, validator::trial_days_left());
    }

    /**
     * The trial counts down, and the last day still counts as a day.
     */
    public function test_trial_counts_down(): void {
        unset_config('license_key', 'local_forumia');
        set_config('firstinstall', time() - (10 * DAYSECS), 'local_forumia');
        validator::reset_cache();

        $this->assertSame(validator::TRIAL_DAYS - 10, validator::trial_days_left());
        $this->assertSame(validator::STATUS_TRIAL, validator::check()->status);
    }

    /**
     * Once the trial window closes the plugin stops working.
     */
    public function test_expired_trial_disables_the_plugin(): void {
        unset_config('license_key', 'local_forumia');
        set_config('firstinstall', time() - ((validator::TRIAL_DAYS + 1) * DAYSECS), 'local_forumia');
        validator::reset_cache();

        $this->assertSame(validator::STATUS_MISSING, validator::check()->status);
        $this->assertFalse(validator::is_valid());
        $this->assertSame(0, validator::trial_days_left());
    }

    /**
     * An invalid key is not silently downgraded to a running trial. Otherwise a
     * typo would look like it worked for fifteen days.
     */
    public function test_invalid_key_does_not_fall_back_to_trial(): void {
        set_config('license_key', 'garbage.garbage', 'local_forumia');
        set_config('firstinstall', time(), 'local_forumia');
        validator::reset_cache();

        $this->assertSame(validator::STATUS_INVALID, validator::check()->status);
        $this->assertFalse(validator::is_valid());
    }

    /**
     * The banner is silent only when the licence is genuinely valid.
     */
    public function test_banner_is_shown_for_every_non_valid_state(): void {
        unset_config('license_key', 'local_forumia');
        unset_config('firstinstall', 'local_forumia');
        validator::reset_cache();
        $this->assertNotNull(validator::get_banner());

        set_config('firstinstall', time(), 'local_forumia');
        validator::reset_cache();
        $this->assertNotNull(validator::get_banner(), 'The trial banner doubles as a purchase reminder.');

        $class = $this->validator_class();
        global $CFG;
        set_config('license_key', $this->make_key(['wwwroot' => $CFG->wwwroot]), 'local_forumia');
        validator::reset_cache();
        $this->assertNull($class::get_banner());
    }
}
