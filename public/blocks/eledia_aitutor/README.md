# eLeDia.ai Tutor (block_eledia_aitutor)

[Deutsche README](README.de.md)

[Documentation](docs/02-user-doc.md) · [Privacy](docs/privacy.md) · [Security](docs/security.md)

A polished, Moodle-native chatbot block that connects **server-side** to an
external RAG/Tutor MCP server. The block is a chat **frontend and secure
connector** only — it does not implement retrieval-augmented generation itself.

It pairs with [`webservice_elediamcp`](../../webservice/elediamcp), which turns
Moodle into an MCP server and provides the internal PHP API used here to mint
user-scoped MCP tokens. The **full grounded tutor** requires `local_ragingest`
(to build the per-course knowledge base) and `webservice_elediamcp` (for the
MCP call-back): in grounded mode the block always mints a user-scoped token so
the RAG/Tutor backend can call back into Moodle as the learner — there is **no
off switch** for this. That "no off switch" refers to the block's grounded-mode
*provisioning*, not to an install-time requirement and not to whether a backend
*uses* the call-back (e.g. literag's `enable_mcp_tools`, decided backend-side).
When a course resolves to grounded but the connector or external service is
missing, the block shows managers a configuration error instead of silently
degrading. **LLM-only chat runs standalone** — it mints no token, needs no
connector and works without `webservice_elediamcp`. The block declares **no
hard plugin dependencies** in `version.php`: it installs and upgrades
independently, and missing companions degrade gracefully.

- **Maturity:** Beta (`0.15.0`)
- **Requires:** Moodle 4.2+ (tested against 5.1), PHP 8.1+
- **Required runtime integration (grounded answers):** `webservice_elediamcp`
  for the user-scoped Moodle MCP call-back, plus `local_ragingest` for the
  knowledge base; LLM-only chat runs standalone
- **License:** GNU GPL v3 or later
- **Author:** Christopher Reimann · © 2026 eLeDia GmbH, Berlin

---

## Architecture

```text
 Browser (AMD chat.js)
   │  Moodle core/ajax  (authenticated, sesskey-protected)
   ▼
 block_eledia_aitutor external functions  ──►  chat_service
   │                                            │
   │  webservice_elediamcp token (mandatory)     │  rag_client (MCP Streamable HTTP, tools/call)
   ▼                                            ▼
 user-scoped Moodle MCP token  ───────────►  External RAG / Tutor MCP server
                                                │
                                                ▼
                                       Moodle MCP server / other tools
```

**Secrets never reach the browser.** The Moodle MCP token, the RAG authorization
token and the RAG server URL all live server-side. The browser only ever talks
to Moodle.

See the consolidated documentation:

- [Master / project context](docs/00-master.md)
- [Features](docs/01-features.md)
- [User, teacher and admin documentation](docs/02-user-doc.md)
- [Developer and RAG/MCP integration documentation](docs/03-dev-doc.md)
- [Tasks and open questions](docs/04-tasks.md)
- [Quality, bugs and verification](docs/05-quality.md)
- [Privacy](docs/privacy.md)
- [Security notes](docs/security.md)

---

## Installation

1. Copy this directory to `blocks/eledia_aitutor` in your Moodle tree (the path
   must be exactly `eledia_aitutor`).
2. Optional: install and enable `webservice_elediamcp` when the tutor should use
   Moodle MCP tools.
3. Visit **Site administration ▸ Notifications** to run the install.
4. Build the front-end (only needed if you change `amd/src`):
   ```bash
   cd /path/to/moodle
   npx grunt amd --root=blocks/eledia_aitutor
   ```
   A working `amd/build/chat.min.js` is committed, so the block runs out of the
   box without a build step.

## Required configuration

At **Site administration ▸ Plugins ▸ Blocks ▸ eLeDia.ai Tutor**:

| Setting | Required | Example |
|---|---|---|
| RAG MCP server URL | ✅ | `https://rag.example.com/mcp` |
| RAG authorization method / token | if your server needs it | `Bearer` + token |
| Chat tool name | ✅ (default ok) | `tutor_chat` |
| History tool name | optional | `tutor_get_history` |
| MCP external service | ✅ (mandatory) | one of the services configured in `webservice_elediamcp` |
| Token lifetime | ✅ (default ok) | `3600` |

Then add the **eLeDia.ai Tutor** block to a course or the Dashboard.

## Quick test commands

Paths assume the Moodle 5.1 layout where the code lives under `public/`. Run from the
Moodle root. The PHPUnit suite (115 tests) and Behat suite (22 scenarios) pass on
Moodle 5.1 / PHP 8.3 / PHPUnit 11; test metadata uses PHP attributes (`#[CoversClass]`),
the Moodle 5.1 convention.

```bash
# PHPUnit — initialise once (re-run after any version bump), then run the component suite.
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_eledia_aitutor_testsuite

# A single test file
vendor/bin/phpunit public/blocks/eledia_aitutor/tests/rag_client_test.php

# Behat — initialise once, then run this plugin's tagged scenarios (needs Selenium).
php public/admin/tool/behat/cli/init.php
vendor/bin/behat --config "$(php public/admin/tool/behat/cli/util.php --behatdir 2>/dev/null || echo behatdata/behatrun)/behat/behat.yml" --tags @block_eledia_aitutor

# Code style (requires moodlehq/moodle-cs installed via composer)
vendor/bin/phpcs --standard=moodle public/blocks/eledia_aitutor
```

> Note: the footer-branding tests assert the free-block behaviour only when the optional
> `local_elediaai_tutor_premium` add-on is **absent**; when it is installed they verify the
> unlocked behaviour instead, so the suite is green with or without the add-on.

## Continuous integration & publishing

This plugin is developed inside a full Moodle tree but published to its own
company repository, **wrapped under `public/`** so the repo mirrors a Moodle 5.x
document root. The CI configuration lives at the repository root (one level above
`public/`), not inside the plugin folder:

```text
<repo root>/
├── .gitlab-ci.yml                  # GitLab pipeline (at the repo root)
├── .github/
│   └── workflows/moodle-ci.yml     # GitHub Moodle Plugin CI mirror
└── public/
    └── blocks/
        └── eledia_aitutor/         # the plugin
```

- `.gitlab-ci.yml` runs PHPCS (Moodle), PHPStan, Semgrep, Trivy, PHPUnit
  (plugin-only coverage) and Behat against `MOODLE_501_STABLE`. It mounts the
  plugin into a cloned Moodle by stripping the leading `public/` from
  `PLUGIN_PATH` to get the frankenstyle path, so it works on 4.x/5.0 (no `public/`)
  and 5.1+ (`public/`) alike.
- `.github/workflows/moodle-ci.yml` runs Moodle Plugin CI (PHPCS, PHPMD, PHPDoc,
  Grunt, PHPUnit, Behat) across PHP 8.2–8.4 against `MOODLE_501_STABLE`.
- For publishing, the plugin folder is flattened into a GitHub mirror so that
  `README.md`, `version.php`, `db/`, `classes/` and `.github/workflows/` sit at
  the repository root, matching the Moodle Plugins Directory layout.

## What it stores

Only lightweight conversation **pointers** (the RAG server's conversation id, a
short last-message preview, timestamps). Full transcripts are owned by the RAG
server. See [docs/privacy.md](docs/privacy.md).
