# Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Block shows *"configuration problem"* (managers) | Connector missing, no MCP service selected, or no RAG URL | Install/enable `webservice_elediamcp`; set **MCP external service** and **RAG MCP server URL** in the block settings. |
| Block shows *"currently unavailable"* (learners) | Same as above; learners see the neutral message | Check the admin settings; the manager view names the exact problem. |
| *"The tutor service is temporarily unavailable"* | Transport/HTTP error reaching the RAG server, or non-2xx status | Verify the URL is reachable from the Moodle server; check the cURL security helper (blocked hosts/ports) under **Site administration ▸ Security ▸ HTTP security**. |
| *"The tutor returned an unexpected response"* | RAG response is not valid JSON-RPC / not the expected shape | Confirm the server speaks MCP `tools/call` and returns `result` (see the integration guide). |
| Repeated auth failures | User token revoked/expired server-side | The connector auto-retries once with a fresh token; if it persists, confirm the MCP service is enabled and the user satisfies its required capability. |
| *"You are sending messages too quickly"* | Per-user rate limit | Increase **Rate limit (messages/minute)** or set it to `0`. |
| Chat UI does not appear / JS errors | AMD not built after editing `amd/src` | Run `npx grunt amd --root=blocks/elediaaitutor`, then purge caches. |
| Menus/overlays clipped | Theme container overflow | Overlay modes are portalled to `<body>` with a high z-index by design; if a theme still clips, check for a global `overflow:hidden` on `<body>` and a conflicting `z-index`. |

## Logs

Configuration and runtime problems are recorded as events (see
**Reports ▸ Logs**): *configuration error detected*, *Tutor request failed*,
*Tutor message sent*, *Tutor response received*, *token provisioned*. No secrets
or full message content are logged.

Raise **Logging verbosity** to *Verbose* for additional developer-mode
`debugging()` output (still secret-free).
