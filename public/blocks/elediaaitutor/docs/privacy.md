# Privacy documentation

The block implements the Moodle Privacy API
(`\block_elediaaitutor\privacy\provider`).

## Stored locally in Moodle

Table `block_elediaaitutor_conv` — one row per conversation **pointer**:

- `userid` — owner
- `courseid` — course context (or none)
- `conversationid` — the RAG server's conversation identifier
- `title` — optional display title
- `lastpreview` — a short preview (≤ 200 chars) of the most recent message
- `timecreated`, `timemodified`

This data is **exported** and **deleted** by the privacy provider for subject
access / erasure requests, scoped to the system context.

> **Full chat transcripts are NOT stored in Moodle.** They live on the external
> RAG/Tutor server.

## Sent to the external RAG server

Declared as an external location (`rag_server`): your user identity (via a
user-scoped token), your message text, the course context (when provided), and
the conversation id. The RAG server stores and retains the full conversation
according to **its own** policy — document that policy separately for your data
processing records.

## MCP token metadata

The user-scoped Moodle MCP token's own metadata is owned by
`webservice_elediamcp` and is exported/erased by *that* plugin's privacy
provider. The token secret is never persisted by either plugin.

## User preferences

The block stores no personal user preferences of its own beyond the conversation
metadata above.
