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
 * Test double for the licence validator.
 *
 * @package    local_forumia
 * @category   test
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia;

/**
 * Validator that verifies against a public key supplied at runtime.
 *
 * Lets tests sign licence keys with a throwaway keypair instead of needing the
 * production private key, which by design does not exist in the repository.
 * Every other behaviour is inherited unchanged.
 */
class testable_validator extends \local_forumia\license\validator {
    /** @var string Base64 DER public key to verify against. */
    public static $publickeyoverride = '';

    /**
     * Returns the injected public key.
     *
     * @return string
     */
    protected static function get_public_key(): string {
        return self::$publickeyoverride !== '' ? self::$publickeyoverride : parent::get_public_key();
    }
}
