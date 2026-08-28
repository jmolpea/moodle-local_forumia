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
 * AI client factory for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumia\api;

/**
 * Instantiates the AI client for the provider selected in plugin settings.
 *
 * When no provider is configured (sites upgraded from earlier versions),
 * OpenAI is used, preserving the original behaviour.
 */
class client_factory {
    /**
     * Creates the client for the currently configured provider.
     *
     * @return ai_client_base
     * @throws \moodle_exception If no license or API key is configured (thrown
     *                           by the client constructor).
     */
    public static function create(): ai_client_base {
        $provider = get_config('local_forumia', 'provider') ?: 'openai';

        switch ($provider) {
            case 'anthropic':
                return new anthropic_client();
            case 'gemini':
                return new gemini_client();
            case 'deepseek':
                return new deepseek_client();
            case 'openai':
            default:
                return new openai_client();
        }
    }
}
