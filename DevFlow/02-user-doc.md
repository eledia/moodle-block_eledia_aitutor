# Benutzer-Dokumentation

Dieses Dokument beschreibt den Tutor aus Sicht der Moodle-Nutzer.

---

## Zielgruppen

- Lernende, die kursbezogene oder allgemeine Hilfe im Chat erhalten.
- Lehrende, die einen Tutor-Block in Kursen platzieren und konfigurieren.
- Admins, die RAG/MCP, Limits, Privacy und Branding siteweit einrichten.

---

## Haupt-Use-Cases

1. Lernende stellen im Kurs eine Frage an den Tutor.
2. Lernende nutzen den globalen Tutor ausserhalb eines konkreten Kurses.
3. Lehrende passen eine Tutor-Instanz fuer ihren Kurs an.
4. Admins konfigurieren RAG/MCP-Verbindung, Limits, Datenschutz und Profile.

---

## Bedienung

### Tutor nutzen (`feat01`, `feat04`)

1. Moodle oeffnen und anmelden.
2. Kurs oder Dashboard mit eLeDia.ai Tutor oeffnen.
3. Datenschutz-Hinweise bestaetigen, falls sie noch nicht bestaetigt wurden.
4. Frage in das Chatfeld schreiben.
5. Antwort lesen und bei Bedarf Folgefrage stellen.

**Erwartetes Ergebnis**
Der Tutor antwortet im Chat. Verlauf und optionale Remote-History haengen von
der Serverkonfiguration ab.

### Tutor im Kurs bereitstellen (`feat03`)

1. Bearbeiten im Kurs einschalten.
2. Block "eLeDia.ai Tutor" hinzufuegen.
3. Block konfigurieren, falls kursbezogene Overrides erlaubt sind.
4. Kurs speichern und als Nutzer mit `block/elediaaitutor:use` pruefen.

### Admin-Konfiguration (`feat02`, `feat03`, `feat04`)

1. `Site administration -> Plugins -> Blocks -> eLeDia.ai Tutor` oeffnen.
2. RAG MCP server URL und Toolnamen setzen.
3. MCP external service auswaehlen.
4. Optional Auth, Limits, Privacy-Texte und Tutor-Profil konfigurieren.
5. Caches leeren, wenn Moodle App Handler, Strings oder Defaults geaendert
   wurden.

---

## Wichtige Hinweise

- Der Block erzeugt selbst keine AI-Antworten. Er ruft einen RAG/Tutor-MCP-
  Server auf.
- Bei lokalen Docker-Setups muss die RAG-URL aus dem Moodle-Container erreichbar
  sein.
- Nutzer ohne `block/elediaaitutor:use` sehen keinen Chat.
