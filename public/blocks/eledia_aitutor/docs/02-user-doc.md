# Benutzer- und Administrationsdokumentation

Dieses Dokument konsolidiert die frueher getrennten Nutzer-, Lehrenden-, Admin-,
Troubleshooting- und Loesungsueberblick-Dokumente fuer den eLeDia.ai Tutor.

---

## Zielgruppen

- **Lernende** nutzen den Tutor als KI-gestuetzten Chat in Moodle.
- **Lehrende** platzieren und konfigurieren Tutor-Blockinstanzen in Kursen.
- **Administrator/innen** richten RAG/MCP, Limits, Datenschutz und Tutor-Designs
  websiteweit ein.

---

## Produktueberblick

Der eLeDia.ai Tutor ist ein Moodle-Block fuer einen eingebetteten KI-Chat. Die
Antwortgenerierung erfolgt nicht im Browser und nicht direkt im Block, sondern
serverseitig ueber einen konfigurierten RAG-/Tutor-MCP-Endpunkt. Moodle bleibt
die Vertrauens- und Kontextquelle: Der Tutor arbeitet mit den Rechten der
angemeldeten Person und kann nur Inhalte nutzen, die diese Person auch in Moodle
sehen darf.

Der Tutor kann je nach Konfiguration als Kursblock, angedocktes Panel, Dialog
oder Vollbildansicht erscheinen. Tutor-Designs, Persona, Begruessung,
Vorschlagsfragen und Antwortstil koennen websiteweit und, falls freigegeben,
pro Blockinstanz angepasst werden.

---

## Lernende: Tutor nutzen

1. Moodle oeffnen und anmelden.
2. Kurs, Dashboard oder Tutor-Seite mit eLeDia.ai Tutor oeffnen.
3. Datenschutzhinweise bestaetigen, falls sie noch nicht bestaetigt wurden.
4. Frage in das Chatfeld schreiben.
5. Antwort lesen und bei Bedarf Folgefrage stellen.

Wichtige Bedienhinweise:

- **Enter** sendet eine Nachricht, **Shift+Enter** fuegt einen Zeilenumbruch ein.
- Vorgeschlagene Einstiegsfragen erscheinen als Chips, wenn sie konfiguriert
  sind.
- Antwortstile koennen zwischen **Erklaeren**, **Nur Hinweise** und
  **Quiz mich** wechseln, sofern die Website oder der Block dies erlaubt.
- Antworten koennen kopiert werden.
- Fehlgeschlagene Antworten lassen sich erneut versuchen.
- **Neue Unterhaltung** startet einen frischen Verlauf.
- Wenn Verlauf aktiviert ist, oeffnet das Verlaufssymbol gespeicherte
  Unterhaltungen.
- Im Datenschutzdialog koennen Nutzende ihre eigenen Tutor-Daten loeschen.

### Kurskontext

In einem Kurs beantwortet der Tutor kursbezogene Fragen, zum Beispiel zu
Aufgaben, Materialien oder naechsten Schritten. Ausserhalb eines Kurses kann er
allgemeiner helfen, etwa zu Moodle-Navigation oder sichtbaren Kursen. Kurschat
funktioniert nur dort, wo der Tutor fuer den Kurs aktiviert ist und Kurskontext
uebergeben wird.

---

## Lehrende: Tutor im Kurs bereitstellen

1. Bearbeiten im Kurs einschalten.
2. Block **eLeDia.ai Tutor** hinzufuegen.
3. Block ueber das Kontextmenue konfigurieren.
4. Optional ein Tutor-Design anwenden, importieren oder exportieren.
5. Kurs als Nutzer/in mit `block/eledia_aitutor:use` pruefen.

Wichtige Blockinstanz-Einstellungen:

| Einstellung | Wirkung |
|---|---|
| Blocktitel | Ueberschrift des Blocks. |
| Darstellungsmodus | Angedocktes Panel, eingebettet, Dialog oder Vollbild. |
| Kurskontext uebergeben | Sendet die aktuelle Kurs-ID an den Tutor. |
| Feste Kurs-ID | Erzwingt einen bestimmten Kurskontext, z. B. auf dem Dashboard. |
| Taegliches Nachrichtenlimit | Optionales Limit fuer diese Instanz. |
| Begruessung und Vorschlagsfragen | Einstiegstext und klickbare Startfragen. |
| Persona | Name, Rolle, Tonfall, Zielgruppe und freie Instruktionen. |
| Design | Farben, Flaechen, Typografie, Nachrichtenblasen, Launcher, Logo und Avatar. |
| Verlauf | Zeigt gespeicherte Unterhaltungen, wenn global erlaubt. |

Welche Design- und Persona-Felder sichtbar sind, haengt von der
Governance-Einstellung der Website ab. Gesperrte Werte folgen immer dem
Website-Default.

---

## Administrator/innen: zentrale Einrichtung

Die zentrale Konfiguration liegt unter **Website-Administration > Plugins >
Bloecke > eLeDia.ai Tutor** und in der Plugin-Shell des Tutors.

### RAG-/Tutor-MCP-Server

| Einstellung | Hinweis |
|---|---|
| RAG MCP server URL | Streamable-HTTP-MCP-Endpunkt, z. B. `https://rag.example.com/mcp`. |
| RAG authorization method/token | Optionaler Bearer-Token oder Custom Header; bleibt serverseitig. |
| Chat tool name | MCP-Tool fuer Chatantworten, Standard `tutor_chat`. |
| History/Delete tools | Optionale Tools fuer Verlauf und Loeschung. |
| Memory opt-in tool | Optionales Tool fuer Langzeitgedaechtnis und Opt-in-Sync. |
| Allow insecure transport/private host | Nur fuer lokale Entwicklung oder bewusst interne Dienste. |
| Request timeout/Streaming | Transportverhalten fuer Antworten. |

### Moodle-MCP-Token

Wenn **MCP freischalten** aktiviert ist, nutzt der Tutor `webservice_elediamcp`,
um nutzerbezogene Moodle-MCP-Tokens fuer den konfigurierten externen Dienst zu
erzeugen. Ohne aktiviertes MCP funktioniert der Tutor als LLM-/RAG-Chat weiter,
kann aber keine Moodle-bezogenen AI-Werkzeuge, Memory-Sync oder Fernloeschung
ausfuehren. Tokens werden serverseitig provisioniert, kurzzeitig im
Applikationscache gehalten und nie an den Browser gesendet.

### Verhalten und Limits

Zentrale Optionen umfassen globalen Chat, Kurschat, maximale Nachrichtenlaenge,
Rate Limit, taegliches Nachrichtenlimit, Datenschutztexte, Frageanalyse,
Logging-Detailgrad und Re-Clustering fuer Analyse-Hotspots.

Jede Person muss die Datenschutzhinweise vor dem ersten Chat bestaetigen. Details
stehen in `privacy.md`.

### Tutor-Designs und Profile

Administrator/innen verwalten Tutor-Designs unter **Tutoren** in der Plugin-Shell.
Dort koennen Presets und eigene Tutor-Profile auf die Website oder einzelne
Blockinstanzen angewendet, dupliziert, importiert und exportiert werden.

Die Einstellungen umfassen unter anderem:

- Farben, Flaechen, Linien, Typografie und Schatten.
- Nachrichtenblasen und Statuszustaende.
- Start-Schaltflaeche, Fusszeile, Logo und Avatar.
- Persona, System-Prompt, Begruessung und Vorschlagsfragen.
- Governance, welche Werte pro Blockinstanz ueberschrieben werden duerfen.

---

## Moodle App

Da Drittanbieter-Bloecke in der Moodle App nicht wie im Web gerendert werden,
stellt der Tutor eine eigene Seite bereit:

```text
https://YOURSITE/blocks/eledia_aitutor/view.php
https://YOURSITE/blocks/eledia_aitutor/view.php?courseid=N
```

`?embedded=1` nutzt ein reduziertes Layout ohne Moodle-Chrome. Login,
Einschreibung, Capabilities, Consent und Limits gelten wie im Web.

---

## Troubleshooting

| Symptom | Wahrscheinliche Ursache | Loesung |
|---|---|---|
| Konfigurationsproblem im Block | RAG-URL fehlt oder MCP ist aktiviert, aber MCP-Service/Connector fehlt | Einstellungen pruefen; MCP nur freischalten, wenn der Connector installiert und konfiguriert ist. |
| Tutor-Dienst nicht verfuegbar | Transportfehler, falsche URL oder Serverfehler | URL aus Sicht des Moodle-Servers testen; HTTP-Security/Curl-Helper pruefen. |
| Unerwartete Tutor-Antwort | RAG-Server liefert nicht das erwartete MCP-Format | Serververtrag in `03-dev-doc.md` pruefen. |
| Wiederholte Auth-Fehler | Moodle-MCP-Token ungueltig oder Dienst deaktiviert | MCP-Service, Capabilities und Token-Lifetime pruefen. |
| Nachrichten zu schnell | Rate Limit aktiv | Limit in den Einstellungen anpassen. |
| Chat-JS wirkt alt | AMD-Build oder Moodle-Cache veraltet | AMD neu bauen und Moodle-Caches leeren. |
| Kurskontext fehlt | Kurs nicht indexiert, Kurschat aus oder Kontextuebergabe deaktiviert | Kursblock/Instanz, RAG-Ingest und Einstellung `Kurskontext uebergeben` pruefen. |

---

## Barrierefreiheit

Die Oberflaeche ist per Tastatur bedienbar, neue Antworten werden fuer
Screenreader angekuendigt und reduzierte Bewegung wird respektiert. Sichtbare
Fokuszustaende sind Teil der UI-Baseline.
