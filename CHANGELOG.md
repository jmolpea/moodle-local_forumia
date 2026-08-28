# Changelog

All notable changes to `local_forumia` are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [Semantic Versioning](https://semver.org/).

## [1.7.1] — 2026-08-27

### Changed
- `$plugin->supported` widened to `[405, 502]`: **Moodle 4.5 LTS through 5.2**. Both ends of the range were verified by running the full test suite on a real installation — Moodle 4.5.10+ on PHP 8.2.30 and Moodle 5.2.2+ on PHP 8.3.33, both on MariaDB 10.11 — with 58 PHPUnit tests and 4 Behat scenarios green on each, from a clean install of an empty database. Moodle 5.0 and 5.1 were verified by API audit: every core function and class the plugin uses is present and unchanged in both.
- No plugin code changed for 5.x support. Nothing in the plugin's API surface was removed or altered across 5.0, 5.1 and 5.2, and all filesystem access already went through `$CFG->dirroot` and `$CFG->libdir`, so the `public/` webroot layout introduced in Moodle 5.1 needs no adaptation.

### Fixed
- Five PHPUnit tests reported errors on their first real run: the processor's guards emit `debugging()` when they fire, and the tests did not acknowledge it. They now assert the exact guard message, so a test proves *which* guard stopped the reply instead of only that no reply was published.
- `test_duplicate_event_does_not_produce_a_second_reply` was passing for the wrong reason. The per-user daily cap sits earlier in the guard chain than the deduplication check the test claims to cover, and was stopping the post first. The cap is now disabled in that test so the intended guard is exercised.
- The Behat feature enrolled the assistant account as a student, but the account selector only offers teachers, managers and the site default bot, so the option never appeared. It is now enrolled as a non-editing teacher, which is what the README recommends.

### Notes
- Under PHPUnit 11 (Moodle 5.x) the suite reports 7 deprecation notices for `@covers` and `@dataProvider` doc-comment metadata, which PHPUnit 12 will drop in favour of attributes. The annotations stay as they are: Moodle 4.5 ships PHPUnit 9, which does not support attributes at all, and 4.5 LTS remains the primary target.

## [1.7.0] — 2026-08-25

### Added
- 15-day full-feature trial on fresh installations — no licence key required.
- Licence key setting now states where to request a key (julio@rsmax.es), and the trial banner counts down the days remaining.
- Reviewer licence keys (`wwwroot: "*"`) for evaluation on any site.
- PHPUnit coverage for the licence validator, the processor guard chain, endpoint validation and the backup/restore round-trip.
- Behat scenario covering the per-forum settings flow.
- `$plugin->supported = [405, 405]`, declaring Moodle 4.5 LTS and nothing else. 5.x is untested and is deliberately not claimed.
- Privacy provider now declares `local_forumia_config` and its `bot_userid` field.

### Changed
- Product renamed to **Forumia – AI Forum Assistant** ("AI" rather than the Spanish "IA") across all interface strings.
- AI replies are now published through `mod_forum`'s own API with temporary session impersonation, restoring event dispatch, global search indexing and read-tracking.
- `README.md` rewritten: it previously described a `null_provider` that the plugin does not implement, and mentioned only one of the four supported AI providers.

### Fixed
- Course backups no longer lose seven configuration fields (`grading_prompt`, `inactivity_enabled`, `inactivity_days`, `inactivity_repeat_days`, `inactivity_prompt`, `inactivity_deadline`, `last_inactivity_post`). Restoring a backup silently reset them to defaults.
- Forum name is escaped with `format_string()` in the settings page heading.
- Word count on generated posts is now multibyte-safe — it under-counted in Spanish and Portuguese.

## [1.6.1] — 2026-08-05

### Fixed
- Moodle codechecker compliance: language string ordering and comment style.

## [1.6.0] — 2026-08-04

### Added
- Multi-provider support: Anthropic Claude, Google Gemini and DeepSeek alongside OpenAI.
- Provider-specific model selectors, with fields hidden for unselected providers.

### Changed
- HTTP client logic consolidated into `ai_client_base`, so the licence gate, SSRF endpoint validation, error handling and rate-limit pause behave identically for every provider.

## [1.5.2] — 2026-07-10

### Fixed
- GPT-5 family compatibility: `max_completion_tokens` instead of `max_tokens`, no `temperature` parameter, and a longer HTTP timeout for reasoning models.

## [1.5.0] — 2026-07-07

### Added
- Discussion reactivation: the assistant replies in open discussions after a configurable period without a human reply, with a minimum repeat interval and an optional deadline.
- Spanish language pack.

## [1.4.3] — 2026-04-08

### Fixed
- AI grading returned prose instead of JSON, so no grade was parsed. Provider-native JSON mode is now requested when grading is active.

## [1.4.2]

### Fixed
- Grade assignment ran before the reply was published, so a `grade_update()` error discarded the reply. Grading is now isolated in its own guard.

## [1.4.1]

### Fixed
- Incorrect `use` statement for `ai_client_base` in the observer made immediate and daily modes silently do nothing.

## [1.0.0] — 2025-03-10

### Added
- Initial release: immediate and daily response modes, per-forum configuration, rate limiting, disclaimer, offline RSA licence validation, backup and restore.
