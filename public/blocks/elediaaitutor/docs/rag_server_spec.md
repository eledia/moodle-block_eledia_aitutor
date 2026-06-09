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
| `tutor_delete_conversation` | Optional | Delete tool name | Delete a conversation server-side. |

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

A **source** entry may be a plain string, or an object with any of:
`title`/`name`/`source`, `url`/`link`/`uri`, `snippet`/`text`/`excerpt`.

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
      { "role": "assistant", "content": "Photosynthesis is …" }
    ]
  }
}
```

- `role` is normalised to `user` / `assistant` (anything not `user` becomes
  `assistant`). `content` may also be supplied as `text`. Empty messages are dropped.
- Assistant `content` is rendered as Markdown; user `content` is shown as plain text.

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
site/connector, not the end user.

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
is the summary as of `webservice_elediamcp` 0.8.

| Tool | Purpose |
|---|---|
| `moodle_verify_user_context` | Verify the token; return the user, enrolments, roles, (optional) groups & capabilities, and an LLM-ready summary. **Call first.** |
| `moodle_me` | Identity of the authenticated user (id, full name, email, …). |
| `moodle_my_courses` | Courses the user is enrolled in (with optional extras). |
| `moodle_search_courses` | Free-text search of the course catalogue (paginated). |
| `moodle_course_contents` | Sections and activity modules visible to the user in a course. |
| `moodle_get_resource` | Readable content of one course module by `cmid`. |
| `moodle_get_announcements` | Most recent posts in each course's Announcements forum. |
| `moodle_calendar_upcoming` | Upcoming calendar events visible to the user. |
| `moodle_my_assignments` | Assignments across the user's courses (status, due dates). |
| `moodle_my_grades` | The user's course-final grade per enrolled course. |
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
- **Deletion / GDPR:** implement `tutor_delete_conversation` so erasure propagates.
  Without it, deleting in Moodle removes only the local pointer.
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
- [ ] Use `Authorization: Bearer {moodle_token}` against
      `{system_url}/webservice/elediamcp/server.php`; call
      `moodle_verify_user_context` first.
- [ ] Honour tool annotations; two-step confirm before any write
      (`moodle_send_message`).
- [ ] Never log or leak the token; document your transcript retention.
- [ ] Respect the block's timeout (default 30 s) — stream long answers.
- [ ] Use `"isError": true` for handled failures; JSON-RPC `error` for outages.
