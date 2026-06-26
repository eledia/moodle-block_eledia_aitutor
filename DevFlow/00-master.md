# eLeDia.ai Tutor -- Master

> Zentraler Einstiegspunkt fuer KI-gestuetzte Arbeit am Moodle-Block
> `block_elediaaitutor`.

---

## 1. Projekt-Meta

- **Name:** eLeDia.ai Tutor (`block_elediaaitutor`)
- **Ziel:** Moodle-nativer AI-Tutor-Block, der serverseitig mit einem externen
  RAG/Tutor-MCP-Server spricht und Moodle-Nutzerkontext ueber kurzlebige,
  nutzerbezogene MCP-Tokens bereitstellt.
- **Kurzbeschreibung:** Chat-Frontend und sicherer Connector fuer Moodle.
  Der Block rendert die Tutor-UI, erzwingt Consent/Capabilities/Limits,
  provisioniert Moodle-MCP-Tokens ueber `webservice_elediamcp` und leitet
  Chat-Anfragen an einen RAG/Tutor-MCP-Endpunkt weiter.
- **Tech Stack:** Moodle Plugin, PHP, AMD JavaScript, Mustache, Moodle Web
  Services, MCP Streamable HTTP, PHPUnit/Behat/PHPCS.
- **Plugin-Pfad:** `public/blocks/elediaaitutor`
- **DevFlow-Quelle:** `jmoskaliuk/eLeDia.OS_DevFlow`

---

## 2. Session-Start

1. Dieses Dokument lesen.
2. `04-tasks.md` lesen und offene `taskXX`/`qXX` identifizieren.
3. Passende Feature-Definition in `01-features.md` lesen.
4. Bei Moodle-Themen `Skills/moodle-framework.md` und `Skills/moodle-dev.md`
   konsultieren.
5. Bei UI/Accessibility-Themen `Skills/eledia-moodle-ux.md`,
   `Skills/moodle-design-system.md` und `Skills/webui-accessibility-auditor.md`
   konsultieren.
6. Keine impliziten Produktentscheidungen treffen. Unklarheiten als `qXX` in
   `04-tasks.md` erfassen.

---

## 3. Definition of Done

Ein Feature ist erst done, wenn:

- `01-features.md` Intent und Akzeptanzkriterien enthaelt.
- `02-user-doc.md` das sichtbare Verhalten beschreibt.
- `03-dev-doc.md` die Implementierung beschreibt.
- relevante `testXX` in `05-quality.md` gruen sind oder ein bewusstes Restrisiko
  dokumentieren.
- keine blockierenden `bugXX` offen sind.
- PO Sign-off erfolgt ist.

---

## 4. ID-System

| Prefix | Bedeutung | Datei |
|---|---|---|
| `featXX` | Feature | `01-features.md` |
| `taskXX` | Task | `04-tasks.md` |
| `qXX` | Offene Frage | `04-tasks.md` |
| `bugXX` | Bug | `05-quality.md` |
| `testXX` | Test/Verifikation | `05-quality.md` |
| `adrXX` | Architekturentscheidung | `00-master.md` |

---

## 5. ADRs

### adr01 DevFlow lebt im Repo unter `DevFlow/`

**Status:** accepted

**Kontext**
Das Plugin-Repository spiegelt eine Moodle-5-Document-Root-Struktur unter
`public/`. Der DevFlow soll Projektkontext liefern, aber nicht als Moodle-Code
oder Plugin-Datei erscheinen.

**Entscheidung**
Der DevFlow liegt im Top-Level-Ordner `DevFlow/`.

**Folgen**
Der Moodle-Code bleibt unter `public/` unveraendert, und DevFlow-Dateien koennen
separat gepflegt, reviewed und bei Bedarf ignoriert/exportiert werden.

### adr02 Review-Fixes vor UX-Iteration priorisieren

**Status:** accepted

**Kontext**
Der Code-Review vom 2026-06-25 enthielt kritische und hohe Befunde zu
Moodle-Guard, Deserialisierung und Privacy-Consent. Parallel laufen UX-Arbeiten
an Plugin Shell, Tutor-Verwaltung und Blockinstanz-Editor.

**Entscheidung**
Sicherheits- und Moodle-Core-Compliance-Fixes haben Vorrang vor weiterer
optischer Feinarbeit. Der DevFlow dokumentiert Review-Fixes als `task04` und
die zugehoerigen Bugs in `05-quality.md`.

**Folgen**
Neue UX-Aenderungen muessen weiterhin gegen die Review-Baselines laufen:
kein nacktes `unserialize()`, keine externe Kommunikation vor Consent, keine
Inline-JS/CSS-Hooks und keine veralteten Context-Aliasse in Produktiv-PHP.

### adr03 Root-Markdown gehoert in den DevFlow

**Status:** accepted

**Kontext**
Im Projekt-Root lagen zeitweise Review- und Handover-Dateien
(`HANDOVER.md`, `CODE_REVIEW_review_johannes.md`,
`UX_REVIEW_review_johannes.md`). Dadurch entstanden parallele
Dokumentationsquellen neben dem DevFlow.

**Entscheidung**
Root-Markdown wird in die sechs DevFlow-Hauptdokumente einsortiert:
Projektstand und Entscheidungen nach `00-master.md`, Produktverhalten nach
`01-features.md`, Nutzungshinweise nach `02-user-doc.md`, technische Details
nach `03-dev-doc.md`, operative Aufgaben nach `04-tasks.md` und Findings,
Bugs sowie Verifikation nach `05-quality.md`.

**Folgen**
Der Projekt-Root bleibt frei von losen `.md`-Dateien. Neue Reviews oder
Handover-Notizen werden direkt in den passenden DevFlow-Abschnitten gepflegt.

---

## 6. Aktueller Review-Stand

### Stand 2026-06-26

- Sicherheitsreview `review_johannes`: kritische und hohe Befunde sind
  umgesetzt oder als offene Tests dokumentiert.
- UX/UI-Review `review_johannes`: Plugin-Shell-Fallbacks, Help-Link ohne
  `local_lernhive`, Fokus-/A11y-Details und Settings-Shell-Robustheit sind als
  `task05`/`bug09` bis `bug12` im DevFlow erfasst.
- PHPUnit/Behat bleiben fuer eine reproduzierbare Abschlussfreigabe offen,
  weil die lokale PHPUnit-Umgebung nicht initialisiert ist.
