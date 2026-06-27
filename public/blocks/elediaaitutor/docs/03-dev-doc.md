# Entwickler-Dokumentation

Dieses Dokument konsolidiert Architektur-, Integrations- und RAG-Server-Notizen
fuer `block_elediaaitutor`.

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
bleiben serverseitig. Der externe RAG-/Tutor-Dienst ist zugleich MCP-Server fuer
den Tutor und MCP-Client gegen Moodle.

---

## Wichtige Pfade

| Pfad | Zweck |
|---|---|
| `block_elediaaitutor.php` | Moodle Block Entry. |
| `settings.php` | Siteweite Admin Settings. |
| `classes/external/` | AJAX/Webservice-Endpunkte. |
| `classes/local/chat_service.php` | Chat-Orchestrierung. |
| `classes/local/rag_client.php` | MCP HTTP Client. |
| `classes/local/token_provider.php` | Moodle-MCP Token Provisioning. |
| `classes/local/registry.php` | Tutor/Branding Registry. |
| `classes/local/security.php` | URL-, CSS- und Transport-Haertung. |
| `classes/local/tutor_io.php` | Tutor-Import/-Export und Bildvalidierung. |
| `amd/src/` | AMD-Quellen. |
| `amd/build/` | Gebaute AMD-Assets. |
| `templates/` | Mustache Templates. |
| `tests/` | PHPUnit Tests. |

---

## Externe Abhaengigkeiten

- Moodle 4.2+ laut Plugin-Metadaten, getestet gegen Moodle 5.x.
- PHP 8.1+.
- `webservice_elediamcp` fuer echte Moodle-MCP-Token-Provisionierung.
- RAG-/Tutor-MCP-Server mit Streamable HTTP und mindestens einem Chat-Tool.

`local_lernhive` ist keine harte Runtime-Abhaengigkeit. Die Plugin-Shell rendert
den LernHive-Support-Link nur, wenn das Plugin installiert ist.

---

## RAG-/Tutor-MCP-Protokoll

Der Block ist MCP-Client und ruft den konfigurierten RAG-/Tutor-Server per
JSON-RPC 2.0 `tools/call` ueber Streamable HTTP auf.

### Request

```http
POST <RAG MCP server URL>
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
Authorization: Bearer <token>        # falls konfiguriert
```

Beispiel:

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
      "conversation_id": "optional-existing-id",
      "answer_style": "explain",
      "user_lang": "de",
      "rag_enabled": true
    }
  }
}
```

### Chat-Argumente

| Feld | Bedeutung |
|---|---|
| `system_url` | Moodle-`wwwroot` fuer Callbacks. |
| `moodle_token` | Nutzerbezogenes Moodle-MCP-Token; geheim behandeln. |
| `user_message` | Validierte Nutzerfrage. |
| `course_id` | Optionaler Kurskontext. |
| `conversation_id` | Optional; fehlt bei neuer Unterhaltung. |
| `ltm_enabled` | Optionaler Consent-Status fuer Langzeitgedaechtnis. |
| `answer_style` | Server-seitig erzwungener Stil: `explain`, `hint`, `quiz`. |
| `user_lang` | Moodle-Sprachcode der nutzenden Person. |
| `rag_enabled` | Wenn `false`, darf der Server keine Retrieval-Wissensbasis nutzen. |
| `persona` | Optionale Persona-Anweisungen fuer Stimme und Tonalitaet. |

### Response

Der Block liest bevorzugt `result.structuredContent`, alternativ Textteile aus
`result.content[]`. Erkannte Felder:

| Konzept | Akzeptierte Keys |
|---|---|
| Antwort | `answer`, `text`, `message`, `response`, `content`, `output` |
| Conversation-ID | `conversation_id`, `conversationId`, `session_id`, `sessionId`, `thread_id` |
| Quellen | `sources`, `citations`, `documents`, `references` |
| Thema | `topic`, `subject` |

Quellen koennen Strings oder Objekte mit `title`/`url`/`snippet`-Aliases sein.
Antworten werden als Markdown behandelt und serverseitig ueber Moodle bereinigt.
Raw HTML sollte nicht vorausgesetzt werden.

### Optional unterstuetzte Tools

| Tool-Default | Zweck |
|---|---|
| `tutor_get_history` | Verlauf einer Conversation laden. |
| `tutor_delete_conversation` | Einzelne Conversation serverseitig loeschen. |
| `tutor_delete_user_data` | Alle serverseitigen Daten einer Person loeschen. |
| `tutor_set_memory_optin` | Langzeitgedaechtnis-Consent synchronisieren. |
| `tutor_recluster_questions` | Frageanalyse-Hotspots neu clustern. |

Optionale Tools werden nur genutzt, wenn in den Blockeinstellungen ein Toolname
konfiguriert ist.

---

## Moodle-MCP-Callbacks

Der RAG-/Tutor-Server nutzt das uebergebene Moodle-MCP-Token, um im Namen der
angemeldeten Person Moodle-Tools aufzurufen. Er darf dabei nur die Moodle-Daten
verwenden, die diese Person sehen darf. Das Token darf nie geloggt, persistiert
oder an andere Clients weitergegeben werden.

Empfohlene Regeln fuer RAG-Server:

- Immer eine stabile `conversation_id` zurueckgeben.
- `rag_enabled=false` strikt beachten und dann keine Wissensbasis abfragen.
- `answer_style=hint` darf keine vollstaendige Loesung verraten.
- In der Sprache aus `user_lang` antworten, sofern die Nutzerfrage nichts anderes
  verlangt.
- Quellen nach Relevanz sortieren; `sources[0]` ist die Primaerquelle fuer
  Analyse-Hotspots.
- Behandelte Fehler mit `isError: true` melden; Ausfaelle als JSON-RPC-Fehler.

---

## Lokaler Betrieb

Im aktuellen lokalen Setup laeuft Moodle unter `http://localhost:8080`. Der
Block ist unter `public/blocks/elediaaitutor` installiert. Fuer den Tutor muessen
mindestens gesetzt sein:

- RAG MCP server URL.
- Chat tool name.
- MCP external service.
- Lokale HTTP/private-host Opt-ins, falls ein lokaler Docker-Endpunkt verwendet
  wird.

Wichtig: Eine Browser-URL wie `http://localhost:8080/...` ist aus einem
Moodle-Container heraus nicht automatisch derselbe Host. Fuer End-to-End-Tests
muss die RAG-URL serverseitig aus dem Moodle-Container erreichbar sein.

---

## Tests und Checks

Aus dem Moodle-Root:

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

Im Review-Setup wurde der offizielle Moodle CodeChecker im Demo-Container mit
0 Errors / 0 Warnings dokumentiert. Vor Submission sollten CodeChecker, PHPUnit
und ein Browser-Smoke erneut laufen.
