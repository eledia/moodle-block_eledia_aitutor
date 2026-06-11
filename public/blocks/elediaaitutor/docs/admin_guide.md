# Admin configuration guide

All settings live at **Site administration ▸ Plugins ▸ Blocks ▸ eLeDia.ai Tutor**.

## 1. RAG / Tutor MCP server

| Setting | Notes |
|---|---|
| **RAG MCP server URL** | The MCP *Streamable HTTP* endpoint, e.g. `https://rag.example.com/mcp`. Must be HTTPS (see *Allow insecure transport* for local dev). This is the only host the block ever contacts. |
| **RAG authorization method** | `None`, `Bearer token`, or `Custom header`. |
| **RAG authorization token** | The bearer token, or a full `Header-Name: value` line for the custom method. Stored with `configpasswordunmask`; never sent to the browser. |
| **Chat tool name** | MCP tool invoked via `tools/call`. Default `tutor_chat`. |
| **History tool name** | Optional; e.g. `tutor_get_history`. Leave empty to disable history loading. |
| **Delete tool name** | Optional; e.g. `tutor_delete_conversation`. When set, deleting a conversation in Moodle also deletes it on the RAG server (best-effort). Leave empty to delete only the local pointer. |
| **Delete user data tool name** | Optional, recommended; e.g. `tutor_delete_user_data`. One call erases ALL data the RAG server holds for the user (transcripts + long-term memory). Preferred over per-conversation deletion for "delete all my data". |
| **Memory opt-in tool name** | Optional; e.g. `tutor_set_memory_optin`. Setting it declares the RAG server memory-capable: consent changes are pushed immediately, every chat carries `ltm_enabled`, opt-out erases stored memories. Leave empty while the server has no memory support. |
| **Allow insecure transport** | Permits `http://`. Local development only. |
| **Allow private / internal RAG host** | Bypasses Moodle's cURL security (blocked private hosts / non-standard ports) **for the configured RAG host only**. Enable for an internal-network or local-dev RAG server (e.g. `host.docker.internal`). Off by default — keep it off in production. |
| **Request timeout** | Seconds to wait for a RAG response. |
| **Enable streaming** | Parse Server-Sent-Event responses when the server streams. |

## 2. Moodle MCP token handling

| Setting | Notes |
|---|---|
| **MCP external service** | The external service that user tokens are scoped to. Populated from the services declared in `webservice_elediamcp`. **Required.** |
| **Token lifetime** | Seconds a provisioned token stays valid before a fresh one is minted. `0` = non-expiring (not recommended). |

The connector mints **one user-scoped token per user/service**, attributed to
the component `block_elediaaitutor`. The plain value is held only in a
short-lived application cache (never the database) and reused until it expires.

## 3. Behaviour and limits

| Setting | Default |
|---|---|
| Default display mode | `embedded` |
| Enable global chat | Yes |
| Enable course chat | Yes |
| Maximum message length | 4000 |
| Rate limit (messages/minute/user) | 20 (0 disables) |
| Question analytics | Off (opt-in; logs learner questions for the course report — see privacy.md) |
| Question analytics retention (days) | 180 (daily prune task) |
| Recluster tool name | Empty (optional; e.g. `tutor_recluster_questions` — a nightly task converges hotspot topic labels via the RAG server) |
| Logging verbosity | Normal |

When question analytics is enabled, teachers with
`block/elediaaitutor:viewreports` get a **Tutor analytics** link in the block
footer leading to `/blocks/elediaaitutor/report.php?courseid=N` — aggregated,
name-free views of asked questions (totals, per-day, grounded share, recent
questions).

## 4. Capabilities

| Capability | Default roles |
|---|---|
| `block/elediaaitutor:addinstance` | editingteacher, manager |
| `block/elediaaitutor:myaddinstance` | authenticated user |
| `block/elediaaitutor:use` | all learner/staff roles |
| `block/elediaaitutor:manage` | editingteacher, manager |
| `block/elediaaitutor:viewhistory` | all learner/staff roles |
| `block/elediaaitutor:deleteownhistory` | all learner/staff roles |

Users without `block/elediaaitutor:use` see no chat.

## 5. Caching (production)

Map the `usertoken` and `ratelimit` application caches to a shared store
(Redis/Memcached) under **Site administration ▸ Plugins ▸ Caching** so the
token cache and rate limiter work correctly across a cluster.
