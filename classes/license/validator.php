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
 * Offline asymmetric (RSA-SHA256) license validator.
 *
 * Key format:
 *   {base64url(json_payload)}.{base64url(rsa_sha256_signature)}
 *
 * Payload schema (JSON):
 *   {
 *     "wwwroot": "https://example.com/moodle",  // full URL, not domain only.
 *                                               // "*" = any site (reviewer keys only)
 *     "expires": "2027-03-29",                  // ISO date, omit for lifetime
 *     "edition": "professional"                  // informational
 *   }
 *
 * Signing (RSMAX side — asymmetric; the private key never ships):
 *   $payloadb64 = base64url_encode(json_encode($payload));
 *   openssl_sign($payloadb64, $sig, $privatekey, OPENSSL_ALGO_SHA256);
 *   $key         = $payloadb64 . '.' . base64url_encode($sig);
 *
 * Validation (plugin side):
 *   1. Split key at the last '.' → payload_b64 + signature_b64url
 *   2. Verify the RSA-SHA256 signature with the embedded PUBLIC key
 *   3. Decode payload, verify wwwroot === $CFG->wwwroot (exact match, trailing
 *      slash normalised). The literal "*" matches any site — reserved for the
 *      evaluation keys issued to plugin reviewers.
 *   4. If 'expires' is present, check it is in the future
 *
 * Trial period:
 *   A fresh installation runs with every feature enabled for TRIAL_DAYS days,
 *   with no key at all. The install date is written to the 'firstinstall'
 *   config value by db/install.php (and backfilled by db/upgrade.php for sites
 *   that predate this feature). This exists so an administrator — or a plugin
 *   reviewer — can evaluate the plugin before obtaining a key, and so the gap
 *   between purchase and first value is not blocked on key issuance.
 *
 *   The trial is deliberately not tamper-proof: 'firstinstall' is a plain
 *   config value an admin could reset. That is fine. Like the key itself, this
 *   is commercial friction and traceability, not a security boundary — the
 *   plugin is GPLv3 and anyone determined can edit this file.
 *
 * Enforcement policy:
 *   Unlike the analytics dashboard (which only warns), this plugin BLOCKS its
 *   AI functionality when the license is missing, invalid, or expired. The
 *   block points are the processor entry methods and the AI client; the host
 *   forum experience is never affected. A site administrator can always reach
 *   the plugin settings to paste a key.
 *
 * Why asymmetric:
 *   Only the public key is shipped in this file. A public key verifies
 *   signatures but cannot create them, so distributing it is safe. The private
 *   key stays at RSMAX (generate_license.php + license_private.key, both
 *   excluded from the distribution zip). Each plugin has its OWN keypair, so a
 *   license issued for one plugin cannot unlock another.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\license;

/**
 * Offline RSA-SHA256 license validator for local_forumia.
 */
class validator {
    /** @var string Frankenstyle component — used for get_config() and get_string(). */
    const COMPONENT = 'local_forumia';

    /** @var int Days a fresh installation runs with all features enabled and no key. */
    const TRIAL_DAYS = 15;

    /** @var string Payload wwwroot value that matches any site (reviewer keys). */
    const WWWROOT_ANY = '*';

    /** @var \stdClass|null In-request memo of the last check() result. */
    private static $cachedresult = null;

    /**
     * RSA public key (base64 DER) used to verify license signatures.
     *
     * Safe to distribute: a public key verifies signatures but cannot create
     * them. The matching private key stays at RSMAX (license_private.key, used
     * by generate_license.php) and is never committed or shipped. Rotate the
     * pair with:  php generate_license.php --genkeys
     */
    const PUBLIC_KEY =
            'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuE5DU+pdjx7hv1ziqPsKDgy8+y3aGw38R+XSMHIjsL26njEmYeTc'
            . 'FjAIXdghg9fKIqPNVaLXWQMwZ7l0fI/AslUUMcOK5b9PKy7zp6iltTXwiAYZQJZKY1Ih78zwSSRqOnqCuZvHwyKzb/a48zha'
            . 'F1KO4TJvBSQufpet0Uj/uRFAfRnu1rJ8XXQJzlcJPC0YCYDeAe/d/j1H2HQxcjOj+hvUhrfjK4CxmdKl3r7nZiivtV/8xZ5R'
            . 'IT6JtxScm3VYzGdgAQXHd2PzchIOA7cYf4jlFflhSJTl+pIzb8cQt7cKE8XLN/QMNio1Uo+PR9FTwoN29wDOAaG9wfBqO4yR'
            . 'OwIDAQAB';

    /** License is present and cryptographically valid. */
    const STATUS_VALID   = 'valid';

    /** No key entered, but the installation is still inside its trial window. */
    const STATUS_TRIAL   = 'trial';

    /** Signature verification failed, or wwwroot does not match. */
    const STATUS_INVALID = 'invalid';

    /** Signature valid and wwwroot matches, but the expiry date has passed. */
    const STATUS_EXPIRED = 'expired';

    /** No license key has been entered and the trial window has closed. */
    const STATUS_MISSING = 'missing';

    /**
     * Validate the currently-configured license key against this Moodle installation.
     *
     * When no key is configured, falls back to the trial window: a fresh
     * installation is treated as licensed for TRIAL_DAYS days.
     *
     * @return \stdClass {
     *     string      status   One of STATUS_* constants.
     *     string|null expires  ISO date string, null if lifetime or not applicable.
     *     string|null edition  Edition name from payload, null if not present.
     * }
     */
    public static function check(): \stdClass {
        // Memoised for the lifetime of the request. check() is called from the
        // admin settings tree, the forum settings page, the processor and every
        // AI client constructor, and each miss costs an openssl_verify().
        if (self::$cachedresult !== null) {
            return self::$cachedresult;
        }

        $key = trim((string) get_config(self::COMPONENT, 'license_key'));

        self::$cachedresult = ($key === '') ? self::check_trial() : self::validate_key($key);

        return self::$cachedresult;
    }

    /**
     * Clears the in-request memo.
     *
     * Only needed by tests, which change the configured key several times
     * within a single request.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$cachedresult = null;
    }

    /**
     * Convenience boolean: true when the AI functionality should be enabled.
     *
     * This is the gate used by the processor and the AI clients. A running
     * trial counts as licensed — that is the whole point of the trial.
     *
     * @return bool
     */
    public static function is_valid(): bool {
        $status = self::check()->status;
        return $status === self::STATUS_VALID || $status === self::STATUS_TRIAL;
    }

    /**
     * Returns the number of whole days left in the trial window.
     *
     * @return int Days remaining, or 0 when there is no trial running.
     */
    public static function trial_days_left(): int {
        $installed = (int) get_config(self::COMPONENT, 'firstinstall');
        if ($installed <= 0) {
            return 0;
        }

        $remaining = ($installed + (self::TRIAL_DAYS * DAYSECS)) - time();

        return $remaining > 0 ? (int) ceil($remaining / DAYSECS) : 0;
    }

    /**
     * Return a user-facing warning string for non-valid license states,
     * or null when the license is valid (no banner needed).
     *
     * @return string|null  Lang string with substitution applied, or null.
     */
    public static function get_banner(): ?string {
        global $CFG;

        $result = self::check();

        switch ($result->status) {
            case self::STATUS_TRIAL:
                // Not an error: the assistant is fully working. The banner is a
                // purchase reminder, so it counts down rather than warning.
                return get_string('license_banner_trial', self::COMPONENT, self::trial_days_left());

            case self::STATUS_MISSING:
                return get_string('license_banner_missing', self::COMPONENT);

            case self::STATUS_INVALID:
                return get_string('license_banner_invalid', self::COMPONENT, rtrim($CFG->wwwroot, '/'));

            case self::STATUS_EXPIRED:
                return get_string('license_banner_expired', self::COMPONENT, $result->expires);

            default: // STATUS_VALID.
                return null;
        }
    }

    /**
     * Return a short status string and CSS class for the settings page display.
     *
     * @return array{text: string, css: string}
     */
    public static function get_settings_status(): array {
        $result = self::check();

        switch ($result->status) {
            case self::STATUS_VALID:
                $text = $result->expires
                    ? get_string('license_status_valid', self::COMPONENT, $result->expires)
                    : get_string('license_status_valid_lifetime', self::COMPONENT);
                return ['text' => $text, 'css' => 'text-success fw-bold'];

            case self::STATUS_TRIAL:
                return [
                    'text' => get_string('license_status_trial', self::COMPONENT, self::trial_days_left()),
                    'css'  => 'text-info fw-bold',
                ];

            case self::STATUS_EXPIRED:
                return [
                    'text' => get_string('license_status_expired', self::COMPONENT, $result->expires),
                    'css'  => 'text-warning fw-bold',
                ];

            case self::STATUS_INVALID:
                return [
                    'text' => get_string('license_status_invalid', self::COMPONENT),
                    'css'  => 'text-danger fw-bold',
                ];

            default: // MISSING.
                return [
                    'text' => get_string('license_status_missing', self::COMPONENT),
                    'css'  => 'text-muted',
                ];
        }
    }

    // Internals.

    /**
     * Returns the base64 DER public key used to verify signatures.
     *
     * Indirection exists so unit tests can verify keys signed with a throwaway
     * keypair without touching the shipped constant. Production code always
     * gets PUBLIC_KEY.
     *
     * @return string
     */
    protected static function get_public_key(): string {
        return static::PUBLIC_KEY;
    }

    /**
     * Resolve the licence state of a site that has no key configured.
     *
     * Returns STATUS_TRIAL while the installation is inside its trial window,
     * STATUS_MISSING once that window has closed.
     *
     * @return \stdClass
     */
    private static function check_trial(): \stdClass {
        $daysleft = self::trial_days_left();

        if ($daysleft <= 0) {
            return (object) ['status' => self::STATUS_MISSING, 'expires' => null, 'edition' => null];
        }

        $installed = (int) get_config(self::COMPONENT, 'firstinstall');

        return (object) [
            'status'  => self::STATUS_TRIAL,
            'expires' => date('Y-m-d', $installed + (self::TRIAL_DAYS * DAYSECS)),
            'edition' => 'trial',
        ];
    }

    /**
     * Perform cryptographic validation of a non-empty license key string.
     *
     * @param  string $key  Trimmed license key.
     * @return \stdClass
     */
    private static function validate_key(string $key): \stdClass {
        // Split at the LAST dot: payload_b64 . '.' . signature_b64url.
        $dotpos = strrpos($key, '.');
        if ($dotpos === false || $dotpos === 0 || $dotpos === strlen($key) - 1) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        $payloadb64 = substr($key, 0, $dotpos);
        $sigpart    = substr($key, $dotpos + 1);

        // Step 1: RSA-SHA256 signature verification with the embedded public key.
        $signature = base64_decode(strtr($sigpart, '-_', '+/'), true);
        $publickey = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(static::get_public_key(), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        if (
            $signature === false
                || !function_exists('openssl_verify')
                || openssl_verify($payloadb64, $signature, $publickey, OPENSSL_ALGO_SHA256) !== 1
        ) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        // Step 2: Decode payload.
        $payloadjson = base64_decode(strtr($payloadb64, '-_', '+/'), true);
        if ($payloadjson === false) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        $payload = json_decode($payloadjson, true);
        if (!is_array($payload) || empty($payload['wwwroot'])) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        // Step 3: wwwroot binding — exact match against $CFG->wwwroot.
        // WWWROOT_ANY ("*") matches any site. Those keys are issued only to
        // plugin reviewers, always carry an 'expires' date, and are tagged
        // edition:"reviewer" so a leaked one can be identified and left to lapse.
        global $CFG;
        $siteroot = rtrim($CFG->wwwroot, '/');
        $keyroot  = rtrim($payload['wwwroot'], '/');

        if ($keyroot !== self::WWWROOT_ANY && $siteroot !== $keyroot) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        // Step 4: Expiry check. Absent 'expires' means lifetime license.
        $expires = null;
        $edition = $payload['edition'] ?? null;

        if (!empty($payload['expires'])) {
            try {
                $expirydate = new \DateTime($payload['expires']);
                $expires     = $expirydate->format('Y-m-d');

                if (new \DateTime() > $expirydate) {
                    return (object) [
                        'status'  => self::STATUS_EXPIRED,
                        'expires' => $expires,
                        'edition' => $edition,
                    ];
                }
            } catch (\Exception $e) {
                return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
            }
        }

        return (object) [
            'status'  => self::STATUS_VALID,
            'expires' => $expires,
            'edition' => $edition,
        ];
    }
}
