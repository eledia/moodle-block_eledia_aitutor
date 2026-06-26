# Entwickler-Dokumentation

Dieses Dokument beschreibt die technische Ist-Struktur von
`block_elediaaitutor`.

---

## Architektur

```text
Browser / AMD chat.js
  -> Moodle core/ajax external functions
  -> block_elediaaitutor local services
  -> webservice_elediamcp token provisioning
  -> RAG/Tutor MCP endpoint via server-side HTTP
```

Der Browser spricht nur mit Moodle. RAG-URL, RAG-Auth und Moodle-MCP-Token
bleiben serverseitig.

---

## Wichtige Pfade

| Pfad | Zweck |
|---|---|
| `public/blocks/elediaaitutor/block_elediaaitutor.php` | Moodle Block Entry |
| `public/blocks/elediaaitutor/settings.php` | Siteweite Admin Settings |
| `public/blocks/elediaaitutor/classes/external/` | AJAX/Webservice Endpunkte |
| `public/blocks/elediaaitutor/classes/local/chat_service.php` | Chat-Orchestrierung |
| `public/blocks/elediaaitutor/classes/local/rag_client.php` | MCP HTTP Client |
| `public/blocks/elediaaitutor/classes/local/token_provider.php` | Moodle-MCP Token Provisioning |
| `public/blocks/elediaaitutor/classes/local/registry.php` | Tutor/Branding Registry |
| `public/blocks/elediaaitutor/amd/src/chat.js` | Chat Frontend Quelle |
| `public/blocks/elediaaitutor/amd/build/chat.min.js` | Gebautes AMD Asset |
| `public/blocks/elediaaitutor/templates/` | Mustache Templates |
| `public/blocks/elediaaitutor/tests/` | PHPUnit Tests |

---

## Externe Abhaengigkeiten

- Moodle 4.2+ laut Plugin-Metadaten, getestet gegen Moodle 5.x.
- PHP 8.1+.
- `webservice_elediamcp` als Runtime-Abhaengigkeit fuer echte Token-
  Provisionierung.
- RAG/Tutor-MCP-Server mit `tools/call`, mindestens `tutor_chat`.

---

## Lokaler Betrieb

Im aktuellen lokalen Setup laeuft Moodle unter `http://localhost:8080`.
Der Block ist unter `public/blocks/elediaaitutor` installiert. Fuer den Tutor
muessen in Moodle die Block-Einstellungen gesetzt sein:

- RAG MCP server URL
- Chat tool name
- MCP external service
- lokale HTTP/private-host Opt-ins, falls ein lokaler Docker-Endpunkt verwendet
  wird

---

## Tests

Aus Moodle-Root:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --filter block_elediaaitutor
vendor/bin/phpunit blocks/elediaaitutor/tests/rag_client_test.php
vendor/bin/phpcs --standard=moodle blocks/elediaaitutor
```

Behat:

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags @block_elediaaitutor
```
