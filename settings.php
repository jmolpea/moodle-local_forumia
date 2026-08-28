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
 * Global admin settings page for local_forumia.
 *
 * Accessible at: Site administration > Plugins > Local > Forum IA Assistant
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_forumia',
        get_string('pluginname', 'local_forumia')
    );

    // 0. License.
    $settings->add(new admin_setting_heading(
        'local_forumia/license_heading',
        get_string('license_heading', 'local_forumia'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_forumia/license_key',
        get_string('license_key', 'local_forumia'),
        get_string('license_key_desc', 'local_forumia'),
        '',
        PARAM_RAW_TRIMMED
    ));

    // License status indicator — computed inline at render time (offline, no DB hit).
    $licenseresult = \local_forumia\license\validator::get_settings_status();
    $settings->add(new admin_setting_heading(
        'local_forumia/license_status_display',
        '',
        html_writer::tag('span', $licenseresult['text'], ['class' => $licenseresult['css']])
    ));

    // Heading.
    $settings->add(new admin_setting_heading(
        'local_forumia/heading',
        get_string('settings_heading', 'local_forumia'),
        get_string('settings_heading_desc', 'local_forumia')
    ));

    // 1. AI provider.
    $providers = [
        'openai'    => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini'    => 'Google Gemini',
        'deepseek'  => 'DeepSeek',
    ];
    $settings->add(new admin_setting_configselect(
        'local_forumia/provider',
        get_string('settings_provider', 'local_forumia'),
        get_string('settings_provider_desc', 'local_forumia'),
        'openai',
        $providers
    ));

    // 2a. OpenAI API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_forumia/apikey',
        get_string('settings_apikey', 'local_forumia'),
        get_string('settings_apikey_desc', 'local_forumia'),
        ''
    ));
    $settings->hide_if('local_forumia/apikey', 'local_forumia/provider', 'neq', 'openai');

    // 2b. OpenAI Model.
    // Only models verified against the current API are listed. A new model is
    // added in the release that has actually been tested with it — an
    // unrecognised identifier in this list is a support ticket waiting to happen.
    $modeloptions = [
        'gpt-5.6-luna' => 'GPT-5.6 Luna (default)',
        'gpt-5.1'      => 'GPT-5.1',
        'gpt-5-mini'   => 'GPT-5 mini',
        'gpt-5-nano'   => 'GPT-5 nano',
        'gpt-4.1-mini' => 'GPT-4.1 mini',
        'gpt-4.1-nano' => 'GPT-4.1 nano (most economical)',
        'gpt-4o-mini'  => 'GPT-4o mini',
        'gpt-4o'       => 'GPT-4o',
    ];
    $settings->add(new admin_setting_configselect(
        'local_forumia/model',
        get_string('settings_model', 'local_forumia'),
        get_string('settings_model_desc', 'local_forumia'),
        'gpt-5.6-luna',
        $modeloptions
    ));
    $settings->hide_if('local_forumia/model', 'local_forumia/provider', 'neq', 'openai');

    // 2c. OpenAI API Endpoint (also supports Azure OpenAI).
    $settings->add(new admin_setting_configtext(
        'local_forumia/endpoint',
        get_string('settings_endpoint', 'local_forumia'),
        get_string('settings_endpoint_desc', 'local_forumia'),
        'https://api.openai.com/v1/chat/completions',
        PARAM_URL
    ));
    $settings->hide_if('local_forumia/endpoint', 'local_forumia/provider', 'neq', 'openai');

    // 3a. Anthropic API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_forumia/anthropic_apikey',
        get_string('settings_anthropic_apikey', 'local_forumia'),
        get_string('settings_anthropic_apikey_desc', 'local_forumia'),
        ''
    ));
    $settings->hide_if('local_forumia/anthropic_apikey', 'local_forumia/provider', 'neq', 'anthropic');

    // 3b. Anthropic Model.
    $anthropicmodels = [
        'claude-haiku-4-5'  => 'Claude Haiku 4.5 (recommended — most economical)',
        'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
        'claude-sonnet-5'   => 'Claude Sonnet 5',
        'claude-opus-4-8'   => 'Claude Opus 4.8',
    ];
    $settings->add(new admin_setting_configselect(
        'local_forumia/anthropic_model',
        get_string('settings_anthropic_model', 'local_forumia'),
        get_string('settings_model_desc', 'local_forumia'),
        'claude-haiku-4-5',
        $anthropicmodels
    ));
    $settings->hide_if('local_forumia/anthropic_model', 'local_forumia/provider', 'neq', 'anthropic');

    // 4a. Gemini API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_forumia/gemini_apikey',
        get_string('settings_gemini_apikey', 'local_forumia'),
        get_string('settings_gemini_apikey_desc', 'local_forumia'),
        ''
    ));
    $settings->hide_if('local_forumia/gemini_apikey', 'local_forumia/provider', 'neq', 'gemini');

    // 4b. Gemini Model.
    $geminimodels = [
        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite (recommended — most economical)',
        'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
        'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
    ];
    $settings->add(new admin_setting_configselect(
        'local_forumia/gemini_model',
        get_string('settings_gemini_model', 'local_forumia'),
        get_string('settings_model_desc', 'local_forumia'),
        'gemini-2.5-flash',
        $geminimodels
    ));
    $settings->hide_if('local_forumia/gemini_model', 'local_forumia/provider', 'neq', 'gemini');

    // 5a. DeepSeek API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_forumia/deepseek_apikey',
        get_string('settings_deepseek_apikey', 'local_forumia'),
        get_string('settings_deepseek_apikey_desc', 'local_forumia'),
        ''
    ));
    $settings->hide_if('local_forumia/deepseek_apikey', 'local_forumia/provider', 'neq', 'deepseek');

    // 5b. DeepSeek Model.
    $deepseekmodels = [
        'deepseek-chat'     => 'DeepSeek Chat (recommended)',
        'deepseek-reasoner' => 'DeepSeek Reasoner',
    ];
    $settings->add(new admin_setting_configselect(
        'local_forumia/deepseek_model',
        get_string('settings_deepseek_model', 'local_forumia'),
        get_string('settings_model_desc', 'local_forumia'),
        'deepseek-chat',
        $deepseekmodels
    ));
    $settings->hide_if('local_forumia/deepseek_model', 'local_forumia/provider', 'neq', 'deepseek');

    // 4. Site-wide rate limit.
    $settings->add(new admin_setting_configcheckbox(
        'local_forumia/siteratelimit_enabled',
        get_string('settings_siteratelimit', 'local_forumia'),
        get_string('settings_siteratelimit_desc', 'local_forumia'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_forumia/siteratelimit_max',
        get_string('settings_siteratelimit_max', 'local_forumia'),
        '',
        100,
        PARAM_INT
    ));

    // 5. Per-user rate limit.
    $settings->add(new admin_setting_configcheckbox(
        'local_forumia/userratelimit_enabled',
        get_string('settings_userratelimit', 'local_forumia'),
        get_string('settings_userratelimit_desc', 'local_forumia'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_forumia/userratelimit_max',
        get_string('settings_userratelimit_max', 'local_forumia'),
        '',
        10,
        PARAM_INT
    ));

    // 6. Daily summary hour.
    $houroptions = [];
    for ($h = 0; $h <= 23; $h++) {
        $label = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
        $houroptions[$h] = $label;
    }
    $settings->add(new admin_setting_configselect(
        'local_forumia/dailyhour',
        get_string('settings_dailyhour', 'local_forumia'),
        get_string('settings_dailyhour_desc', 'local_forumia'),
        8,
        $houroptions
    ));

    // 7. Default site IA user.
    // PARAM_NOTAGS strips any HTML/script tags while preserving all valid
    // Moodle username characters (letters, digits, dots, hyphens, @, etc.).
    // PARAM_RAW_TRIMMED was too permissive — it would allow HTML injection
    // into the stored value. The field is further sanitised at read time
    // via clean_param($setting, PARAM_USERNAME) in resolve_default_bot_userid().
    $settings->add(new admin_setting_configtext(
        'local_forumia/defaultbot',
        get_string('settings_defaultbot', 'local_forumia'),
        get_string('settings_defaultbot_desc', 'local_forumia'),
        '',
        PARAM_NOTAGS
    ));

    // Register under the Local plugins category.
    $ADMIN->add('localplugins', $settings);
}
