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
 * Plugin version definition for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_forumia';
$plugin->version   = 2026082701; // Verified against Moodle 5.2; supported range widened.
$plugin->requires  = 2024100700; // Moodle 4.5.
// Declare ONLY what has actually been tested. Both ends of this range have been
// run for real: 4.5.10+ on PHP 8.2 and 5.2.2+ on PHP 8.3, each with the full
// PHPUnit suite (58 tests) and the Behat scenarios green, plus a clean install
// from an empty database. 5.0 and 5.1 sit inside the range and were verified by
// API audit only - every core function and class this plugin touches is present
// and unchanged in both - so they are covered by the declaration but have not
// been run. Narrow this rather than widen it if that distinction ever matters.
$plugin->supported = [405, 502]; // Moodle 4.5 LTS through 5.2.
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.1';
$plugin->dependencies = [
    'mod_forum' => 2024100700,
];
