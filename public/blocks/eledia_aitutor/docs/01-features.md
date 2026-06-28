# Features

Dieses Dokument beschreibt das gewuenschte Produktverhalten fuer
`block_eledia_aitutor`.

---

## Produkt-Uebersicht

Der eLeDia.ai Tutor ist ein Moodle-Block, der Lernenden und Lehrenden eine
eingebettete Chat-Oberflaeche bereitstellt. Die eigentliche Antwortgenerierung
liegt bei einem externen RAG/Tutor-MCP-Server. Moodle bleibt die Vertrauens- und
Kontextquelle.

## Kernkonzepte

- **Tutor-Block:** Moodle UI, Consent, Limits und Chat-Frontend.
- **Moodle MCP Connector:** Optional. `webservice_elediamcp` erstellt
  nutzerbezogene Tokens fuer Moodle-MCP-Zugriffe, wenn MCP freigeschaltet ist.
- **RAG/Tutor-MCP-Server:** Externer Dienst, der `tools/call` verarbeitet und
  Antworten erzeugt.
- **Tutor Profile:** Konfigurierbare Persona, Branding- und UI-Vorgaben.

---

## Features

### feat01 Moodle-native Tutor chat

**Ziel**
Nutzer koennen innerhalb von Moodle mit einem Tutor chatten, ohne dass Secrets
oder Server-Tokens in den Browser gelangen.

**Akzeptanzkriterien**

- feat01.AC01
  Given: Ein Nutzer hat `block/eledia_aitutor:use`
  When: Der Tutor-Block oder die Tutor-Seite geoeffnet wird
  Then: Die Chat-UI wird angezeigt, sofern Kurs-/Globalchat aktiviert ist

- feat01.AC02
  Given: Der Nutzer sendet eine Nachricht
  When: Der Block die Anfrage verarbeitet
  Then: Moodle ruft serverseitig den konfigurierten RAG/Tutor-MCP-Endpunkt auf
  und sendet keine Secrets an den Browser

### feat02 Moodle MCP token delegation

**Ziel**
Der Tutor kann im Namen des eingeloggten Moodle-Nutzers auf erlaubte Moodle-MCP
Tools zugreifen.

**Akzeptanzkriterien**

- feat02.AC01
  Given: MCP ist freigeschaltet, `webservice_elediamcp` ist installiert und ein
  MCP-externer Dienst ist ausgewaehlt
  When: Eine Chat-Nachricht verarbeitet wird
  Then: Der Block provisioniert ein nutzerbezogenes MCP-Token fuer diesen Dienst

- feat02.AC02
  Given: MCP ist freigeschaltet, aber kein MCP-externer Dienst ist ausgewaehlt
  When: Die Chat-UI geladen wird
  Then: Der Nutzer sieht eine klare Konfigurationsmeldung

- feat02.AC03
  Given: MCP ist nicht freigeschaltet
  When: Eine Chat-Nachricht verarbeitet wird
  Then: Der Block ruft den Tutor ohne Moodle-MCP-Token auf

### feat03 Tutor configuration and profiles

**Ziel**
Admins und berechtigte Lehrende koennen Tutor-Persona, Branding und Verhalten
konfigurieren, ohne Code zu aendern.

**Akzeptanzkriterien**

- feat03.AC01
  Given: Ein Admin oeffnet die Block-Einstellungen
  When: Persona-/Branding-Werte angepasst werden
  Then: Neue Block-Instanzen verwenden die Site-Defaults

- feat03.AC02
  Given: Eine Einstellung ist fuer Teacher-Overrides freigegeben
  When: Ein Teacher eine Block-Instanz konfiguriert
  Then: Die Instanz kann diesen Wert lokal ueberschreiben

### feat04 Privacy, consent and deletion

**Ziel**
Nutzer verstehen vor der ersten Nutzung, welche Daten verarbeitet werden, und
koennen eigene Tutor-Daten loeschen.

**Akzeptanzkriterien**

- feat04.AC01
  Given: Ein Nutzer hat die Datenschutz-Hinweise noch nicht bestaetigt
  When: Der Tutor geoeffnet wird
  Then: Der Consent-Gate wird angezeigt

- feat04.AC02
  Given: Remote-Delete-Tools sind konfiguriert
  When: Der Nutzer eigene Tutor-Daten loescht
  Then: Moodle loescht lokale Pointer und fordert Remote-Loeschung beim
  RAG/Tutor-MCP-Server an
