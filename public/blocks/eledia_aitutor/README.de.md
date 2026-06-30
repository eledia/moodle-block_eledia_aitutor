# eLeDia.ai Tutor (block_eledia_aitutor)

[English README](README.md)

[Dokumentation](docs/02-user-doc.md) · [Datenschutz](docs/privacy.md) · [Sicherheit](docs/security.md)

Der **eLeDia.ai Tutor** ist ein Moodle-nativer Chatbot-Block. Er verbindet
Moodle serverseitig mit einem externen RAG-/Tutor-MCP-Server und stellt die
Chat-Oberfläche, sichere Token-Übergabe und Moodle-Integration bereit.

Der Block ist bewusst **kein eigener RAG-Server**. Kursinhalte, Retrieval,
LLM-Zugriff und Tool-Ausführung liegen in den angebundenen Zusatzdiensten.

- **Reifegrad:** Beta (`0.15.0`)
- **Voraussetzung:** Moodle 4.2+ (getestet mit 5.1), PHP 8.1+
- **Erforderliche Laufzeit-Integration:** `webservice_elediamcp` für den
  nutzerbezogenen Moodle-MCP-Rückruf (verpflichtend, keine Abschaltung möglich)
- **Lizenz:** GNU GPL v3 oder später
- **Autor:** Christopher Reimann · © 2026 eLeDia GmbH, Berlin

---

## Architektur

```text
Browser (AMD chat.js)
  │  Moodle core/ajax  (authentifiziert, sesskey-geschützt)
  ▼
block_eledia_aitutor external functions  ──►  chat_service
  │                                            │
  │  webservice_elediamcp-Token (verpflichtend) │  rag_client (MCP Streamable HTTP, tools/call)
  ▼                                            ▼
nutzerbezogenes Moodle-MCP-Token  ───────►  externer RAG-/Tutor-MCP-Server
                                               │
                                               ▼
                                      Moodle MCP Server / weitere Tools
```

Geheimnisse werden nicht an den Browser ausgeliefert. MCP-Token, RAG-Token und
RAG-Server-URL bleiben serverseitig in Moodle.

## Installation

1. Dieses Verzeichnis nach `blocks/eledia_aitutor` in die Moodle-Installation
   kopieren. Der Verzeichnisname muss exakt `eledia_aitutor` lauten.
2. Optional: `webservice_elediamcp` installieren und aktivieren, wenn der Tutor
   Moodle-MCP-Werkzeuge nutzen soll.
3. In Moodle **Website-Administration ▸ Mitteilungen** aufrufen, um die
   Installation bzw. Aktualisierung auszuführen.
4. Nur bei Änderungen an `amd/src` die JavaScript-Dateien neu bauen:
   ```bash
   cd /pfad/zu/moodle
   npx grunt amd --root=blocks/eledia_aitutor
   ```

Die gebauten Dateien unter `amd/build` sind enthalten, daher ist für die normale
Installation kein Build-Schritt nötig.

## Konfiguration

Die Einstellungen finden sich unter:

**Website-Administration ▸ Plugins ▸ Blöcke ▸ eLeDia.ai Tutor**

Wichtige Einstellungen:

| Einstellung | Erforderlich | Beispiel |
|---|---:|---|
| RAG-MCP-Server-URL | ja | `https://rag.example.com/mcp` |
| RAG-Authentifizierung / Token | falls benötigt | `Bearer` + Token |
| Chat-Tool-Name | ja, Standard meist passend | `tutor_chat` |
| History-Tool-Name | optional | `tutor_get_history` |
| Externer MCP-Service | ja (verpflichtend) | Service aus `webservice_elediamcp` |
| Token-Lebensdauer | ja, Standard meist passend | `3600` |

Danach kann der Block in Kursen oder auf dem Dashboard hinzugefügt werden.

## Zusatzplugins

Für den vollständigen Betrieb werden üblicherweise diese Komponenten kombiniert:

- **eLeDia MCP** (`webservice_elediamcp`, optional) für Moodle-Werkzeuge und
  nutzerbezogene MCP-Token.
- **LiteRAG** als RAG-/Tutor-MCP-Backend.
- **RAG-Ingest** zur Indexierung von Moodle-Kursinhalten.

Sind diese Zusatzplugins nicht installiert oder deaktiviert, zeigt das Dashboard
entsprechende Hinweise an.

## Tests

```bash
# Aus dem Moodle-Root.
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --filter block_eledia_aitutor

vendor/bin/phpunit blocks/eledia_aitutor/tests/rag_client_test.php

php admin/tool/behat/cli/init.php
vendor/bin/behat --tags @block_eledia_aitutor

vendor/bin/phpcs --standard=moodle blocks/eledia_aitutor
```

## Dokumentation

- [Projektkontext](docs/00-master.md)
- [Funktionen](docs/01-features.md)
- [Dokumentation für Nutzer/innen, Lehrende und Admins](docs/02-user-doc.md)
- [Entwicklung und RAG-/MCP-Integration](docs/03-dev-doc.md)
- [Aufgaben und offene Punkte](docs/04-tasks.md)
- [Qualität und Verifikation](docs/05-quality.md)
- [Datenschutz](docs/privacy.md)
- [Sicherheit](docs/security.md)

## CI, Mirror und Release

Das Entwicklungsrepo führt den Pluginordner unter `public/blocks/eledia_aitutor`,
sodass das Repo ein Moodle-5.x-Dokumentenstammverzeichnis spiegelt. Die
CI-Konfiguration liegt im Repository-Root (eine Ebene über `public/`):

```text
<repo root>/
├── .gitlab-ci.yml                  # GitLab-Pipeline (im Repo-Root)
├── .github/
│   └── workflows/moodle-ci.yml     # GitHub Moodle Plugin CI
└── public/
    └── blocks/
        └── eledia_aitutor/         # das Plugin
```

- `.gitlab-ci.yml` führt PHPCS (Moodle), PHPStan, Semgrep, Trivy, PHPUnit
  (Plugin-Coverage) und Behat gegen `MOODLE_501_STABLE` aus. Das Plugin wird in
  ein geklontes Moodle eingehängt, wobei das führende `public/` aus `PLUGIN_PATH`
  entfernt wird – so funktioniert die Pipeline auf 4.x/5.0 (ohne `public/`) und
  5.1+ (mit `public/`).
- `.github/workflows/moodle-ci.yml` führt Moodle Plugin CI (auf Basis von
  `moodlehq/moodle-plugin-ci`) über PHP 8.2–8.4 gegen `MOODLE_501_STABLE` aus.
- Für die Veröffentlichung wird der Pluginordner flach in einen GitHub-Mirror
  gespiegelt, sodass `README.md`, `version.php`, `db/`, `classes/` und
  `.github/workflows/` direkt im Repository-Root liegen.

## Gespeicherte Daten

Das Plugin speichert nur leichte Gesprächsreferenzen, z. B. die Conversation-ID
des RAG-Servers, kurze Vorschautexte und Zeitstempel. Vollständige Transkripte
liegen beim angebundenen RAG-/Tutor-Server. Details stehen in
[docs/privacy.md](docs/privacy.md).
