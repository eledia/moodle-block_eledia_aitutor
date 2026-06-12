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
| Daily message limit | 0 = unlimited (per-user daily cap, enforced server-side before any AI call; block instances may override the limit — the counter is always per user site-wide; counters live in `block_elediaaitutor_usage`, pruned after 60 days) |
| Prompt starters | Empty (suggested questions shown as chips under the welcome message, one per line, max 6; block instances may override) |
| Question analytics | Off (opt-in; logs learner questions for the course report — see privacy.md) |
| Question analytics retention (days) | 180 (daily prune task) |
| Recluster tool name | Empty (optional; e.g. `tutor_recluster_questions` — a nightly task converges hotspot topic labels via the RAG server). The task auto-provisions a powerless maintenance account (`elediaaitutor_service`, webservice-only auth, no roles/enrolments) and authenticates with its MCP token, so no shared transport secret is required for this call. |
| Logging verbosity | Normal |

Every user must acknowledge the privacy guidelines once before their first chat
turn (enforced server-side; documented in `block_elediaaitutor_consent` and the
event log, erased automatically when the account is deleted — see privacy.md).

The guidelines text itself is editable: **Privacy guidelines text** (HTML
editor, under the *Privacy* heading) replaces the built-in informational
sections with institution-specific content — including the AI accuracy notice
and the what-is-sent/stored descriptions, so the custom text must cover them.
The long-term memory opt-in and data deletion controls always remain, and
multilang filters are applied. Leave empty for the built-in default.

When question analytics is enabled, teachers with
`block/elediaaitutor:viewreports` get a **Tutor analytics** entry in the course
navigation ("More" menu) leading to `/blocks/elediaaitutor/report.php?courseid=N`
— aggregated, name-free views of asked questions (totals, hotspots, per-day,
grounded share, paginated recent questions).

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

## 6. Moodle App

Third-party blocks are not rendered by the Moodle App, so the tutor ships a
standalone page hosting the identical chat widget:

```
https://YOURSITE/blocks/elediaaitutor/view.php            (global chat)
https://YOURSITE/blocks/elediaaitutor/view.php?courseid=N (course chat)
```

`?embedded=1` switches to Moodle's chrome-less page layout (no navigation).
Login, enrolment (for course chat), the `use` capability, the consent gate and
all rate limits apply exactly as in the block — it is the same widget against
the same endpoints.

**In-app entry points (automatic).** The plugin registers Moodle App remote
add-on handlers (`db/mobile.php`) that open this page in-app via `core-iframe`
(which auto-logins same-site URLs):

- a **Tutor** entry in the course options menu (course-scoped chat), and
- an **eLeDia.ai Tutor** item in the app's main menu (global chat).

**Who decides where the tutor appears:**

- The site toggles **Enable course chat** / **Enable global chat** control
  whether the corresponding app handler is registered at all (purge caches
  after changing them).
- Per course, **the teacher decides by adding the eLeDia.ai Tutor block to the
  course** — the same opt-in as on the web. In courses without the block, the
  app entry and `view.php?courseid=N` show a friendly "not enabled in this
  course" notice instead of the chat. (The app cannot hide a remote handler
  per course, so the entry is visible everywhere but only functional where
  opted in.)

They appear after the app refreshes its remote add-ons (pull-to-refresh on the
app home, or log out/in). Requirements: HTTPS site, web services for mobile
enabled.

**Alternative: custom menu item.** A site-level entry can also be added under
**Site administration ▸ Plugins ▸ Admin tools ▸ Moodle app tools ▸ Mobile
features ▸ Custom menu items** (`tool_mobile/custommenuitems`):

```
eLeDia.ai Tutor|https://YOURSITE/blocks/elediaaitutor/view.php?embedded=1|embedded
```

**Pitfalls worth knowing:**

- **Do not link the page from a URL activity** — the app hands URL activities
  to the device browser, which has no Moodle session. The browser auto-login
  the app attempts is throttled (~one key per 6 minutes) and **always refused
  for site administrators**, so an admin testing this sees a login wall.
  Use the built-in handlers above instead.
- Test app behaviour with a **non-admin** account for the same reason.

This remains the pragmatic integration — the full web UI inside the app. A
fully native chat UI (CoreBlockDelegate templates) is a possible future step;
the entire server side (tokens, RAG calls, consent, analytics) would be reused
unchanged.
