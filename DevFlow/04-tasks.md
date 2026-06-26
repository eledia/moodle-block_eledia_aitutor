# Tasks

Dies ist das operative Zentrum fuer die Arbeit am aiTutor.

---

## Neu

Unstrukturierter Input landet hier und wird in `taskXX` oder `qXX` triagiert.

Keine untriagierten Eintraege.

---

## Klaerung benoetigt

### q01 Lokale RAG-URL fuer Docker final festlegen
Linked: feat01 / feat02
Asked-by: KI
Status: open

**Frage**
Soll der lokale Tutor gegen `local_literag` in derselben Moodle-Instanz laufen
oder gegen den manuellen Testserver unter `tests/manual/tutor_mcp_server.py`?

**Kontext**
Die Browser-URL `http://localhost:8080/...` ist aus dem Moodle-Container nicht
automatisch derselbe Netzwerkpfad. Fuer End-to-End-Tests muss die RAG-URL
serverseitig erreichbar sein.

---

## Tasks

### task01 DevFlow initial anlegen
Status:    done
Feature:   -
Prioritaet: P1

**Ziel**
Projektbezogenen DevFlow auf Branch `review_johannes` anlegen.

**Ergebnis**
`DevFlow/` enthaelt Master, Feature-, User-, Dev-, Task- und Quality-Dateien
sowie relevante Skills aus `jmoskaliuk/eLeDia.OS_DevFlow`.

### task02 Lokalen Tutor-End-to-End-Smoke pruefen
Status:    open
Feature:   feat01 / feat02
Prioritaet: P1
Linked:    q01, test01

**Ziel**
Eine Chat-Nachricht in `http://localhost:8080` erfolgreich durch den Block bis
zum RAG/Tutor-MCP-Endpunkt schicken und eine Antwort erhalten.

**Schritte**
1. Ziel-RAG-Server festlegen (`q01`).
2. Tutor-Block-Einstellungen pruefen.
3. Als Moodle-Nutzer mit `block/elediaaitutor:use` Chat oeffnen.
4. Testnachricht senden.
5. Ergebnis und Logs in `05-quality.md` dokumentieren.

**Erwartetes Ergebnis**
Der Tutor antwortet ohne Konfigurationsfehler.

### task03 Premium-/Review-Aenderungen dokumentieren
Status:    done
Feature:   feat03
Prioritaet: P2

**Ziel**
Die aktuell im Workspace vorhandenen Aenderungen rund um Registry, Tutor Profile
und `premium.php` in DevFlow-Dokumentation einordnen, sobald ihr Scope bestaetigt
ist.

**Hinweis**
Der Workspace hatte diese Aenderungen bereits vor dem DevFlow-Anlegen. Sie
wurden nicht veraendert.

**Ergebnis 2026-06-26**
Der Scope ist weiterhin als bestehende Workspace-Aenderung dokumentiert. Keine
zusaetzlichen Code-Aenderungen wurden allein fuer diesen Task vorgenommen.

### task04 Code-Review `review_johannes` abarbeiten
Status:    done
Feature:   feat01 / feat02 / feat03
Prioritaet: P0
Linked:    bug02, bug03, bug04, bug05, bug06, bug07, bug08, test02

**Ziel**
Die Findings aus `CODE_REVIEW_review_johannes.md` umsetzen und die kritischen
Sicherheitsbefunde vor weiterer UX-Arbeit schliessen.

**Umgesetzt**
- `block_elediaaitutor.php`: `MOODLE_INTERNAL`-Guard ergaenzt.
- `db/upgrade.php`: nacktes `unserialize()` durch `unserialize_object()` ersetzt.
- LTM-Sync: `ltm::sync_to_rag()` bricht ohne Privacy-Consent ab.
- Citation-URLs: Server-seitige `PARAM_URL`-Normalisierung in Live- und History-Flows.
- Admin-Navbar-Hook: Inline-JS/CSS entfernt; CSS liegt in `styles.css`, Verhalten in AMD.
- Produktiv-PHP: alte Context-Alias-Klassen auf `\core\context\...` umgestellt.
- Entry-Points: `declare(strict_types=1)` ergaenzt.
- `version.php`: `$plugin->supported = [402, 502]` ergaenzt.
- `manage_tutors.php`: direkte Rendering-DB-Abfrage fuer Blockinstanzen in
  `tutor_apply::instance_records()` ausgelagert.

**Verifikation**
- Lokaler PHP-Lint ueber alle Plugin-PHP-Dateien: gruen.
- Container PHP-Lint ueber alle Plugin-PHP-Dateien: gruen.
- JS-Syntaxcheck fuer `amd/src/admin_launcher.js`: gruen.
- Moodle-Container wurde aktualisiert und Caches wurden gepurged.
- PHPUnit konnte nicht ausgefuehrt werden, weil die lokale Moodle-PHPUnit-
  Konfiguration nicht initialisiert ist (`@root@` in `phpunit.xml.dist`).

### task05 UX/UI-Review `review_johannes` abarbeiten
Status:    in-progress
Feature:   feat01 / feat03 / feat04
Prioritaet: P1
Linked:    bug09, bug10, bug11, bug12, test03

**Ziel**
Die Findings aus dem UX/UI-Review vom 2026-06-26 in die Plugin-Shell,
Settings-Shell und Tutor-Oberflaechen uebertragen, ohne die Moodle-native
Bedienbarkeit zu ueberstylen.

**Review-Schwerpunkte**
- Kein toter Help-Link auf `/local/lernhive/support.php`, wenn `local_lernhive`
  nicht installiert ist.
- Settings-Shell robust gegen fehlende `headerHtml`-Konfiguration machen.
- Fokus- und Tastaturbedienung fuer zentrale Controls sichtbar halten.
- Fallback-CSS fuer im Template angebotene `lh-*`-Slots vollstaendig machen.
- Dekorative Preview-Elemente fuer Assistive Technology ausblenden.
- Root-Markdown aus Review/Handover in DevFlow ueberfuehren.

**Umgesetzt**
- Root-Dateien `HANDOVER.md`, `CODE_REVIEW_review_johannes.md` und
  `UX_REVIEW_review_johannes.md` in DevFlow einsortiert.
- Override-Checkboxen in der Settings-Shell wieder Moodle-schlicht gestaltet:
  kein Spezial-Pill fuer `Standard: Ja/Nein`, keine fette
  `Ueberschreiben erlauben`-Beschriftung.

**Offen**
- Code-seitige Umsetzung der UX-Review-Findings aus `bug09` bis `bug12`
  abschliessen und lokal verifizieren.
