# MCP / RAG integration guide

This block is an MCP **client**. It calls your RAG/Tutor server with a single
JSON-RPC 2.0 `tools/call` over the MCP Streamable HTTP transport.

> Building the RAG server itself? See the complete, authoritative contract in
> [**rag_server_spec.md**](rag_server_spec.md) — it covers the tools you must
> provide, the Moodle MCP tools you can call back into, framing, auth, the
> conversation lifecycle, and an implementation checklist. This page is the
> quick reference.

## Request

`POST <RAG MCP server URL>`

Headers:

```
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
Authorization: Bearer <token>        # if configured
```

Body:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "tutor_chat",
    "arguments": {
      "system_url": "https://moodle.example.com",
      "moodle_token": "USER_SCOPED_MOODLE_MCP_TOKEN",
      "user_message": "What do I need to do this week?",
      "course_id": "42",
      "conversation_id": "optional-existing-id"
    }
  }
}
```

`course_id` and `conversation_id` are omitted when not applicable.

The `moodle_token` is a **user-scoped** Moodle MCP token minted via
`webservice_elediamcp`. Your RAG server should use it to call back into Moodle's
MCP server (`system_url`) to retrieve the learner's data, acting strictly with
that learner's permissions.

## Response

The client accepts either a JSON body or an SSE (`text/event-stream`) body and
reads the final JSON-RPC frame. It normalises the tool result flexibly:

- **Preferred:** `result.structuredContent` containing any of
  `answer` / `text` / `message` / `response`, plus optional
  `conversation_id` (or `conversationId` / `session_id` / `thread_id`) and
  `sources` (or `citations` / `documents` / `references`).
- **Fallback:** `result.content[].text` — parsed as JSON if it is JSON, else
  treated as Markdown answer text.

Example structured result:

```json
{
  "structuredContent": {
    "answer": "This week you have **Assignment 2** due Friday…",
    "conversation_id": "conv-abc123",
    "sources": [
      {"title": "Assignment 2", "url": "https://moodle.example.com/mod/assign/view.php?id=99", "snippet": "Due Friday 17:00"}
    ]
  }
}
```

Each source may be an object (`title`/`url`/`snippet`, with several alias keys
accepted) or a plain string.

To signal a handled error, set `result.isError = true`; the block shows a
friendly failure and offers retry.

## History tool (optional)

If you configure a **History tool name**, the block calls it as:

```json
{ "name": "tutor_get_history",
  "arguments": { "system_url": "...", "moodle_token": "...", "conversation_id": "conv-abc123" } }
```

and expects `{ "messages": [ {"role": "user|assistant", "content": "..."} ] }`
(in `structuredContent` or a JSON text payload).

## Delete tool (optional)

If you configure a **Delete tool name**, deleting a conversation in Moodle calls
it as:

```json
{ "name": "tutor_delete_conversation",
  "arguments": { "system_url": "...", "moodle_token": "...", "conversation_id": "conv-abc123" } }
```

A `structuredContent` of `{ "deleted": true }` (or any non-error result) is
treated as success. The call is best-effort: if it fails, the block still
removes its local pointer and logs a `rag_request_failed` event, so a learner can
always erase their own data.

## Conversation lifecycle

- When the RAG server returns a `conversation_id`, the block stores a pointer to
  it for the user and reuses it on follow-up turns.
- The **New conversation** button starts a fresh thread: the next message is sent
  **without** a `conversation_id`, which is the signal for the server to begin a
  new conversation. The previous conversation is kept in history (not deleted).
- The RAG server remains the source of truth for full transcripts.
