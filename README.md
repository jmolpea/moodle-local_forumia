# Forumia — AI Forum Assistant for Moodle™

[![Moodle 4.5 – 5.2](https://img.shields.io/badge/Moodle-4.5%20LTS%20%E2%80%93%205.2-orange)](https://moodle.org) [![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net) [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-green)](LICENSE)

**Component:** `local_forumia` · **By:** [Pluginia](https://pluginia.es), a trading name of RSMAX Consulting S.L.

Forumia replies to student forum posts with AI, grades them against your own criteria, and revives discussions that have gone quiet. It posts as a real Moodle user, in the forums you choose, under limits you control.

---

## What it does

| | |
|---|---|
| **Immediate replies** | Answers each student post using your prompt and the forum's own description as context. Optionally delayed by one hour so students get first crack at answering each other. |
| **AI grading** | When whole-forum grading is enabled, scores each post 0–max against a rubric you write, and writes the grade to the Moodle gradebook. The grade never appears in the text the student reads. |
| **Discussion reactivation** | After N days without a human reply, posts a short reply into the thread that picks up an existing point, adds an angle and asks one open question. Never starts empty threads. Stops at the forum due date. |
| **Daily digest** | One consolidated reply per day covering the last 24 hours of posts, with authorship anonymised. |
| **Four AI providers** | OpenAI (incl. Azure), Anthropic Claude, Google Gemini, DeepSeek. Bring your own key. |
| **Spend control** | Independent daily ceilings per user, per forum and site-wide. Auto-pause on provider rate limits; site-wide auto-disable and admin notification on auth failure. |
| **Backup & restore** | Per-forum configuration travels with course backups. |

---

## Requirements

| | |
|---|---|
| Moodle | 4.5 LTS through 5.2. Test suite run on 4.5.10+ and 5.2.2+; 5.0 and 5.1 verified by API audit |
| PHP | 8.2 or later |
| PHP extensions | `openssl`, `curl` |
| Moodle core | `mod_forum` |
| Database | MySQL / MariaDB / PostgreSQL |
| External service | An API key for OpenAI, Anthropic, Google Gemini or DeepSeek |
| Cron | Required for daily-digest and reactivation modes |

---

## Installation

**Via the browser (recommended)**

1. **Site administration → Plugins → Install plugins**
2. Drop the ZIP in, leave the plugin type as *Detected*, install, and complete the database upgrade.

**From the server**

1. Unzip and copy the `forumia` folder to `<moodleroot>/local/forumia/`.
2. Visit **Site administration → Notifications**.

> The folder must be named exactly `forumia`.

Full setup — creating the assistant account, configuring the provider, enabling it in a forum — is in the [documentation](https://pluginia.es/forumia/docs).

---

## Licensing and pricing

The **software is GPLv3**: you get complete, unobfuscated source code and the full rights the GPL grants you.

The **commercial subscription** — sold through [Moodle Marketplace](https://marketplace.moodle.com) — covers a licence key for one production instance, one year of updates including Moodle compatibility releases, and support in English, Spanish and Portuguese.

**New installations run with all features enabled for 15 days**, with no key and no payment details. Install it and see whether it earns its place.

**To obtain or renew a licence key, contact julio@rsmax.es**, quoting your site URL. Staging and development keys are free on request, and a key is re-issued at no cost if your site changes domain.

Licence verification is an **offline RSA signature check** performed inside your own server. Forumia makes no network call to us in any code path: no telemetry, no phone-home, no usage reporting.

---

## Privacy

**Leaves your site:** plain-text forum post bodies (HTML stripped, truncated) and the forum description, sent over HTTPS to the AI provider *you* configured with *your* API key. In daily-digest and reactivation modes, authorship is replaced by anonymous sequential labels first.

**Never leaves your site:** names, usernames, email addresses, user IDs, IP addresses, course identifiers, grades.

**Stored by the plugin:** per-forum configuration (including the user ID of the designated assistant account) and a daily counter of API calls per forum. No message content, no student identities.

All declared through Moodle's Privacy API. Administrators should confirm their institution's data-processing agreements cover the transfer of forum content to the chosen provider, and reflect it in the site privacy notice.

---

## Capabilities

| Capability | Default roles | Purpose |
|---|---|---|
| `local/forumia:managesettings` | editingteacher, manager | Configure Forumia for a forum |
| `local/forumia:viewdisclaimer` | all authenticated users | See the AI disclaimer on generated posts |

---

## Support

- **Issues:** [GitHub Issues](https://github.com/pluginia/moodle-local_forumia/issues)
- **Licences and commercial support:** julio@rsmax.es
- **Languages:** English, Spanish, Portuguese
- **First response:** two business days (Mon–Fri, CET)

---

## Contributing

Bug reports and pull requests are welcome. Code must pass `moodle-plugin-ci` (codechecker, phpdoc, phpunit, behat) against Moodle 4.5 LTS and 5.2 on both MySQL and PostgreSQL.

---

## Licence

GNU General Public License v3 or later — see [LICENSE](LICENSE).

© 2025–2026 RSMAX Consulting S.L. Pluginia is a trading name of RSMAX Consulting S.L.

*Moodle™ is a registered trademark of Moodle Pty Ltd. RSMAX Consulting S.L. is not affiliated with or endorsed by Moodle Pty Ltd.*
