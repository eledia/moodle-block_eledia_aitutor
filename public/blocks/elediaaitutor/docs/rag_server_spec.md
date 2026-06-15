# RAG / Tutor MCP server — integration specification

This document is the contract for building a **RAG / Tutor MCP server** that works
with the eLeDia.ai Moodle plugins. Implement it and the
[`block_elediaaitutor`](../README.md) chat block will talk to your server, and
your server will be able to read the learner's Moodle data through the
[`webservice_elediamcp`](../../../webservice/elediamcp/README.md) MCP server.

Your server plays **two roles at once**:

```text
                         tools/call (tutor_chat, …)
  block_elediaaitutor  ────────────────────────────►  YOUR RAG / Tutor MCP server
   (MCP client)                                          (MCP server  AND  MCP client)
                                                                │
                                                                │ tools/call (moodle_*),
                                                                │ Authorization: Bearer <moodle_token>
                                                                ▼
                                                         Moodle MCP server
                                                       (webservice_elediamcp)
```

- **As an MCP _server_** you expose the `tutor_*` tools the block calls (Part A).
- **As an MCP _client_** you call the `moodle_*` tools to fetch the learner's data,
  using the user-scoped token the block hands you (Part C).

Both directions use **MCP over Streamable HTTP** with **JSON-RPC 2.0**
`tools/call` (Part B).

---

## Part A — Tools your server MUST/​MAY provide

The block calls your server with a single JSON-RPC `tools/call`. Tool names are
**configurable** in the block (Site administration ▸ Plugins ▸ Blocks ▸ eLeDia.ai
Tutor); the defaults are shown below.

| Tool (default name) | Required? | Block setting | Purpose |
|---|---|---|---|
| `tutor_chat` | **Required** | Chat tool name | Answer a learner message. |
| `tutor_get_history` | Optional | History tool name | Return earlier messages of a conversation. |
| `tutor_delete_conversation` | Optional | Delete tool name | Delete a single conversation server-side. |
| `tutor_delete_user_data` | Optional, **recommended** | Delete user data tool name | Erase ALL data held for the authenticated user (transcripts + memory). |
| `tutor_set_memory_optin` | Optional (required for memory) | Memory opt-in tool name | Record the user's long-term memory consent; erase memories on opt-out. |
| `tutor_recluster_questions` | Optional | Recluster tool name | Nightly batch re-labelling of logged questions (analytics topic convergence); authenticated by the maintenance account's token. |

If an optional tool name is left blank in the block, that feature is simply not used.

### A.1 `tutor_chat` (required)

**Arguments the block sends** (`params.arguments`):

| Field | Type | Always present | Meaning |
|---|---|---|---|
| `system_url` | string | yes | The Moodle site's `wwwroot`, e.g. `https://moodle.example.com`. Use it as the base for callbacks (Part C). |
| `moodle_token` | string | yes | **User-scoped** Moodle MCP token. Treat as a secret. Use it to call back into Moodle as this learner (Part C). |
| `user_message` | string | yes | The learner's message (already length-validated, ≤ configured max, default 4000 chars). |
| `course_id` | string | only in course context | Moodle course id the chat is attached to. Omitted for global chat. |
| `conversation_id` | string | only on follow-ups | Your own conversation id from a previous turn. **Absent ⇒ start a new conversation.** |
| `ltm_enabled` | boolean | only when memory is configured | The user's long-term-memory consent **for this request**. Only sent when the Moodle admin has configured the memory opt-in tool (i.e. your server declared memory support). **Absent ⇒ treat as `false`**: do not read or write memory. Gate every memory read AND write on this request's value — it is the authoritative per-request consent signal. |
| `answer_style` | string | yes (server-side enforced) | Pedagogical style: `explain` (full explanations, the default), `hint` (guide step by step, **never give the final solution**), or `quiz` (respond with practice questions and check the learner's answers). Absent ⇒ `explain`. The value is validated and lock-enforced by Moodle (teachers can pin a style per block), so honour it as authoritative. |
| `user_lang` | string | yes | The learner's Moodle language code (e.g. `de`, `en`). Answer in this language unless the learner explicitly asks otherwise. |
| `rag_enabled` | boolean | only when set | Whether the agent may use its retrieval / knowledge-base tool. When `false` (**LLM-only mode**) the agent MUST NOT retrieve and should answer from the model directly — expect no `sources`. Absent ⇒ treat as `true` (grounded). Moodle decides this server-side (admin gate + per-instance setting + whether the course is ingested), so honour it as authoritative. |
| `persona` | object | only when configured | The tutor's configured persona, shaping its **voice** only. An object with any of these string sub-fields (only the populated ones are sent): `name` (what the tutor calls itself), `role` (what it is, e.g. "a patient maths tutor"), `tone` (e.g. "warm and encouraging"), `audience` (who it is helping), `instructions` (free-text style guidance). Treat it as **system-prompt guidance**: adopt the name/role/tone/audience and follow the instructions when phrasing answers. It MUST NOT override safety, the `answer_style` contract, the grounding rules (`rag_enabled`), or the requirement to answer in `user_lang`. The text is institution/teacher-authored (already length-bounded) and never contains secrets. Absent ⇒ use your default voice. |

**Response the block expects** — return an MCP tool result. The block reads, in
order of preference:

1. `result.structuredContent` (preferred), or
2. `result.content[]` text parts (fallback) — parsed as JSON if it *is* JSON, else
   treated as the Markdown answer.

Recognised fields (several aliases accepted, so you can keep your own naming):

| Concept | Accepted keys (first match wins) |
|---|---|
| Answer body (Markdown) | `answer`, `text`, `message`, `response`, `content`, `output` |
| Conversation id | `conversation_id`, `conversationId`, `session_id`, `sessionId`, `thread_id` |
| Sources / citations | `sources`, `citations`, `documents`, `references` |
| Topic label | `topic`, `subject` |

A **source** entry may be a plain string, or an object with any of:
`title`/`name`/`source`, `url`/`link`/`uri`, `snippet`/`text`/`excerpt`.
**Order sources by relevance: `sources[0]` is treated as the PRIMARY source**
and is stored (title + resolved Moodle cmid) for the teacher analytics
hotspots.

**`topic` (strongly recommended):** a short canonical label (≤ 100 chars) used
to cluster questions in the teacher analytics dashboard. Without it, two
phrasings of the same question ("When is the essay due?" / "essay deadline?")
never aggregate and the statistics stay uselessly granular. Rules:

- **Stable across rephrasings**: the same concept must always yield the same
  label. Derive it from your retrieval (e.g. the dominant chunk's
  section/concept) or a cheap classification step — not from the user's wording.
- Keep the label set small and human-readable (course-section or concept
  granularity, e.g. `Photosynthesis`, `Assignment 2`, `Enrolment & access`).
- Use the course's language consistently; do not vary the label by the
  learner's `user_lang`.
- Omit the field when you genuinely cannot classify; Moodle then falls back to
  grouping by the primary source's title.

> The answer is rendered as **Markdown** and sanitised by Moodle's HTML purifier
> before display, so Markdown (headings, lists, code fences, links, tables) is
> safe and encouraged. Do **not** send raw HTML expecting it to survive untouched.

**Recommended response shape:**

```json
{
  "content": [
    { "type": "text", "text": "You are enrolled in **3 courses**: …" }
  ],
  "structuredContent": {
    "answer": "You are enrolled in **3 courses**: …",
    "conversation_id": "conv-9f2a1c",
    "sources": [
      { "title": "My courses", "url": "https://moodle.example.com/my/courses.php", "snippet": "Biology 101, …" }
    ]
  },
  "isError": false
}
```

- **Always return a `conversation_id`** (new one when the request had none). The
  block persists it and sends it back on the next turn so you keep context.
- Set `"isError": true` for a handled, user-facing failure. The block shows a
  friendly error and a **Retry** button; it does not treat your text as an answer.

### A.2 `tutor_get_history` (optional)

Called when the user opens a stored conversation (the block first checks the user
owns it). Arguments: `system_url`, `moodle_token`, `conversation_id`.

Return the messages as `structuredContent.messages` (or a JSON `{"messages": …}`
text payload):

```json
{
  "structuredContent": {
    "messages": [
      { "role": "user",      "content": "What is photosynthesis?" },
      { "role": "assistant", "content": "Photosynthesis is …",
        "sources": [
          { "title": "Photosynthesis", "url": "https://moodle.example.com/mod/page/view.php?id=42", "snippet": "…" }
        ] }
    ]
  }
}
```

- `role` is normalised to `user` / `assistant` (anything not `user` becomes
  `assistant`). `content` may also be supplied as `text`. Empty messages are dropped.
- Assistant `content` is rendered as Markdown; user `content` is shown as plain text.
- **`sources` (optional, per assistant message):** the citations for that turn,
  identical in shape to the `tutor_chat` `sources` (A.1) — an array of
  `{title, url, snippet}` (the same key aliases are accepted). Return them so a
  reopened conversation shows the same source cards as the live answer did.
  Absent ⇒ no citations for that message. User messages carry no sources.

### A.3 `tutor_delete_conversation` (optional)

Called when the learner deletes a conversation from history. Arguments:
`system_url`, `moodle_token`, `conversation_id`. Delete the conversation and its
messages and return success:

```json
{ "structuredContent": { "deleted": true, "conversation_id": "conv-9f2a1c" } }
```

- The block treats **any non-error result** as success. It's best-effort: if your
  server errors, the block still removes its local pointer and logs the failure,
  so the learner can always erase their own copy.
- For "delete all my data" requests this tool is only the **fallback** (called
  once per conversation Moodle still knows about). Implement A.4 for complete
  erasure.

### A.4 `tutor_delete_user_data` (optional, **recommended**)

Called when the learner uses "Delete all my tutor data" (and by GDPR erasure
flows). Arguments: `system_url`, `moodle_token` — no conversation ids, because
the point of this tool is to be **complete by definition**: erase *everything*
your server holds for the authenticated user, including

- every conversation/transcript (also ones Moodle no longer has pointers to), and
- all long-term memory stored for the user (A.5).

```json
{ "structuredContent": { "deleted": true, "conversations_deleted": 12, "memories_deleted": 4 } }
```

The counts are optional; any non-error result is treated as success. When this
tool is configured in the block it is **preferred** over per-conversation
deletion and is invoked even when Moodle holds zero local pointers. Identify the
user via the token (e.g. by calling `moodle_me` / `moodle_verify_user_context`
back on the Moodle MCP server, or by your own token→user mapping established
during chats).

### A.5 `tutor_set_memory_optin` (optional — required for memory support)

Long-term memory lets the tutor remember helpful facts about a learner across
conversations. **Consent rules are strict**:

1. **Default off.** Never read or write memory for a user unless consent is
   present.
2. **Per-request gate.** Every `tutor_chat` call carries the current consent as
   `ltm_enabled` (A.1). Gate each memory read and write on *that request's*
   value — this keeps behaviour correct even if a consent push was missed.
3. **Opt-out = erasure.** When this tool is called with `enabled: false`, delete
   all memory already stored for the user, not just stop collecting.

Arguments: `system_url`, `moodle_token`, `enabled` (boolean). Called immediately
whenever the user toggles the opt-in in Moodle; treat it as idempotent.

```json
{ "structuredContent": { "accepted": true, "enabled": false, "memories_deleted": 4 } }
```

Any non-error result is treated as success. The call is best-effort from
Moodle's side (rule 2 is the safety net), so do not rely on it as the *only*
consent signal.

> Moodle never transmits memory **content** — only the consent boolean. What you
> store as memory, you own; include it in A.4 deletion and document its
> retention.

### A.6 `tutor_recluster_questions` (optional — called by a nightly task)

Topic quality is the foundation of the teacher analytics. Two mechanisms keep
it high; implement both:

**Primary — per-course label registry (required for good analytics).**
Maintain a registry of topic labels per course and always classify a question
*into the existing set*, only minting a new label when nothing fits. Free-form
label generation per answer WILL fragment ("Essay deadline", "Essay 2 due
date", "Assignment deadlines" for the same concept) and makes the hotspot
report useless.

**Repair — batch reclustering (this tool).** Even with a registry, labels
drift over time (model/prompt updates, course restructuring, early questions
asked before the registry stabilised). When the Moodle admin configures this
tool's name, a **nightly scheduled task** sends the last 30 days of logged
questions per active course (in batches of ≤ 200, oldest first, together with
the course's current label set) and writes the returned canonical labels back —
converging the *historical* analytics:

```json
{ "name": "tutor_recluster_questions",
  "arguments": {
    "system_url": "https://moodle.example.com",
    "moodle_token": "f9a8…",
    "course_id": "42",
    "existing_labels": ["Photosynthesis", "Assignment 2", "Enrolment & access"],
    "questions": [
      { "id": 17, "text": "When is the essay due?" },
      { "id": 18, "text": "essay deadline?" }
    ]
  } }
```

Expected response — one label per question id, drawn from (or extending) the
supplied label set:

```json
{ "structuredContent": {
    "topics": [
      { "id": 17, "topic": "Assignment 2" },
      { "id": 18, "topic": "Assignment 2" }
    ] } }
```

Contract notes, fixed now so you can design toward them:

- **Authentication — maintenance account token.** The `moodle_token` belongs to
  a dedicated, auto-provisioned **maintenance account**
  (username `elediaaitutor_service`) — never to a learner or an administrator.
  No shared transport secret is required for this call. Your server **MUST**:
  1. **Validate the token by callback** like every other tool's token: call
     `moodle_verify_user_context` against
     `{system_url}/webservice/elediamcp/server.php` (C.1) with the token as
     bearer. Moodle only answers for genuine, unexpired, unrevoked tokens of
     active accounts.
  2. **Pin on the username**: accept `tutor_recluster_questions` only when the
     validated identity is `user.username === "elediaaitutor_service"`. A valid
     *learner* token MUST be rejected for this tool. (Corroborating signal: the
     account has no enrolments, so its `courses` list is empty.)
  3. **Validate against known tenants only**: perform the callback only for
     `system_url` values in your configured tenant list, over HTTPS — a
     callback to an attacker-supplied URL proves nothing.

  One successful validation may be cached for the duration of a nightly run
  (the token lives ~1 hour; a run takes seconds). The account is deliberately
  powerless inside Moodle (no roles, no enrolments, no interactive login), so
  do **not** treat it as a person: never create conversations, memories or any
  per-user state for it.
  (Plugins ≤ 0.6.0 sent no `moodle_token`; if you must support those, fall
  back to requiring the transport-level authorization of B.4.)
- Batches are bounded (≤ 200 questions per call); the plugin may call
  repeatedly.
- Idempotent: reclustering the same batch must yield the same labels.
- Privacy: the question texts already transited your server at chat time; this
  re-sends the same data to the same processor for the same purpose. Do not
  retain the batch beyond processing.

---

## Part B — Transport, framing & authentication

### B.1 Request

The block issues exactly one HTTP request per call:

```
POST <RAG MCP server URL>
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
Authorization: Bearer <rag-token>          # only if configured (see B.4)
```

Body (JSON-RPC 2.0):

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "tutor_chat",
    "arguments": { "system_url": "…", "moodle_token": "…", "user_message": "…" }
  }
}
```

> The block calls `tools/call` **directly** — it does not perform an MCP
> `initialize` handshake and does not call `tools/list`. You may still implement
> `initialize` / `tools/list` for other clients and for debugging.

### B.2 Response framing — JSON or SSE

The block accepts **either**:

- a plain JSON body (`Content-Type: application/json`) containing the JSON-RPC
  response, **or**
- a **Server-Sent Events** stream (`Content-Type: text/event-stream`). The block
  reads `data:` lines and uses the **last** frame that is a JSON-RPC message
  (i.e. has `result` or `error`). Intermediate progress events are ignored.

Either way the envelope is standard JSON-RPC 2.0:

```json
{ "jsonrpc": "2.0", "id": 1, "result": { … } }
```

or, for a protocol-level failure:

```json
{ "jsonrpc": "2.0", "id": 1, "error": { "code": -32000, "message": "…" } }
```

A JSON-RPC `error`, a non-2xx HTTP status, an empty body, or unparseable content
all surface to the learner as a generic "tutor unavailable" message (detail is
logged server-side only, never shown).

### B.3 Limits

- **Timeout:** the block waits up to the configured request timeout (default 30 s).
  Long generations should stream (B.2) or stay within the timeout.
- **Message length:** user messages are capped (default 4000 chars) before they
  reach you.
- **Per-user rate limiting** is applied by the block before calling you.

### B.4 How the block authenticates to your server

Configured by the admin (block settings):

| Method | Header the block sends |
|---|---|
| `none` (default) | — |
| `bearer` | `Authorization: Bearer <token>` |
| `header` | a verbatim header line you specify, e.g. `X-Api-Key: <token>` |

This is **separate** from the `moodle_token` (which is a per-user argument, not a
transport credential). Validate this however you like; it identifies the Moodle
site/connector, not the end user. Since every tool call (including the nightly
reclustering, as of 0.7.0) carries a verifiable `moodle_token`, transport-level
authorization is **optional defence in depth**, not a requirement.

**Tenant resolution at query time (multi-customer deployments).** The corpus
ingested by `local_ragingest` is namespaced by a tenant id **derived from the
Moodle site's `wwwroot`** (canonicalisation defined in that plugin's
`API_SPECIFICATION.md` v1.2: lowercased host plus subdirectory path, reduced to
`[a-z0-9._-]`). At query time, resolve the tenant from the **verified**
`site.url` returned by the `moodle_verify_user_context` callback — i.e. only
after the `moodle_token` validated against that `system_url` — and apply the
same canonicalisation. Filter every retrieval by that tenant. Never trust a
client-claimed tenant value: the verified site URL is the only tenant anchor.
Maintain a small tenant registry (`tenant → ingestion API key, [site_urls]`)
when one customer operates several Moodle sites against one corpus.

**Direct MCP hosts (e.g. Claude Desktop).** Users may connect generic MCP
clients straight to your server, and such hosts cannot inject per-call tool
arguments. Support this by also accepting a Moodle MCP token as the transport
bearer (`Authorization: Bearer <moodle_token>`) and mapping it to the
`moodle_token` argument internally when the argument is absent. Users obtain
their personal token from their Moodle profile (the elediamcp self-service
token page).

---

## Part C — Calling back into Moodle (using `moodle_token`)

To answer with real data, call the Moodle MCP server **as the learner**, using the
`moodle_token` and `system_url` you were given.

### C.1 Endpoint & auth

```
POST {system_url}/webservice/elediamcp/server.php
Content-Type: application/json
Accept: application/json
Authorization: Bearer {moodle_token}
```

- The token is **user-scoped**: every tool runs with that learner's permissions
  and only ever returns data they may see. You cannot escalate beyond the user.
- Tokens are short-lived (admin-configured, default ~1 h) and may be rotated; if a
  call returns an auth error, fail gracefully — the block will mint a fresh token
  and retry the turn once.
- **Never log, persist, echo, or return the token** to the block or the learner.

### C.2 Protocol

Same MCP Streamable HTTP / JSON-RPC 2.0 as Part B. Discover tools at runtime with
`tools/list`; call them with `tools/call`:

```json
{
  "jsonrpc": "2.0", "id": 1, "method": "tools/call",
  "params": { "name": "moodle_my_courses", "arguments": {} }
}
```

Each tool result is a standard MCP result with `content[]` (text, often JSON) and
usually `structuredContent`. Read `structuredContent` when present.

### C.3 Recommended flow

1. **Start every conversation with `moodle_verify_user_context`.** It confirms the
   token and returns the user, their enrolled courses, role per course, optional
   group memberships, and a short natural-language summary suitable for an LLM
   system prompt. Pass `course_id` to scope it to one course.
2. Use the returned `course_id` values for course-scoped tools
   (`moodle_course_contents`, `moodle_get_resource`, …).
3. Cache within a turn; don't refetch the same data repeatedly.

### C.4 Moodle MCP tool catalogue

All read-only unless noted. Names/▾schemas are authoritative via `tools/list`; this
is the summary as of `webservice_elediamcp` 1.0.

| Tool | Purpose |
|---|---|
| `moodle_verify_user_context` | Verify the token; return the user, enrolments, roles, (optional) groups & capabilities, and an LLM-ready summary. **Call first.** |
| `moodle_me` | Identity of the authenticated user (id, full name, email, …). |
| `moodle_my_courses` | Courses the user is enrolled in (with optional extras). |
| `moodle_search_courses` | Free-text search of the course catalogue (paginated). |
| `moodle_course_contents` | Sections and activity modules visible to the user in a course. |
| `moodle_get_resource` | Readable content of one course module by `cmid`. |
| `moodle_search_content` | Full-text search across content the user can access (global search, with a fallback to activity names/descriptions); follow up with `moodle_get_resource` on a hit's `cmid`. |
| `moodle_get_announcements` | Most recent posts in each course's Announcements forum. |
| `moodle_forum_discussions` | Course forum discussions and the posts of one discussion (groups, Q&A gating, timed posts and private replies enforced). |
| `moodle_calendar_upcoming` | Upcoming calendar events visible to the user. |
| `moodle_my_assignments` | Assignments across the user's courses (status, due dates). |
| `moodle_my_grades` | The user's course-final grade per enrolled course. |
| `moodle_my_progress` | Completion progress per course (percentage, completed/total), optional per-activity states — the backbone for progress coaching. |
| `moodle_quiz_info` | Quizzes with timing/attempt limits plus the user's **own** attempt history and best grade. |
| `moodle_my_submission_files` | The user's **own** latest assignment submission: files and online-text content (self-scoped). |
| `moodle_find_user` | Find messageable users by fuzzy name (precursor to messaging). |
| `moodle_send_message` ⚠️ | **Write.** Send a 1:1 personal message *as the user*. |

**Write tools (`moodle_send_message`) use a two-step confirm:**

1. First call **without** `confirm` (or `confirm: false`) → returns a **preview**
   with `requires_confirmation: true`. Show it to the learner and get explicit
   approval.
2. Second call with `confirm: true` actually performs the action.

Tools advertise MCP annotations (`readOnlyHint`, `destructiveHint`) via
`tools/list` — honour them: never auto-confirm a non-read-only tool.

> The Moodle MCP server may also expose raw Moodle web-service functions alongside
> these curated AI tools (admin-configurable). Prefer the `moodle_*` AI tools —
> they return LLM-friendly, summarised output.

---

## Part D — Conversation lifecycle, privacy & errors

- **New conversation:** the block sends `tutor_chat` **without** `conversation_id`.
  Mint a new one and return it. The previous conversation is untouched.
- **Continuing:** the block sends the `conversation_id` you returned; load that
  thread's context.
- **History ownership:** the block stores only a *pointer* (your `conversation_id`
  + a short preview) and enforces that a user can only read/delete their own.
  Your server owns the full transcript and its retention policy — **document it**;
  it is declared to learners via the block's Privacy API as an external location.
- **Deletion / GDPR:** implement `tutor_delete_user_data` (preferred — complete,
  includes memory) and/or `tutor_delete_conversation` so erasure propagates.
  Without them, deleting in Moodle removes only the local pointer.
- **Long-term memory:** off by default, gated per-request by `ltm_enabled`,
  erased on opt-out (`tutor_set_memory_optin` with `enabled: false`) and by
  `tutor_delete_user_data`. Never store memory from a request without
  `ltm_enabled: true`.
- **Secrets:** `moodle_token`, `system_url` and any RAG auth token are server-side
  only — the block never exposes them to the browser, and neither should you.
- **Errors:** distinguish *handled* tool errors (`"isError": true` in the result,
  for "I couldn't do that") from *protocol* errors (JSON-RPC `error` / non-2xx,
  for "the service is broken"). The block shows a friendlier message for the former.

---

## Part E — End-to-end example

**Block → your server** (course chat, follow-up turn):

```json
{
  "jsonrpc": "2.0", "id": 1, "method": "tools/call",
  "params": {
    "name": "tutor_chat",
    "arguments": {
      "system_url": "https://moodle.example.com",
      "moodle_token": "abc123…",
      "user_message": "What do I need to do for this week's assignment?",
      "course_id": "42",
      "conversation_id": "conv-9f2a1c"
    }
  }
}
```

**Your server → Moodle** (fetch the data, as the learner):

```json
{
  "jsonrpc": "2.0", "id": 1, "method": "tools/call",
  "params": { "name": "moodle_my_assignments", "arguments": { "course_id": 42 } }
}
```

**Your server → block** (the answer):

```json
{
  "jsonrpc": "2.0", "id": 1,
  "result": {
    "content": [{ "type": "text", "text": "This week **Essay 2** is due Friday 17:00…" }],
    "structuredContent": {
      "answer": "This week **Essay 2** is due Friday 17:00…",
      "conversation_id": "conv-9f2a1c",
      "sources": [
        { "title": "Essay 2", "url": "https://moodle.example.com/mod/assign/view.php?id=99",
          "snippet": "Due Friday 17:00" }
      ]
    },
    "isError": false
  }
}
```

---

## Implementation checklist

- [ ] `POST` endpoint speaking JSON-RPC 2.0 `tools/call`; reply as JSON **or** SSE.
- [ ] `tutor_chat` accepting `system_url`, `moodle_token`, `user_message`,
      optional `course_id` / `conversation_id`.
- [ ] Always return a `conversation_id`; treat a missing one as "new conversation".
- [ ] Answer as Markdown in `structuredContent.answer` (+ a `content[]` text part).
- [ ] Optional `tutor_get_history` and `tutor_delete_conversation`.
- [ ] Recommended `tutor_delete_user_data`: erase ALL user data (transcripts +
      memory) for the authenticated token, even without conversation ids.
- [ ] For memory support: `tutor_set_memory_optin`; memory off by default, every
      read/write gated on the request's `ltm_enabled`, erase on opt-out.
- [ ] Use `Authorization: Bearer {moodle_token}` against
      `{system_url}/webservice/elediamcp/server.php`; call
      `moodle_verify_user_context` first.
- [ ] Honour tool annotations; two-step confirm before any write
      (`moodle_send_message`).
- [ ] Never log or leak the token; document your transcript retention.
- [ ] Respect the block's timeout (default 30 s) — stream long answers.
- [ ] Use `"isError": true` for handled failures; JSON-RPC `error` for outages.
- [ ] Return a stable `topic` label with each answer and order `sources` by
      relevance (`sources[0]` = primary) so teacher analytics can aggregate.
- [ ] Honour `answer_style` (`hint` must never reveal full solutions) and
      `user_lang`.
- [ ] Optional `tutor_recluster_questions`: classify each batch into the
      supplied label registry (mint sparingly), idempotent, ≤ 200/batch; its
      `moodle_token` belongs to the `elediaaitutor_service` maintenance
      account — validate it by callback, **pin on that username** (reject
      learner tokens for this tool), only against known tenant `system_url`s,
      and never create per-user state for it.
- [ ] For direct MCP hosts (Claude Desktop etc.): accept a Moodle token as
      transport bearer and map it to `moodle_token` when the argument is absent.

---

## Contract changelog

The plugin version (in `version.php`) that introduced each contract change.
Build against the newest row; all fields remain backwards-compatible (optional
unless marked otherwise).

| Plugin version | Change |
|---|---|
| 0.14.0 | **Per-message `sources` in `tutor_get_history`** (A.2): assistant messages may include an optional `sources` array (same `{title, url, snippet}` shape and aliases as the `tutor_chat` sources). The block normalises and renders them as the same citation cards used for live answers, so reopened conversations keep their citations. Absent ⇒ no sources for that message. Additive and backwards-compatible. |
| 0.13.0 | **`persona`** chat argument (A.1): an optional object (`name`/`role`/`tone`/`audience`/`instructions`, only populated sub-fields sent) carrying the tutor's configured persona. Use it as system-prompt guidance for the tutor's **voice** only — it must never override safety, `answer_style`, `rag_enabled` grounding, or `user_lang`. Absent ⇒ default voice. The tutor's design/branding (now fully configurable per site and per block, and packaged as importable "tutor profiles") is presentation-only and not part of this contract. |
| 0.9.0 | **`rag_enabled`** chat argument (A.1): when `false` the agent must NOT call its retrieval tool and answers from the model alone (LLM-only / pass-through). Moodle sends it based on an admin gate, a per-block setting, and whether the course is ingested; absent ⇒ grounded. |
| 0.8.2 (doc update) | **Tenant resolution defined** (B.4): the retrieval corpus is namespaced by a tenant id derived from the Moodle `wwwroot` (see local_ragingest API spec v1.2); resolve it at query time from the **verified** `site.url` of the token callback with the same canonicalisation, and filter all retrieval by it. Never trust a claimed tenant value. |
| 0.8.1 (doc update) | Moodle tool catalogue (C.4) at `webservice_elediamcp` 1.0: added **`moodle_search_content`** (full-text/content discovery — pairs with `moodle_get_resource`) and **`moodle_my_submission_files`** (the user's own submission files + online text). No change to the Moodle→RAG chat contract. Security note for self-hosters: elediamcp 1.0 closes a `moodle_get_resource` cross-course content-access leak — run ≥ 1.0 in production. |
| 0.8.0 | Moodle tool catalogue (C.4) grew to 15 with `webservice_elediamcp` 0.9: **`moodle_my_progress`** (completion coaching), **`moodle_quiz_info`** (own attempts only) and **`moodle_forum_discussions`** (visibility-safe forum reading) — use them to ground tutoring in the learner's actual progress. Also new chat-side capabilities since 0.7.0: tutor UI sends `answer_style`/`user_lang` unchanged; nothing else in the Moodle→RAG contract changed. |
| 0.7.0 (doc update) | A.6 recluster validation spelled out as MUSTs: callback-validate via `moodle_verify_user_context`, **pin on username `elediaaitutor_service`** (reject learner tokens for this tool), callback only to known tenant `system_url`s over HTTPS; one validation may be cached per nightly run. |
| 0.7.0 | **Reclustering now authenticates with a `moodle_token`** (A.6): the nightly task auto-provisions a powerless maintenance account (`elediaaitutor_service`) and sends its token, so the call is verifiable like every other tool and **no shared transport secret is needed**; B.4 transport auth is now optional defence in depth. New B.4 note: servers SHOULD accept a Moodle token as transport bearer for direct MCP hosts (Claude Desktop etc.) and map it to `moodle_token`. |
| 0.6.0 | **`tutor_recluster_questions` is now LIVE** (A.6): when configured, a nightly Moodle task sends the last 30 days of questions per course (≤200/batch, service-level auth — no `moodle_token`) and applies the returned labels. The per-course label registry remains the primary mechanism. |
| 0.5.0 (doc update) | **`tutor_recluster_questions`** reserved (A.6): per-course label registry declared the primary topic-quality mechanism; batch-reclustering contract fixed (service-level auth, ≤200/batch, idempotent). |
| 0.5.0 | **`topic`** response field (canonical label for analytics clustering); `sources[0]` defined as the primary source and stored (title + cmid) for hotspot aggregation. |
| 0.4.0 | **`answer_style`** chat argument (`explain`/`hint`/`quiz`, server-side lock-enforced — the UI's pedagogy chips) and **`user_lang`** chat argument (answer in the learner's language). |
| 0.3.0 | Long-term memory consent: **`ltm_enabled`** chat argument (per-request gate) and **`tutor_set_memory_optin`** tool (erase-on-revoke); **`tutor_delete_user_data`** tool (complete user-level erasure, preferred for "delete all my data"). |
| 0.2.0 | **`tutor_delete_conversation`** tool wired to per-conversation deletion; "new conversation" defined as a `tutor_chat` call without `conversation_id`. |
| 0.1.0 | Initial contract: `tutor_chat` (+`system_url`, `moodle_token`, `user_message`, `course_id`, `conversation_id`), `tutor_get_history`, JSON/SSE framing, Moodle MCP callback (Part C). |
