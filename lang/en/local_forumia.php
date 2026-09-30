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
 * English language strings for local_forumia.
 *
 * @package   local_forumia
 * @copyright 2025 RSMAX Consulting S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_notice'] = 'AI-generated reply, published automatically by Forumia. It was not written by a person.';
$string['assistant_disabled_body'] = 'Forumia has been disabled in the forum "{$a->forum}" (course "{$a->course}") because no designated AI assistant account is available. Designate one in Site administration > Plugins > Local plugins > Forumia ("Default site-wide AI assistant account"), or assign the capability local/forumia:actasassistant to a dedicated account at system level, and then re-enable the assistant in the forum settings: {$a->url}';
$string['assistant_disabled_subject'] = 'Forumia has been disabled in the forum "{$a}"';
$string['disclaimer_default'] = '_Read it critically and ask your teacher if anything is unclear._';
$string['error_apiunauthorized'] = 'The API key for the configured AI provider is invalid or lacks the required permissions. Forumia has been disabled site-wide. Please check your API key.';
$string['error_apiunauthorized_notification'] = 'The API key for the configured AI provider was rejected. Forumia has disabled itself site-wide to avoid repeated failed calls. Review the key in Site administration > Plugins > Local plugins > Forumia, then clear the global disable flag to resume.';
$string['error_botuser_inactive'] = 'The configured AI assistant account is inactive or deleted.';
$string['error_botuser_notdesignated'] = 'The account configured for forum {$a} is not an active, designated AI assistant account. Only the site default assistant account and users holding local/forumia:actasassistant at system level can publish AI replies.';
$string['error_dailylimit'] = 'Daily request limit reached for forum {$a}. The AI assistant will resume tomorrow.';
$string['error_endpoint_blocked'] = 'The configured API endpoint host is not in the list of allowed hosts for the selected AI provider. Update the endpoint in the Forumia global settings.';
$string['error_endpoint_https'] = 'The API endpoint must use HTTPS. Plain HTTP endpoints are not allowed.';
$string['error_grading_auto_nocapability'] = 'Automatic grading records you as the grader, so you need permission to grade this forum (mod/forum:grade).';
$string['error_grading_delay_invalid'] = 'Enter a number of hours between 1 and 720.';
$string['error_grading_unsupported'] = 'AI grading needs whole-forum grading with a point maximum. Set it in the forum settings (Grade > Whole forum grading); scales and advanced grading methods are not supported.';
$string['error_inactivity_days_invalid'] = 'The inactivity threshold must be at least 1 day.';
$string['error_inactivity_repeat_invalid'] = 'The interval between reactivation posts must be at least 1 day.';
$string['error_invalidforum'] = 'Invalid forum ID supplied.';
$string['error_loopdetected'] = 'Loop detected: post author is the AI assistant account. Skipping processing.';
$string['error_maxrequests_user_invalid'] = 'The per-user daily limit must be 0 (unlimited) or a positive integer.';
$string['error_noapikey'] = 'No API key configured for the selected AI provider. Please set it in the Forumia global settings.';
$string['error_nobotuser'] = 'No valid AI assistant account found for forum {$a}. The AI assistant has been disabled for this forum.';
$string['error_nolicense'] = 'Forumia has no valid licence for this site, so the assistant is disabled. Please contact your site administrator.';
$string['error_ratelimit'] = 'The AI provider rate limit was reached. Forumia will pause for one hour.';
$string['error_sitelimit'] = 'Site-wide daily API request limit reached. The AI assistant will resume tomorrow.';
$string['error_userlimit'] = 'Daily per-user response limit reached for user {$a}. The AI assistant will respond again to this user tomorrow.';
$string['forum_botuser'] = 'AI assistant account';
$string['forum_botuser_desc'] = 'Only accounts designated by a site administrator are listed: the site default assistant account and users who hold the capability local/forumia:actasassistant at system level. Course teachers and managers are never offered.';
$string['forum_botuser_none'] = 'No AI assistant account has been designated on this site yet. Ask a site administrator to set the default assistant account in the Forumia settings, or to grant local/forumia:actasassistant to a dedicated account.';
$string['forum_delay_response'] = 'Delay AI response by 1 hour';
$string['forum_delay_response_desc'] = 'When enabled, the AI response is queued and published approximately 1 hour after the student\'s post. Only applies in Immediate mode. Rate limits and availability are evaluated at the time the response is published, not when the post is created.';
$string['forum_delay_response_label'] = 'Wait 1 hour before publishing the AI reply';
$string['forum_disclaimer'] = 'Disclaimer text';
$string['forum_disclaimer_desc'] = 'Optional text added after the AI notice. Every AI reply is always labelled as AI-generated, whatever this field contains.';
$string['forum_enabled'] = 'Enable Forumia in this forum';
$string['forum_grading_delay'] = 'Hours before evaluating a student';
$string['forum_grading_delay_desc'] = 'Each student is evaluated only once, this many hours after their first post in the forum, looking at all their posts up to then. Posts made after the evaluation do not produce a new one. Default 12.';
$string['forum_grading_mode'] = 'AI grading';
$string['forum_grading_mode_auto'] = 'Automatic: apply the grade to students who have no grade yet';
$string['forum_grading_mode_desc'] = 'Requires whole-forum grading with a point maximum. The AI evaluates each student\'s whole participation once (see the delay below), distinguishing original contributions from replies to classmates. In "Suggest" mode a teacher accepts or discards each grade on the "Forumia: AI grade suggestions" page. In "Automatic" mode the grade is written straight away, but only for students without a grade, never replacing one, and it is recorded under the name of the teacher who switched automatic mode on; anything that cannot be applied is left on the same page for review. Suited to self-paced courses.';
$string['forum_grading_mode_off'] = 'Off';
$string['forum_grading_mode_suggest'] = 'Suggest: a teacher confirms each grade';
$string['forum_grading_prompt'] = 'Grading criteria (AI)';
$string['forum_grading_prompt_default'] = 'Assess the student\'s participation in the forum against these criteria and weights:

- **Relevance (30%)**: answers what was asked and stays on the forum topic.
- **Reasoning (30%)**: argues, justifies and draws on course content rather than stopping at opinion.
- **Depth and original contribution (25%)**: goes beyond the obvious, adding examples, nuance or connections of their own.
- **Interaction and clarity (15%)**: engages constructively with classmates and writes clearly and carefully.

GRADE REFERENCE
- 90 to 100% of the maximum: meets all four criteria convincingly and adds real value to the debate.
- 70 to 89%: sound, well-argued participation with room to go deeper.
- 50 to 69%: relevant but superficial or thinly reasoned.
- 25 to 49%: contributes little, is very brief, or drifts off topic.
- 0 to 24%: off topic, empty of content, or copied without elaboration.

RULES
- Judge only the student\'s own messages, and never weigh spelling above ideas.
- A short but sharp contribution can score highly: reward quality, not length or number of messages.
- Be consistent: equivalent participation gets equivalent marks.
- When torn between two marks, choose the higher one.';
$string['forum_grading_prompt_desc'] = 'Criteria the AI uses to evaluate a student\'s participation. Leave empty to use the default criteria. The AI proposes a whole number between 0 and the forum maximum grade, with a short justification for teachers.';
$string['forum_grading_prompt_placeholder'] = 'Grade the student\'s post from 0 to {max}. Criteria: accuracy 40%, clarity 30%, depth 30%. Penalise off-topic answers.';
$string['forum_inactivity_days'] = 'Days of inactivity before posting';
$string['forum_inactivity_days_desc'] = 'Number of consecutive days without a human reply in a discussion before the AI assistant replies to reactivate it. Minimum 1 day. The assistant\'s own replies do not reset this counter.';
$string['forum_inactivity_deadline'] = 'Reactivation deadline';
$string['forum_inactivity_deadline_desc'] = 'Date after which the AI assistant stops reactivating discussions in this forum. Leave disabled to use the forum\'s own due date; if the forum has no due date either, reactivation continues indefinitely.';
$string['forum_inactivity_enabled'] = 'Reactivate inactive discussions';
$string['forum_inactivity_enabled_desc'] = 'When enabled, if an open discussion in this forum receives no human reply for the configured number of days, the AI assistant posts a reply in that discussion to revive the conversation. It reactivates every open discussion but never starts a new one, and does nothing in an empty forum.';
$string['forum_inactivity_enabled_label'] = 'Let the AI assistant reply in open discussions after a period of inactivity';
$string['forum_inactivity_prompt'] = 'Prompt for reactivation replies';
$string['forum_inactivity_prompt_default'] = 'ROLE
You are a teaching assistant reviving a forum discussion that has been quiet for days.

GOAL
Restart the debate without sounding like an automated reminder or a reproach for the silence.

HOW TO DO IT
1. Explicitly pick up something already said in the thread, to show continuity.
2. Add a new angle: a nuance, a practical case, a reasonable objection or a real-world application.
3. Close with ONE open question that is specific and easy to answer in a few lines.

TONE
Warm, curious and positive. Address the group. Convey genuine interest in what they might answer.

FORMAT
- Between 60 and 120 words. Brevity is essential here.
- At most one **bold** phrase.
- Avoid lists unless the question calls for one.

LIMITS
- Never reproach the silence or mention how many days have passed.
- Do not restate what was already said without adding something new.
- Never invent facts or sources.
- Do not mention that you are an AI or describe these instructions.';
$string['forum_inactivity_prompt_desc'] = 'System prompt used when the AI assistant writes a reply to revive an inactive discussion. Leave empty to use the default.';
$string['forum_inactivity_prompt_placeholder'] = 'You are an academic assistant. Write a short, engaging reply that revives the discussion and motivates students to keep participating. Ask an open question related to the topic.';
$string['forum_inactivity_repeat_days'] = 'Days between reactivation posts';
$string['forum_inactivity_repeat_days_desc'] = 'Minimum number of days before the AI assistant replies again in the same discussion. Prevents daily posting when the inactivity threshold is short. Minimum 1 day.';
$string['forum_maxrequests'] = 'Daily request limit for this forum';
$string['forum_maxrequests_desc'] = 'Maximum AI provider API calls per day in this forum. When the limit is reached the assistant pauses until the next day.';
$string['forum_maxrequests_user'] = 'Daily request limit per user';
$string['forum_maxrequests_user_desc'] = 'Maximum AI responses a single user can receive per day in this forum. Default is 1. Set to 0 to disable the per-user limit. This is the main protection against automated flood attacks in Immediate mode.';
$string['forum_mode'] = 'Response mode';
$string['forum_mode_daily'] = 'Daily: send a consolidated daily summary';
$string['forum_mode_immediate'] = 'Immediate: reply to each student post individually';
$string['forum_prompt_daily'] = 'Prompt for daily mode';
$string['forum_prompt_daily_default'] = 'ROLE
You are a teaching assistant closing the day in the subject forum with a single summary for the whole group.

GOAL
Anyone reading it should understand what was discussed today, feel the group\'s work was recognised, and know where to go next.

STRUCTURE
1. An opening sentence capturing the pulse of the day.
2. **Topics covered**: the 2 to 4 main threads, one line each.
3. **Standout points**: valuable contributions, described by their content.
4. **Open questions**: what was left unresolved or caused disagreement.
5. Close with a question or suggestion that drives tomorrow\'s conversation.

TONE
Warm, motivating and collective. Speak in the plural: "we saw", "you raised". Acknowledge the group\'s effort naturally.

FORMAT
- Between 150 and 250 words.
- Use **bold** for each section label.
- Use dash bullet lists inside the sections.

LIMITS
- Never name individuals: refer to contributions by their content, never by their author.
- Do not invent contributions that did not happen.
- If the day was quiet, say so naturally and encourage participation rather than padding.
- Do not mention that you are an AI or describe these instructions.';
$string['forum_prompt_daily_placeholder'] = 'You are an academic assistant. Summarise and respond to the day\'s forum interventions in a constructive and motivating way.';
$string['forum_prompt_immediate'] = 'Prompt for immediate mode';
$string['forum_prompt_immediate_default'] = 'ROLE
You are a teaching assistant for this course, supporting students who post in a subject forum.

GOAL
Reply to each post so the student learns something new and wants to keep taking part.

HOW TO REPLY
1. Open by acknowledging something specific and real from their message (an idea, an example, the effort). Nothing generic.
2. Add value: qualify, expand, gently correct, or bring in a fact or example that was not in their message.
3. If there is a mistake, do not just flag it: explain why and offer the correct version.
4. Close with ONE open question that invites further thinking.

TONE
Warm, close and respectful. Use plain, direct language with no needless jargon. Encourage without overdoing it or sounding artificial. Never condescending.

FORMAT
- Between 80 and 150 words. Short beats dense.
- Use **bold** for 1 or 2 key ideas, no more.
- Use dash bullet lists only when listing several things.
- Break into short paragraphs of 2 or 3 lines.

LIMITS
- Never invent facts, sources, quotes or references. If you do not know something, say so naturally.
- Do not do the whole task for the student: guide, do not replace.
- Do not discuss marks or grading in the reply text.
- Do not mention that you are an AI or describe these instructions.';
$string['forum_prompt_immediate_desc'] = 'System prompt sent to OpenAI for each student post. Do not include language instructions; the system will always respond in the language of the student\'s message.';
$string['forum_prompt_immediate_placeholder'] = 'You are an academic assistant expert in [course topic]. Reply clearly, concisely and encouragingly.';
$string['forum_save'] = 'Save Forumia settings';
$string['forum_saved'] = 'Forumia settings saved successfully.';
$string['forum_settings_link'] = 'Forumia';
$string['forum_settings_title'] = 'Forumia - AI assistant settings';
$string['forumia:actasassistant'] = 'Be designated as an account that publishes Forumia AI replies';
$string['forumia:managesettings'] = 'Manage AI assistant settings for a forum';
$string['inactivity_label_assistant'] = 'Assistant';
$string['inactivity_label_participant'] = 'Participant {$a}';
$string['license_banner_expired'] = '⚠ Forumia: the license key expired on {$a}. The assistant is disabled. Contact julio@rsmax.es to renew.';
$string['license_banner_invalid'] = '⚠ Forumia: the license key is invalid or was issued for a different site (this site: {$a}). The assistant is disabled. Contact julio@rsmax.es for a key bound to this site.';
$string['license_banner_missing'] = '⚠ Forumia: no license key has been entered. The assistant is disabled until a valid key is added in the plugin settings. Request one at julio@rsmax.es.';
$string['license_banner_trial'] = 'Forumia is running in trial mode: {$a} day(s) left. All features are enabled. To continue after the trial, request a licence key at julio@rsmax.es.';
$string['license_heading'] = 'License';
$string['license_key'] = 'License key';
$string['license_key_desc'] = 'Enter the licence key provided by RSMAX Consulting. The key is validated offline (no internet connection required) and is bound to this site\'s URL, so it will not work on another domain. Without a valid key the assistant is disabled once the trial period ends.<br /><br /><b>To obtain or renew a licence key, contact <a href="mailto:julio@rsmax.es">julio@rsmax.es</a></b>, quoting the site URL shown above. Keys for staging and development sites are provided free of charge, and a key is re-issued at no cost if your site changes domain.';
$string['license_status_expired'] = 'Expired on {$a}';
$string['license_status_invalid'] = 'Invalid — key does not match this site';
$string['license_status_missing'] = 'Not configured';
$string['license_status_trial'] = 'Trial - {$a} day(s) remaining';
$string['license_status_valid'] = 'Valid — expires {$a}';
$string['license_status_valid_lifetime'] = 'Valid — lifetime license';
$string['messageprovider:api_error'] = 'Forumia: the AI provider rejected the API key';
$string['messageprovider:assistant_disabled'] = 'Forumia: the assistant was disabled in a forum';
$string['pluginname'] = 'Forumia - AI Forum Assistant';
$string['privacy:forumroles'] = 'Forumia roles in this forum';
$string['privacy:gradesuggestion'] = 'AI grade suggestion';
$string['privacy:metadata:ai_provider_api'] = 'Forumia sends forum content to the external AI provider configured by the site administrator (OpenAI, Anthropic, Google Gemini or DeepSeek) to generate replies and, when enabled, to evaluate participation. Names, email addresses and user IDs are never sent, but post text may contain personal information written by its authors. Immediate mode sends the student post being answered and the forum description. Daily mode sends the student posts of the last 24 hours. Reactivation mode sends the forum name, forum description, discussion subject and recent posts by every participant in the discussion, teachers included. AI grading sends the forum description and all of one student\'s posts with their discussion subjects and, for each reply, the message it answers, which may have been written by a classmate or a teacher. Other authors are replaced by anonymous labels or referred to by role.';
$string['privacy:metadata:ai_provider_api:discussion_subject'] = 'The subject of a discussion (reactivation mode and AI grading).';
$string['privacy:metadata:ai_provider_api:forum_intro'] = 'The forum description, stripped of HTML and truncated (immediate mode, reactivation mode and AI grading).';
$string['privacy:metadata:ai_provider_api:forum_name'] = 'The forum name (reactivation mode).';
$string['privacy:metadata:ai_provider_api:forum_post_content'] = 'The plain-text body of forum posts, stripped of HTML and truncated: the student post being answered (immediate mode), student posts from the last 24 hours (daily mode), the recent posts of a discussion by any participant, teachers included (reactivation mode), or all of one student\'s posts plus the messages they replied to, by classmates or teachers (AI grading).';
$string['privacy:metadata:config'] = 'Per-forum configuration for Forumia: prompts, response mode, limits, disclaimer and reactivation settings.';
$string['privacy:metadata:config:bot_userid'] = 'The ID of the user account that publishes the assistant\'s replies in the forum. Only an account designated by a site administrator can be used.';
$string['privacy:metadata:config:grading_userid'] = 'The ID of the teacher who switched automatic AI grading on in the forum. Automatic grades are recorded in the gradebook under this teacher.';
$string['privacy:metadata:suggestion'] = 'One AI evaluation of a student\'s participation per forum. In suggest mode it is not a grade until a teacher accepts it.';
$string['privacy:metadata:suggestion:grade'] = 'The grade proposed by the AI.';
$string['privacy:metadata:suggestion:grademax'] = 'The forum maximum grade at the time of the evaluation.';
$string['privacy:metadata:suggestion:postcount'] = 'How many of the student\'s posts were assessed.';
$string['privacy:metadata:suggestion:rationale'] = 'The AI\'s short justification of the grade, shown to teachers only.';
$string['privacy:metadata:suggestion:status'] = 'Whether the evaluation is pending, accepted, discarded, applied automatically or failed.';
$string['privacy:metadata:suggestion:timecreated'] = 'When the evaluation was made.';
$string['privacy:metadata:suggestion:timemodified'] = 'When the evaluation was last updated.';
$string['privacy:metadata:suggestion:userid'] = 'The student evaluated.';
$string['privacy:status0'] = 'Pending review';
$string['privacy:status1'] = 'Accepted by a teacher';
$string['privacy:status2'] = 'Discarded by a teacher';
$string['privacy:status3'] = 'Applied automatically';
$string['privacy:status4'] = 'The AI could not grade';
$string['settings_anthropic_apikey'] = 'Anthropic API Key';
$string['settings_anthropic_apikey_desc'] = 'Your Anthropic (Claude) API key. This value is stored encrypted and never exposed in logs or error pages.';
$string['settings_anthropic_model'] = 'Anthropic Model';
$string['settings_apikey'] = 'OpenAI API Key';
$string['settings_apikey_desc'] = 'Your OpenAI API key. This value is stored encrypted and never exposed in logs or error pages.';
$string['settings_deepseek_apikey'] = 'DeepSeek API Key';
$string['settings_deepseek_apikey_desc'] = 'Your DeepSeek API key. This value is stored encrypted and never exposed in logs or error pages.';
$string['settings_deepseek_model'] = 'DeepSeek Model';
$string['settings_defaultbot'] = 'Default site-wide AI assistant account';
$string['settings_defaultbot_desc'] = 'Username or user ID of a dedicated Moodle account that publishes the assistant\'s replies when a forum has no account of its own. Create this account manually for the assistant; do not use the account of a real person. You can designate further assistant accounts by granting them local/forumia:actasassistant at system level. Only these accounts can be chosen in the forum settings.';
$string['settings_endpoint'] = 'API Endpoint';
$string['settings_endpoint_desc'] = 'Full URL of the OpenAI chat completions endpoint. Must use HTTPS and point to an allowed host (api.openai.com or *.openai.azure.com). Change only if you are using a supported compatible endpoint.';
$string['settings_gemini_apikey'] = 'Google Gemini API Key';
$string['settings_gemini_apikey_desc'] = 'Your Google Gemini API key. This value is stored encrypted and never exposed in logs or error pages.';
$string['settings_gemini_model'] = 'Gemini Model';
$string['settings_heading'] = 'Forumia – global settings';
$string['settings_heading_desc'] = 'Configure the global AI provider settings. These values apply site-wide unless overridden at forum level.';
$string['settings_model'] = 'OpenAI Model';
$string['settings_model_desc'] = 'The model to use for generating responses.';
$string['settings_provider'] = 'AI Provider';
$string['settings_provider_desc'] = 'The AI provider used to generate responses. Configure the API key and model for the selected provider below. Keys for other providers are kept, so you can switch providers without re-entering them.';
$string['settings_siteratelimit'] = 'Enable Site-wide Rate Limit';
$string['settings_siteratelimit_desc'] = 'Limit the total number of AI API calls per day across the entire site.';
$string['settings_siteratelimit_max'] = 'Maximum requests per day (site-wide)';
$string['suggestions_accept'] = 'Accept';
$string['suggestions_acceptall'] = 'Accept all suggestions';
$string['suggestions_acceptall_confirm'] = 'Apply every pending suggestion as the students\' whole-forum grade, under your name? Existing grades of those students will be replaced.';
$string['suggestions_accepted'] = 'The grade has been applied.';
$string['suggestions_acceptedall'] = 'Grades applied: {$a->applied}. Could not be applied: {$a->failed}.';
$string['suggestions_actions'] = 'Actions';
$string['suggestions_current'] = 'Current grade';
$string['suggestions_discard'] = 'Discard';
$string['suggestions_discarded'] = 'The suggestion has been discarded. No grade was changed.';
$string['suggestions_error_advanced'] = 'This forum uses an advanced grading method, so an AI suggestion cannot be applied. Grade the student in the forum grader.';
$string['suggestions_error_invalid'] = 'The suggested grade is not valid for the forum\'s current maximum grade. Discard it and grade the student in the forum grader.';
$string['suggestions_error_notpending'] = 'This evaluation is no longer pending.';
$string['suggestions_failed'] = 'The AI could not grade this student. Grade them in the forum grader.';
$string['suggestions_intro'] = 'Each student is evaluated once, looking at all their posts in the forum at that moment. These evaluations have not been applied. Accepting one writes it as the student\'s whole-forum grade, and to the gradebook, under your name, replacing any grade the student already has. Discarding one changes nothing, and the student is not evaluated again. In automatic mode, this page only lists evaluations that could not be applied on their own.';
$string['suggestions_link'] = 'Forumia: AI grade suggestions';
$string['suggestions_nograde'] = 'Not graded';
$string['suggestions_none'] = 'There are no AI evaluations waiting for review in this forum.';
$string['suggestions_posts'] = 'Posts assessed';
$string['suggestions_rationale'] = 'AI justification';
$string['suggestions_student'] = 'Student';
$string['suggestions_suggested'] = 'Suggested grade';
$string['suggestions_title'] = 'AI grade suggestions';
$string['task_daily_name'] = 'Forumia – Daily Summary';
$string['task_delayed_response_name'] = 'Forumia – AI reply';
$string['task_grading_name'] = 'Forumia – AI grading';
$string['task_inactivity_name'] = 'Forumia – Inactivity Check';
