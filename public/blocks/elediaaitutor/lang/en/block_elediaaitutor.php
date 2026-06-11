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
 * English language strings for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'eLeDia.ai Tutor';

// Capabilities.
$string['elediaaitutor:addinstance'] = 'Add a new eLeDia.ai Tutor block';
$string['elediaaitutor:myaddinstance'] = 'Add a new eLeDia.ai Tutor block to the Dashboard';
$string['elediaaitutor:use'] = 'Use the eLeDia.ai Tutor chat';
$string['elediaaitutor:manage'] = 'Manage the eLeDia.ai Tutor configuration';
$string['elediaaitutor:viewhistory'] = 'View eLeDia.ai Tutor conversation history';
$string['elediaaitutor:deleteownhistory'] = 'Delete own eLeDia.ai Tutor conversations';

// UI strings.
$string['launch'] = 'Open the eLeDia.ai Tutor';
$string['close'] = 'Close';
$string['online'] = 'Online';
$string['senderyou'] = 'You';
$string['poweredby'] = 'Powered by eLeDia.ai';
$string['send'] = 'Send message';
$string['messagelabel'] = 'Your message to the tutor';
$string['inputplaceholder'] = 'Ask the tutor anything…';
$string['conversation'] = 'Conversation';
$string['sources'] = 'Sources';
$string['source'] = 'Source';
$string['history'] = 'Conversation history';
$string['nohistory'] = 'You have no saved conversations yet.';
$string['clearconversation'] = 'Clear conversation';
$string['newconversation'] = 'New conversation';
$string['newstarted'] = 'Started a new conversation.';
$string['copy'] = 'Copy answer';
$string['copied'] = 'Answer copied to clipboard.';
$string['retry'] = 'Retry';
$string['thinking'] = 'The tutor is thinking…';
$string['failed'] = 'Sorry, the tutor could not respond. Please try again.';
$string['cleared'] = 'Conversation cleared.';
$string['toolong'] = 'Your message is too long.';
$string['nohistorytool'] = 'Loading earlier messages is not available for this tutor.';
$string['configerror'] = 'eLeDia.ai Tutor configuration problem';
$string['unavailable_user'] = 'The tutor is currently unavailable. Please try again later.';
$string['default_welcome'] = 'Hi! I am your eLeDia.ai Tutor. Ask me about your courses, assignments or anything you are studying.';
$string['default_persona'] = 'eLeDia.ai Tutor';
$string['tokenlabel'] = 'eLeDia.ai Tutor connector';

// Instance configuration.
$string['config_title'] = 'Block title';
$string['config_displaymode'] = 'Display mode';
$string['config_displaymode_help'] = 'How the chat is presented: embedded inside the block, a docked panel that floats above the page, a centred modal dialog, or a full-screen experience.';
$string['config_passcoursecontext'] = 'Pass course context';
$string['config_passcoursecontext_help'] = 'When enabled and the block is on a course page, the current course id is sent to the tutor so it can answer course-specific questions. The tutor still works for general questions.';
$string['config_fixedcourseid'] = 'Fixed course id (optional)';
$string['config_fixedcourseid_help'] = 'Force a specific course id to be sent as context regardless of the page. Leave at 0 to use the page context.';
$string['config_welcomemessage'] = 'Welcome message';
$string['config_persona'] = 'Assistant persona label';
$string['config_historyenabled'] = 'Enable conversation history';

// Display modes.
$string['displaymode_embedded'] = 'Embedded in block';
$string['displaymode_docked'] = 'Docked floating panel';
$string['displaymode_modal'] = 'Modal dialog';
$string['displaymode_fullscreen'] = 'Full screen';

// Admin settings — RAG.
$string['setting_header_rag'] = 'RAG / Tutor MCP server';
$string['setting_header_rag_desc'] = 'Connection to the external Retrieval-Augmented Generation (RAG) / Tutor MCP server that answers chat messages. All traffic to this server happens server-side from Moodle.';
$string['setting_ragserverurl'] = 'RAG MCP server URL';
$string['setting_ragserverurl_desc'] = 'The MCP Streamable HTTP endpoint of the RAG/Tutor server, e.g. https://rag.example.com/mcp. Must be HTTPS unless insecure transport is explicitly allowed below.';
$string['setting_ragauthmethod'] = 'RAG authorization method';
$string['setting_ragauthmethod_desc'] = 'How to authenticate Moodle to the RAG server. "Bearer" sends an Authorization: Bearer header; "Custom header" sends the token value verbatim as a full header line.';
$string['authmethod_none'] = 'None';
$string['authmethod_bearer'] = 'Bearer token';
$string['authmethod_header'] = 'Custom header';
$string['setting_ragauthtoken'] = 'RAG authorization token';
$string['setting_ragauthtoken_desc'] = 'The bearer token, or the full "Header-Name: value" line for the custom header method. Stored encrypted and never sent to the browser.';
$string['setting_chattoolname'] = 'Chat tool name';
$string['setting_chattoolname_desc'] = 'The MCP tool invoked via tools/call to answer a chat message. Default: tutor_chat.';
$string['setting_historytoolname'] = 'History tool name';
$string['setting_historytoolname_desc'] = 'Optional MCP tool that returns previous messages for a conversation, e.g. tutor_get_history. Leave empty if the server does not support it.';
$string['setting_deletetoolname'] = 'Delete tool name';
$string['setting_deletetoolname_desc'] = 'Optional MCP tool that deletes a conversation on the RAG server, e.g. tutor_delete_conversation. When set, deleting a conversation in Moodle also removes it on the RAG server. Leave empty to delete only the local pointer.';
$string['setting_deleteusertoolname'] = 'Delete user data tool name';
$string['setting_deleteusertoolname_desc'] = 'Optional MCP tool that deletes ALL data the RAG server holds for the authenticated user (every transcript and any long-term memory), e.g. tutor_delete_user_data. When set, it is preferred over the per-conversation delete tool for "delete all my data" requests and is complete even when Moodle no longer holds conversation references. Leave empty if the server does not support it.';
$string['setting_memoryoptintoolname'] = 'Memory opt-in tool name';
$string['setting_memoryoptintoolname_desc'] = 'Optional MCP tool that records the user\'s long-term memory consent on the RAG server, e.g. tutor_set_memory_optin. Setting this declares the server memory-capable: opt-in changes are pushed immediately, every chat message carries the current consent as ltm_enabled, and opting out instructs the server to erase stored memories. Leave empty while the server has no memory support — no consent data is transmitted then.';
$string['setting_allowinsecuretransport'] = 'Allow insecure (HTTP) transport';
$string['setting_allowinsecuretransport_desc'] = 'Permit a plain http:// RAG URL. Strongly discouraged; for local development only.';
$string['setting_allowprivatenetwork'] = 'Allow private / internal RAG host';
$string['setting_allowprivatenetwork_desc'] = 'Bypass Moodle\'s cURL security (blocked hosts and allowed ports) for the configured RAG server only. Enable this when the RAG server runs on an internal network or a local-development host such as host.docker.internal, whose private address or non-standard port would otherwise be blocked. Leave OFF in production: it removes a layer of SSRF protection.';
$string['setting_requesttimeout'] = 'Request timeout (seconds)';
$string['setting_requesttimeout_desc'] = 'Maximum time to wait for a RAG response before failing.';
$string['setting_streamingenabled'] = 'Enable streaming responses';
$string['setting_streamingenabled_desc'] = 'Use streaming (Server-Sent Events) when the RAG server supports it. The connector parses streamed responses transparently.';

// Admin settings — token.
$string['setting_header_token'] = 'Moodle MCP token handling';
$string['setting_header_token_desc'] = 'The connector provisions a short-lived, user-scoped Moodle MCP token (via the webservice_elediamcp internal API) and passes it to the RAG server so the tutor can act on behalf of the learner.';
$string['setting_mcpserviceid'] = 'MCP external service';
$string['setting_mcpserviceid_desc'] = 'The MCP external service that user tokens are scoped to. Choose one of the services configured in the webservice_elediamcp plugin.';
$string['setting_mcpserviceid_none'] = 'Not configured';
$string['setting_tokenlifetime'] = 'Token lifetime (seconds)';
$string['setting_tokenlifetime_desc'] = 'How long a provisioned token stays valid before a fresh one is minted. Set to 0 for non-expiring tokens (not recommended).';

// Admin settings — behaviour.
$string['setting_header_behaviour'] = 'Behaviour and limits';
$string['setting_defaultdisplaymode'] = 'Default display mode';
$string['setting_defaultdisplaymode_desc'] = 'Display mode used by new block instances.';
$string['setting_enableglobalchat'] = 'Enable global chat';
$string['setting_enableglobalchat_desc'] = 'Allow the tutor to answer general (non-course) questions.';
$string['setting_enablecoursechat'] = 'Enable course chat';
$string['setting_enablecoursechat_desc'] = 'Allow the tutor to receive course context and answer course-specific questions.';
$string['setting_maxmessagelength'] = 'Maximum message length';
$string['setting_maxmessagelength_desc'] = 'The longest user message accepted, in characters.';
$string['setting_ratelimitperminute'] = 'Rate limit (messages per minute)';
$string['setting_ratelimitperminute_desc'] = 'Maximum chat messages a single user may send per minute. Set to 0 to disable.';
$string['setting_loggingverbosity'] = 'Logging verbosity';
$string['setting_loggingverbosity_desc'] = 'How much the connector logs. Secrets and full message content are never logged.';
$string['loglevel_errors'] = 'Errors only';
$string['loglevel_normal'] = 'Normal';
$string['loglevel_verbose'] = 'Verbose';

// Errors.
$string['error_connector_missing'] = 'The required MCP connector plugin (webservice_elediamcp) is not installed or is disabled.';
$string['error_service_not_configured'] = 'No MCP external service has been selected in the eLeDia.ai Tutor settings.';
$string['error_service_unavailable'] = 'The configured MCP external service is not available or not enabled.';
$string['error_rag_url_missing'] = 'The RAG/Tutor server URL has not been configured.';
$string['error_rag_url_invalid'] = 'The configured RAG/Tutor server URL is not a valid URL.';
$string['error_rag_url_insecure'] = 'The RAG/Tutor server URL must use HTTPS.';
$string['error_rag_unavailable'] = 'The tutor service is temporarily unavailable. Please try again.';
$string['error_rag_bad_response'] = 'The tutor returned an unexpected response.';
$string['error_rag_tool_error'] = 'The tutor could not complete your request.';
$string['error_message_empty'] = 'Your message is empty.';
$string['error_message_too_long'] = 'Your message exceeds the maximum length of {$a} characters.';
$string['error_rate_limited'] = 'You are sending messages too quickly. Please wait {$a} seconds.';
$string['error_token_provision_failed'] = 'A Moodle MCP token could not be provisioned for your account.';
$string['error_invalid_context'] = 'Invalid context.';
$string['error_conversation_not_found'] = 'Conversation not found.';
$string['error_course_chat_disabled'] = 'Course chat is disabled on this site.';
$string['error_global_chat_disabled'] = 'Global chat is disabled on this site.';

// Events.
$string['event_message_sent'] = 'Tutor message sent';
$string['event_response_received'] = 'Tutor response received';
$string['event_rag_request_failed'] = 'Tutor request failed';
$string['event_conversation_created'] = 'Tutor conversation created';
$string['event_conversation_cleared'] = 'Tutor conversation cleared';
$string['event_token_provisioned'] = 'Tutor MCP token provisioned';
$string['event_configuration_error'] = 'Tutor configuration error detected';

// Privacy.
$string['privacy:conversations'] = 'eLeDia.ai Tutor conversations';
$string['privacy:metadata:block_elediaaitutor_conv'] = 'Lightweight metadata about your tutor conversations. Full transcripts are stored on the external RAG/Tutor server, not in Moodle.';
$string['privacy:metadata:block_elediaaitutor_conv:userid'] = 'The user who owns the conversation.';
$string['privacy:metadata:block_elediaaitutor_conv:courseid'] = 'The course the conversation was started in, if any.';
$string['privacy:metadata:block_elediaaitutor_conv:conversationid'] = 'The identifier of the conversation on the RAG/Tutor server.';
$string['privacy:metadata:block_elediaaitutor_conv:title'] = 'An optional display title for the conversation.';
$string['privacy:metadata:block_elediaaitutor_conv:lastpreview'] = 'A short preview of the most recent message.';
$string['privacy:metadata:block_elediaaitutor_conv:timecreated'] = 'When the conversation was created.';
$string['privacy:metadata:block_elediaaitutor_conv:timemodified'] = 'When the conversation was last updated.';
$string['privacy:metadata:rag_server'] = 'To answer your questions, messages are sent to the external RAG/Tutor server, which stores the full conversation according to its own policy.';
$string['privacy:metadata:rag_server:userid'] = 'Your Moodle user identity (via a user-scoped token) so the tutor can act on your behalf.';
$string['privacy:metadata:rag_server:message'] = 'The message text you send to the tutor.';
$string['privacy:metadata:rag_server:courseid'] = 'The course context, when provided.';
$string['privacy:metadata:rag_server:conversationid'] = 'The conversation identifier, to maintain context across turns.';

// Privacy guidelines and user data controls.
$string['privacyguidelines'] = 'Privacy guidelines';
$string['privacy_intro'] = 'How the eLeDia.ai Tutor handles your data.';
$string['privacy_accuracy_title'] = 'AI answers can be wrong';
$string['privacy_accuracy_body'] = 'The tutor generates answers using artificial intelligence. Answers can be incomplete or incorrect — always check important information against your course materials or ask your teacher.';
$string['privacy_sent_title'] = 'What is sent when you chat';
$string['privacy_sent_body'] = 'Your message, the course context (when available) and your Moodle identity (via a short-lived, user-scoped token) are sent to the external tutor service so it can answer on your behalf. The tutor can only access what you yourself are allowed to see in Moodle.';
$string['privacy_storage_title'] = 'What is stored';
$string['privacy_storage_body'] = 'Moodle stores only lightweight conversation metadata (a conversation reference, a short preview and timestamps). Full transcripts are stored by the external tutor service according to its retention policy.';
$string['privacy_ltm_title'] = 'Long-term memory (optional, coming soon)';
$string['privacy_ltm_body'] = 'In a future update the tutor will be able to remember helpful facts across conversations to personalise its support. This is switched off by default and is only ever used if you opt in below. No memory data is collected or sent yet.';
$string['ltm_optin'] = 'Allow the tutor to remember information across conversations (long-term memory)';
$string['ltm_saved'] = 'Preference saved.';
$string['privacy_deletion_title'] = 'Deleting your data';
$string['privacy_deletion_body'] = 'You can delete your tutor conversations at any time using the button below. Local records are removed immediately. Where the external tutor service supports remote deletion, your transcripts are deleted there as well; otherwise they remain subject to that service\'s retention policy — contact your administrator if you need them removed.';
$string['deletealldata'] = 'Delete all my tutor data';
$string['deleteall_confirm_title'] = 'Delete all tutor data?';
$string['deleteall_confirm'] = 'This removes all of your saved tutor conversations. This cannot be undone. Do you want to continue?';
$string['deleteall_confirmbutton'] = 'Yes, delete everything';
$string['deleteall_done'] = '{$a} conversation(s) deleted.';
$string['deleteall_external_done'] = 'Deletion was also requested from the external tutor service.';
$string['deleteall_external_unsupported'] = 'The external tutor service does not support remote deletion; transcripts stored there remain subject to its retention policy.';
$string['event_data_deletion_requested'] = 'Tutor data deletion requested';
$string['event_ltm_preference_changed'] = 'Tutor long-term memory preference changed';
$string['privacy:metadata:preference:ltm'] = 'Whether the user has opted in to the tutor\'s long-term memory.';

// Grounding transparency.
$string['groundedbadge'] = 'Based on course materials';
$string['ungroundedbadge'] = 'General answer';
$string['groundedbadge_title'] = 'This answer cites your course materials.';
$string['ungroundedbadge_title'] = 'This answer is not based on your course materials — double-check important facts.';

// Answer styles.
$string['answerstyle'] = 'Answer style';
$string['answerstyle_explain'] = 'Explain';
$string['answerstyle_hint'] = 'Hints only';
$string['answerstyle_quiz'] = 'Quiz me';
$string['config_answerstyle'] = 'Default answer style';
$string['config_answerstyle_help'] = 'How the tutor responds by default: full explanations, guiding hints without final solutions, or practice questions. Learners can switch styles unless you lock the choice below.';
$string['config_allowstylechange'] = 'Learners may change the answer style';

// Question analytics.
$string['setting_enableanalytics'] = 'Question analytics';
$string['setting_enableanalytics_desc'] = 'Log the questions learners ask the tutor (with course and asker) so teachers can spot confusion hotspots in the course report. Answers are never stored. Disabled by default: enabling this is a privacy-relevant decision — logged questions are covered by the privacy API, hidden behind a teacher capability, displayed without names, and pruned after the retention period.';
$string['setting_analyticsretention'] = 'Question analytics retention (days)';
$string['setting_analyticsretention_desc'] = 'Logged questions older than this are deleted by a daily scheduled task. 0 keeps them forever (not recommended).';
$string['elediaaitutor:viewreports'] = 'View eLeDia.ai Tutor course reports';
$string['report_link'] = 'Tutor analytics';
$string['report_title'] = 'eLeDia.ai Tutor — question analytics';
$string['report_intro'] = 'Questions learners asked the tutor in this course. Askers are not shown: the report exists to reveal confusion hotspots, not to monitor individuals.';
$string['report_disabled'] = 'Question analytics is disabled on this site. An administrator can enable it in the eLeDia.ai Tutor settings.';
$string['report_total'] = 'Questions (total)';
$string['report_last7'] = 'Last 7 days';
$string['report_grounded'] = 'Answered from course materials';
$string['report_byday'] = 'Questions per day (last 14 days)';
$string['report_recent'] = 'Recent questions';
$string['report_question'] = 'Question';
$string['report_when'] = 'When';
$string['report_style'] = 'Style';
$string['report_groundedcol'] = 'Grounded';
$string['report_hotspots'] = 'Hotspots (last 30 days)';
$string['report_topic'] = 'Topic / material';
$string['report_none'] = 'No questions logged yet.';
$string['task_prune_question_log'] = 'Prune old tutor question analytics';

// Question log privacy.
$string['privacy:questions'] = 'eLeDia.ai Tutor questions';
$string['privacy:metadata:block_elediaaitutor_qlog'] = 'Questions you asked the tutor, logged for course-level analytics when enabled by the administrator. Answers are not stored.';
$string['privacy:metadata:block_elediaaitutor_qlog:userid'] = 'The user who asked the question.';
$string['privacy:metadata:block_elediaaitutor_qlog:courseid'] = 'The course the question was asked in, if any.';
$string['privacy:metadata:block_elediaaitutor_qlog:question'] = 'The question text (truncated).';
$string['privacy:metadata:block_elediaaitutor_qlog:grounded'] = 'Whether the answer cited course materials.';
$string['privacy:metadata:block_elediaaitutor_qlog:answerstyle'] = 'The answer style used.';
$string['privacy:metadata:block_elediaaitutor_qlog:topic'] = 'The canonical topic label supplied by the tutor service.';
$string['privacy:metadata:block_elediaaitutor_qlog:sourcetitle'] = 'The title of the primary cited course material.';
$string['privacy:metadata:block_elediaaitutor_qlog:cmid'] = 'The course module the primary citation points at.';
$string['privacy:metadata:block_elediaaitutor_qlog:timecreated'] = 'When the question was asked.';

// Cache definitions.
$string['cachedef_usertoken'] = 'Short-lived per-user Moodle MCP token store';
$string['cachedef_ratelimit'] = 'Per-user chat rate-limit counters';
