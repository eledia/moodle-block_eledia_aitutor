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

## Question analytics (opt-in)

When the administrator enables **Question analytics**, the questions learners
ask (never the answers) are stored in `block_elediaaitutor_qlog` together with
course, asker, grounding flag and answer style. The asker id exists so privacy
export/erasure works — teacher reports never display identities. Rows are
pruned by a daily scheduled task after the configurable retention (default
180 days), are included in privacy export/delete, and are wiped by the user's
own "Delete all my tutor data" action. Collection is **off by default**.

When the **Recluster tool name** is additionally configured, a nightly task
re-sends recent logged question texts (without user identities) to the RAG
server to converge their topic labels. This transmits no new data category —
the same question texts already transited the same processor at chat time —
and the server must not retain the batches beyond processing (see the RAG
server specification, section A.6).

## User preferences

One user preference is stored: `block_elediaaitutor_ltm_enabled` — the explicit
opt-in to the (future) long-term memory feature. It defaults to **off**, is only
ever changed by the user themselves (audited via the *long-term memory
preference changed* event), and is declared and exported through the Privacy
API. No memory data is collected or transmitted yet; the preference only records
consent for when the feature ships.

## In-product privacy controls

The chat header has a **Privacy guidelines** button that shows learners an AI
accuracy warning, what is sent and stored, the long-term memory opt-in, and a
**Delete all my tutor data** action. Deletion always removes the local
conversation metadata; when the admin has configured a RAG delete tool it is
propagated to the external server too, otherwise the dialog states honestly that
external transcripts remain subject to the RAG service's retention policy. Every
deletion request is recorded via the *tutor data deletion requested* event.
