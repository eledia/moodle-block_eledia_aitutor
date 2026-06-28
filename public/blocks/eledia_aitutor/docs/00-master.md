# eLeDia.ai Tutor -- Master

> Zentraler Einstiegspunkt fuer KI-gestuetzte Arbeit am Moodle-Block
> `block_eledia_aitutor`.

---

## 1. Projekt-Meta

- **Name:** eLeDia.ai Tutor (`block_eledia_aitutor`)
- **Ziel:** Moodle-nativer AI-Tutor-Block, der serverseitig mit einem externen
  RAG/Tutor-MCP-Server spricht. Moodle-MCP-Token fuer Moodle-Werkzeuge sind
  optional und werden nur bereitgestellt, wenn MCP freigeschaltet ist.
- **Kurzbeschreibung:** Chat-Frontend und sicherer Connector fuer Moodle.
  Der Block rendert die Tutor-UI, erzwingt Consent/Capabilities/Limits,
  leitet Chat-Anfragen an einen RAG/Tutor-MCP-Endpunkt weiter und provisioniert
  bei aktivem MCP Moodle-MCP-Tokens ueber `webservice_elediamcp`.
- **Tech Stack:** Moodle Plugin, PHP, AMD JavaScript, Mustache, Moodle Web
  Services, MCP Streamable HTTP, PHPUnit/Behat/PHPCS.
- **Plugin-Pfad:** `public/blocks/eledia_aitutor`
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

### adr01 DevFlow lebt im Plugin-Dokumentationsordner

**Status:** accepted

**Kontext**
Das Plugin-Repository spiegelt eine Moodle-5-Document-Root-Struktur unter
`public/`. Der DevFlow soll Projektkontext liefern und zugleich im Plugin-Repository dort liegen, wo auch die fachliche Dokumentation fuer die Moodle-Plugin-Submission gepflegt wird.

**Entscheidung**
Der DevFlow liegt unter `public/blocks/eledia_aitutor/docs/`. Diese Lage passt zum Moodle-5-`public/`-Layout und haelt die Plugin-Dokumentation zusammen.

**Folgen**
Die DevFlow-Dateien sind Teil der Plugin-Dokumentation. Der Projekt-Root bleibt frei von separaten DevFlow-Ordnern; Reviews und Handovers werden direkt in die sechs Hauptdokumente einsortiert.

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

### adr03 Lose Markdown-Dokumente gehoeren in die DevFlow-Struktur

**Status:** accepted

**Kontext**
Im Projekt-Root lagen zeitweise Review- und Handover-Dateien
(`HANDOVER.md`, `CODE_REVIEW_review_johannes.md`,
`UX_REVIEW_review_johannes.md`). Dadurch entstanden parallele
Dokumentationsquellen neben dem DevFlow.

**Entscheidung**
Lose Markdown-Dokumente werden in die sechs DevFlow-Hauptdokumente einsortiert:
Projektstand und Entscheidungen nach `00-master.md`, Produktverhalten nach
`01-features.md`, Nutzungshinweise nach `02-user-doc.md`, technische Details
nach `03-dev-doc.md`, operative Aufgaben nach `04-tasks.md` und Findings,
Bugs sowie Verifikation nach `05-quality.md`.

**Folgen**
Der Projekt-Root und der Plugin-`docs/`-Ordner bleiben frei von parallelen Guide-Dateien. Neben `00-master.md` bis `05-quality.md` bleiben nur `privacy.md` und `security.md` als thematische Zusatzdokumente bestehen. Neue Reviews, Handovers, User Guides, Admin Guides und Integrationsnotizen werden direkt in den passenden DevFlow-Abschnitten gepflegt.

---

## 6. Aktueller Review-Stand

### Stand 2026-06-27 (CodeChecker)

- Offizieller Moodle CodeChecker (`moodlehq/moodle-cs`, `phpcs --standard=moodle`)
  ueber das gesamte Plugin: **0 errors, 0 warnings** (`test05`, `task07`).
- Ausgangslage 920 Errors + 660 Warnings (rein Style); behoben via PHPCBF,
  manueller Aufloesung, Lang-Sortierung (EN/DE) und `MOODLE_INTERNAL`-Bereinigung.
- `bug02`-Reconciliation: Der in Runde 1 ergaenzte `MOODLE_INTERNAL`-Guard wurde
  in seiteneffektfreien/autoloaded Dateien wieder entfernt (sicherheitsneutral,
  vom Standard so verlangt).
- Beifang `bug18` (S4, offen): `MODIFIER_COMPACT == 'full'` in `plugin_page.php`
  -- moeglicher Copy-Paste-Fehler, zur Klaerung erfasst.
- Submission-Ziel: Moodle Plugins Directory.

### Stand 2026-06-26 (Folge-Review)

- Plugin-Version: `0.14.1` (`$plugin->version = 2026061601`).
- Re-Verifikation: Die Fixes der ersten Review-Runde (`bug02`-`bug08`) wurden am
  Code erneut bestaetigt und sind unveraendert vorhanden (siehe `test02`).
- Folge-Review `review_johannes` (Backend + Frontend, je ein gezielter
  Review-Durchlauf) ergab fuenf neue Befunde, erfasst als `bug13` bis `bug17`
  und gebuendelt in `task06`:
  - `bug13` (S2, gefixt 2026-06-27): Stored XSS ueber ungepruefte SVG-Uploads
    (Logo/Avatar). Behoben per Force-Download in `lib.php` und gehaertetem
    `tutor_io::is_safe_image()` (Defense in Depth).
  - `bug14` (S3): `customcss` erlaubt `</style>`-Breakout (Admin-Stored-XSS,
    CSP-Bypass).
  - `bug15`/`bug16`/`bug17` (S3/S4): Fokusring History-Button, Heading-Outline
    im Privacy-Modal, fragiles Triple-Mustache-Muster in `appendFailure`.
- `bug09` (2026-06-27/28) gefixt: Der Help-Link der Plugin-Shell zeigt auf die
  plugin-eigene `help.php`. LernHive kann dieselbe Dokumentation optional im
  Support-Hub rendern, ist aber kein Ziel der Tutor-Shell mehr. Das Plugin hat
  damit **keine harte Laufzeit-Abhaengigkeit** auf `local_lernhive`; alle
  uebrigen Integrationen sind per `class_exists`/`get_plugin_directory`
  abgesichert.
- Weiterhin offen aus der UX-Runde: `bug10` (Settings-Shell-Fallback),
  `bug11` (Fokus-/A11y-Details inkl. `role="alert"` in `privacy_info.mustache`).
  Erfasst in `task05`.
- Verifizierte saubere Flaechen (kein Finding): External/AJAX-Capability- und
  Context-Checks (keine IDOR), SSRF-Schutz im `rag_client`/`security.php`,
  Token-Provisionierung, Consent-Gating der RAG-Aufrufe, Privacy-Provider.
- PHPUnit/Behat bleiben fuer eine reproduzierbare Abschlussfreigabe offen,
  weil die lokale PHPUnit-Umgebung nicht initialisiert ist.

---

## 7. Dokumentationsstruktur

Der Plugin-Dokumentationsordner enthaelt bewusst nur die sechs DevFlow-Hauptdokumente
plus zwei thematische Zusatzdokumente:

- `00-master.md` -- Projektstand, ADRs und Arbeitsregeln.
- `01-features.md` -- Produktverhalten und Akzeptanzkriterien.
- `02-user-doc.md` -- konsolidierte Nutzer-, Lehrenden- und Admin-Dokumentation.
- `03-dev-doc.md` -- Architektur, RAG-/MCP-Integration und Entwicklerhinweise.
- `04-tasks.md` -- operative Tasks und offene Fragen.
- `05-quality.md` -- Bugs, Tests und Verifikation.
- `privacy.md` -- Privacy-/GDPR-Details.
- `security.md` -- Security-Modell und Review-Checkliste.

Fruehere Einzeldateien wie Admin Guide, Teacher Guide, User Guide, Troubleshooting,
Solution Overview, Integration Guide und RAG Server Spec sind in diese Struktur
einsortiert.
