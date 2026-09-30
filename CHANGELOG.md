# Changelog

All notable changes to `local_forumia` are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [Semantic Versioning](https://semver.org/).

## [1.8.0] — 2026-09-30

Changes requested in the Moodle Marketplace review (MMRT-215).

### Security
- **The assistant never publishes under the name of a real teacher or manager.** The account it posts from must now be designated by a site administrator: the site default assistant account, or a user holding the new capability `local/forumia:actasassistant` at system level (granted to no role by default). The per-forum selector offers only those accounts, and the form re-checks the choice on the server.
- **No automatic fallback to course staff.** When the configured account is missing, suspended or no longer designated, the assistant falls back to the site default account; if there is none, it disables itself in that forum and notifies the site administrators (new message provider `assistant_disabled`).
- **Every AI reply is labelled as AI-generated** with a fixed notice that teachers cannot remove. The per-forum disclaimer is now optional extra text shown after it.

### Changed
- **AI grading is redesigned, and it is an explicit per-forum opt-in** (*Off* by default):
  - It is no longer tied to replies. A new hourly scheduled task (`grading_task`) evaluates each student **once per forum**, *N* hours after their first post (`grading_delay`, 12 by default), using all of their posts so far. Each post is labelled as an original contribution, a reply to a classmate, to a teacher or to the assistant, or a follow-up; for replies, the message being answered is included as context only. Original contributions weigh more than replies to classmates.
  - One evaluation per student and forum is enforced by a unique index, and evaluations are kept with a status (pending, accepted, discarded, applied, failed) instead of being deleted, so later posts, re-runs or discards never produce a new draft grade. A provider outage writes nothing and is retried; an unusable answer is stored as failed and not retried.
  - **Suggest** mode: a user with `mod/forum:grade` accepts or discards each grade, or all at once, on the new *Forumia: AI grade suggestions* page, which also shows the AI's short justification and the student's current grade.
  - **Automatic** mode (for self-paced courses): the grade is applied only to students without a grade and never replaces one; students who already have a grade are not even sent to the provider. It is recorded in the gradebook under the teacher who switched automatic mode on, who must hold `mod/forum:grade`. If that teacher is no longer able to grade, the evaluation stays pending for review instead.
  - Grades are always written through `mod_forum`'s grading API (`core_grades\component_gradeitem`). Forums using a scale or an advanced grading method cannot enable AI grading.
- **Upgrading switches AI grading off in every forum**, including those that kept the old pre-filled grading prompt, because nobody explicitly opted in to the new behaviour.
- **Immediate mode never calls the AI provider inside the student's request.** The observer now always queues an adhoc task (with a one-hour delay when that option is on) and cron makes the call. Duplicate events for the same post queue a single task. A task that fails on configuration (no API key, blocked endpoint) logs and finishes instead of being retried indefinitely.
- The grade in the AI's answer is accepted only from a well-formed JSON object, as a number within 0..max. The fallback that scraped `"grade": N` from free text is gone, and out-of-range values are rejected rather than clamped.
- Immediate-mode replies no longer ask the AI for a grade at all.
- Privacy provider rewritten: it now declares every field sent to the AI provider (post text, forum name, forum description, discussion subject), states that reactivation mode sends posts by every participant including teachers, and reports, exports and deletes both the assistant account link (`bot_userid`) and the new grade suggestions.
- The site-wide rate limit is described correctly as a **daily** cap (the strings said "per hour").

### Removed
- Settings that no code ever read: `userratelimit_enabled`, `userratelimit_max` (the per-user limit is the per-forum *Daily request limit per user*) and `dailyhour` (the daily digest time is the schedule of its scheduled task). Their stored values are deleted on upgrade.
- Capability `local/forumia:viewdisclaimer`, which no code checked.

### Fixed
- Missing language string `messageprovider:api_error`.
- The previous grading code wrote to `forum_grades` with `itemnumber = 0`, which is the ratings slot; whole-forum grades live in `itemnumber = 1`. Replaced by the grading API.

### Tests
- 94 PHPUnit tests (was 58) and 9 Behat scenarios (was 4), green on Moodle 4.5.10+ (PHP 8.2) and 5.2.2+ (PHP 8.3). New coverage: task queueing and delay, designated-account rules and the disable-and-notify path, the mandatory AI notice, strict grade parsing, the one-evaluation-per-student rule, the context sent for replies to classmates, suggest and automatic modes (including `usermodified` and never overwriting a grade), and the privacy provider. A fake AI client can be injected in unit tests (`client_factory::set_test_client()`, PHPUnit only).

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
