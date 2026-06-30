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
- **Moodle MCP Connector:** Fuer geerdete Antworten erforderlich.
  `webservice_elediamcp` erstellt das nutzerbezogene Token, mit dem der
  RAG/Tutor-Server in Moodle zurueckrufen kann. Im reinen LLM-Modus (kein
  Retrieval, kein Rueckruf) wird kein Token gemuenzt und der Connector wird
  nicht benoetigt.
- **RAG/Tutor-MCP-Server:** Externer Dienst, der `tools/call` verarbeitet und
  Antworten erzeugt.
- **Tutor Profile:** Konfigurierbare Persona, Branding- und UI-Vorgaben.

> **Zwei-Schichten-Modell:** Der Block *provisioniert* den geerdeten Rueckruf
> immer (im geerdeten Modus wird ein nutzerbezogenes Token gepraegt; es gibt
> keinen Abschalter im Block). Ob der Rueckruf tatsaechlich *genutzt* wird,
> entscheidet das Backend (z. B. `enable_mcp_tools` in `local_literag`). Beides
> ist getrennt: "kein Abschalter" ist eine Aussage zur Provisionierung im Block,
> kein Verbot, das Backend-seitige Tool-Nutzungsschalter umzulegen.

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
  Given: Der Kurs wird im geerdeten Modus beantwortet, `webservice_elediamcp` ist
  installiert und ein MCP-externer Dienst ist ausgewaehlt
  When: Eine Chat-Nachricht verarbeitet wird
  Then: Der Block provisioniert ein nutzerbezogenes MCP-Token fuer diesen Dienst

- feat02.AC02
  Given: Der Kurs wird im geerdeten Modus beantwortet, aber kein MCP-externer
  Dienst (bzw. der Connector) ist verfuegbar
  When: Die Chat-UI geladen wird
  Then: Manager sehen eine klare Konfigurationsmeldung; der Tutor degradiert
  nicht stillschweigend

- feat02.AC03
  Given: Der Kurs wird im reinen LLM-Modus beantwortet (kein Retrieval,
  kein Rueckruf)
  When: Eine Chat-Nachricht verarbeitet wird
  Then: Der Block ruft den Tutor ohne Moodle-MCP-Token auf; der Connector wird
  dafuer nicht benoetigt

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
