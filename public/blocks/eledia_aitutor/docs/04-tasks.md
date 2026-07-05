# Tasks

Dies ist das operative Zentrum fuer die Arbeit am aiTutor.

---

## Neu

Unstrukturierter Input landet hier und wird in `taskXX` oder `qXX` triagiert.

- Uncommittete Aenderung in `classes/hook_callbacks.php`: Feinjustierung der
  SVG-Pfaddaten des Admin-Launcher-Icons (rein optisch, kein Verhalten). Bei
  naechstem Commit mitnehmen.

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
`public/blocks/eledia_aitutor/docs/` enthaelt die sechs DevFlow-Hauptdokumente
sowie die Zusatzdokumente `privacy.md` und `security.md`.

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
3. Als Moodle-Nutzer mit `block/eledia_aitutor:use` Chat oeffnen.
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
- `block_eledia_aitutor.php`: `MOODLE_INTERNAL`-Guard ergaenzt.
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
Status:    done
Feature:   feat01 / feat03 / feat04
Prioritaet: P1
Linked:    bug09, bug10, bug11, bug12, test03

**Ziel**
Die Findings aus dem UX/UI-Review vom 2026-06-26 in die Plugin-Shell,
Settings-Shell und Tutor-Oberflaechen uebertragen, ohne die Moodle-native
Bedienbarkeit zu ueberstylen.

**Review-Schwerpunkte**
- Kein Help-Link auf `/local/lernhive/support.php`; die Tutor-Shell muss ihre
  Hilfe plugin-eigen bereitstellen.
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
- `bug09` (2026-06-27/28): Help-Link der Plugin-Shell zeigt auf
  `/blocks/eledia_aitutor/help.php`. Die Seite rendert die plugin-eigene
  Dokumentation aus `docs/02-user-doc*.md`; `local_lernhive` ist fuer Hilfe
  nicht erforderlich.

**Offen**
- Keine offenen Code-Findings aus task05.

**Ergebnis 2026-06-27**
- `bug10` gefixt: `settings_shell.js` bricht robust ab, wenn `headerHtml`
  fehlt; Fallback-CSS fuer `lh-plugin-infobar`, Tags, Header-Buttons und
  Row-Actions ergaenzt.
- `bug11` gefixt: Fokus-Ringe fuer Send-Button, History-Open-Button,
  Settings-Karten und Backnav ergaenzt; History-Liste und Composer gelabelt;
  statischer Privacy-Callout nutzt `role="note"`.

### task06 Folge-Code-Review `review_johannes` abarbeiten
Status:    done
Feature:   feat01 / feat03 / feat04
Prioritaet: P0
Linked:    bug13, bug14, bug15, bug16, bug17, test04

**Ziel**
Die neuen Befunde aus dem Folge-Review vom 2026-06-26 schliessen. Prioritaet
liegt auf der Stored-XSS-Flaeche `bug13` (SVG-Upload), gefolgt von `bug14`
(`customcss`-Breakout).

**Schwerpunkte**
- SVG-Uploads sanieren oder als Download ausliefern (`bug13`, S2).
- `customcss` gegen `</style>`-Breakout absichern (`bug14`, S3).
- Fokusring fuer den History-Open-Button ergaenzen (`bug15`, S3).
- Heading-Reihenfolge im Privacy-Modal korrigieren (`bug16`, S4).
- `appendFailure`-HTML-Muster entschaerfen oder dokumentieren (`bug17`, S4).

**Stand 2026-06-27**
- `bug13` (S2) gefixt: Force-Download in `lib.php` plus gehaertetes
  `tutor_io::is_safe_image()`.
- `bug14` (S3) gefixt: `customcss` wird serverseitig gegen `</style>`-
  Breakout, `@import`, `expression()` und `javascript:` gehaertet.
- `bug15` (S3) gefixt: History-Open-Button hat sichtbaren `:focus-visible`.
- `bug16` (S4) gefixt: Privacy-Modal nutzt geordnete Abschnittsueberschriften.
- `bug17` (S4) gefixt: Fehlertexte laufen ueber escapeten Template-Slot statt
  ueber den `{{{html}}}`-Slot.

### task07 Moodle CodeChecker (moodle-cs) gruen bekommen
Status:    done
Feature:   -
Prioritaet: P1
Linked:    bug02, bug18, test05

**Ziel**
Das Plugin gegen den offiziellen `moodlehq/moodle-cs`-Standard sauber bekommen
(Vorbereitung Plugins-Directory-Submission).

**Ausgangslage**
920 Errors + 660 Warnings ueber 50 Dateien (rein Coding-Style, keine
Security-/Korrektheitsbefunde).

**Umgesetzt 2026-06-27**
- PHPCBF-Autofix: 892 Layout-Verstoesse in 47 Dateien automatisch behoben.
- `manage_tutors.php`: phpcbf-Oszillation manuell aufgeloest (Header-Reihenfolge
  `boilerplate -> Docblock -> declare`, isolierter `FunctionCallSignature`-Lauf,
  Rest-Konstrukte von Hand kanonisiert).
- Entry-Dateien `view.php`, `report.php`, `instance_tutor.php`,
  `edit_instance.php`: gleiche `declare`-Reihenfolge korrigiert.
- Docblocks fuer `plugin_page`-Konstanten und `widget::render()` ergaenzt;
  `provider`-Implements-Liste umgebrochen; lange Privacy-Zeile entschaerft;
  `lib.php`-Callback-Variable umbenannt (`$birecord_or_cm` -> `$birecordorcm`).
- `form/element_eatcolour.php`: QuickForm-API-Overrides (camelCase-Methoden,
  `$_helpbutton`) mit gezielten `phpcs:ignore`-Annotationen versehen.
- Inline-Kommentare bereinigt (Grossschreibung, Separatoren, `.eat-*`-Marker).
- Schritt 3: `MOODLE_INTERNAL`-Guard aus den 6 markierten Dateien entfernt
  (siehe `bug02`-Reconciliation).
- Schritt 2: Lang-Strings (EN + DE, je 658) alphabetisch sortiert und
  eingestreute Abschnitts-Kommentare entfernt.

**Ergebnis**
`Codechecker: 0 errors, 0 warnings` (siehe `test05`). Beifang: `bug18`
(`MODIFIER_COMPACT == 'full'`) zur Klaerung erfasst.

**Hinweis**
Die `amd/build/*.min.js` wurden nicht neu gebaut; CodeChecker betrifft nur PHP.

### task08 Behat-Abdeckung fuer Plugin-Shell und Kurskontext erweitern
Status:    done
Feature:   feat01 / feat03
Prioritaet: P1
Linked:    test06

**Ziel**
Die nach dem UX-Umbau zentralen Admin-Flows nicht nur per PHPUnit, sondern auch
als Browser-Journeys absichern.

**Umgesetzt 2026-06-28**
- `plugin_shell.feature`: Dashboard, Tutorenverwaltung und Vorschau werden in
  der Plugin-Shell geprueft; die Moodle-Blockleiste darf auf diesen Shell-Seiten
  nicht erscheinen.
- Dashboard-Test fuer fehlende Zusatzplugins: ein einziger Hinweis oberhalb des
  Wizards plus `Plugin missing`-Status.
- Tutorenverwaltung: Action-Icons fuer `Create tutor` und `Import`.
- `course_context.feature`: Kursbezogene Standalone-Seite bleibt ohne Tutor-
  Block gesperrt und oeffnet erst, wenn der Block im Kurs vorhanden ist.

**Ausstehend**
- In CI mit voll initialisiertem Behat-Profil ausfuehren:
  `vendor/bin/behat --tags @block_eledia_aitutor`.

### task09 Hero-/AI-Home-Erlebnis in der Moodle App (Release 3)
Status:    geplant
Feature:   feat (Dashboard-Hero, AI-Home)
Prioritaet: P2
Linked:    -

**Ziel**
Die App oeffnet den Tutor derzeit als klassisches eingebettetes Chat-Widget
(`db/mobile.php` -> `classes/output/mobile.php` -> `<core-iframe>` auf
`view.php?embedded=1`). Der Release-2-Hero-Modus ("Heute schon gemoodlet?",
rollenbasierte Pills, Tagesbriefing) erscheint in der App nicht, weil er in
`home.php` / dem Dashboard-Block sitzt. In Release 3 soll die App dieselbe
AI-Home-UX zeigen.

**Umsetzungsplan**
- `view.php` einen optionalen Schalter geben (`hero=1` PARAM_BOOL), der die
  Hero-Variante rendert: intern `widget::render($ctx, 0, ['dashboard' => 1])`
  statt des klassischen embedded Widgets. Nur fuer den globalen Kontext
  (courseid 0) sinnvoll; im Kurskontext beim klassischen Chat bleiben.
- `classes/output/mobile.php::iframe_response()` fuer den globalen Handler
  `hero=1` an die URL haengen. Kurs-Handler unveraendert lassen.
  Alternative pruefen: direkt `home.php?embedded=1` einbetten statt
  `view.php?hero=1` — `home.php` muss dann den `embedded`-Pagelayout
  unterstuetzen (aktuell fest `standard`). `view.php`-Weg ist kleiner und
  haelt eine Einstiegs-Datei; bevorzugen.
- CSS: die Hero-Sticky-Composer-Leiste im chrome-losen `pagelayout-embedded`
  pruefen — `position: sticky; bottom` gegen `100vh`-Iframe testen, ggf.
  Safe-Area-Insets (`env(safe-area-inset-bottom)`) fuer Notch-Geraete.
- Rollen-Pills: `user_audience::resolve()` funktioniert in der App identisch
  (serverseitig), MUC-Cache greift. Kein Extra-Aufwand erwartet.
- `tests/mobile_test.php` um einen Fall fuer den Hero-Handler erweitern
  (`hero=1` in der iframe-URL, nur global).
- Manuell in der Moodle App gegen demo.eledia.ai testen: Auto-Login im
  core-iframe, Hero-Darstellung, Pill-Klick loest MCP-Aktion aus,
  Briefing-Button.

**Offene Entscheidung**
- Soll der App-Haupteintrag komplett auf Hero umstellen, oder bleibt der reine
  Chat als Standard und Hero ist ein Site-Schalter? Empfehlung: Site-Schalter
  (z.B. `mobileherohome`), damit Institutionen ohne AI-Home-Startseite die
  schlanke Variante behalten.

### task10 Sitewide-Tutor-Launcher als FAB (unten rechts) statt inline oben
Linked: hook_callbacks::inject_sitewide_tutor
Status: open

**Kontext**
Der sitewide-Tutor (`inject_sitewide_tutor`) wird per
`before_standard_top_of_body_html`-Hook oben in `#page-wrapper` injiziert. Seit
dem Fix, der `embedded` bei der globalen Injektion auf `docked` zwingt (v0.18.5),
rendert das grosse Inline-Panel nicht mehr ueber der Kursnavigation — richtig so.
Aber der `docked`-Launcher rendert weiterhin als **Inline-Element ganz oben**
(bei `top:0`, ueber der Breadcrumb) und hinterlaesst dadurch einen schmalen
Leerstreifen zwischen Moodle-Navbar und Breadcrumb. Verifiziert auf dev.eledia.ai
(2026-07-05): `data-mode=docked`, Launch-Button vorhanden, aber Launcher-Root bei
`top:0` ueber der Nav.

**Ziel**
Der sitewide-injizierte Launcher soll als **FAB** (schwebender runder Knopf unten
rechts) rendern statt als Inline-Element im Seitenfluss. Dann verschwindet der
Leerstreifen oben, die Breadcrumb sitzt direkt unter der Navbar, und der Tutor
bleibt ueber den Ecken-Button erreichbar.

**Umsetzungsplan**
- Der FAB-Stil existiert bereits: CSS `.eledia_aitutor-launch--fab` (fixed unten
  rechts, `--eat-fab-bottom/right`), und der Template-Zweig `{{#launchfab}}` in
  `templates/launcher.mustache`. `launchfab` kommt aus
  `brand['launcherstyle'] === 'fab'` (siehe `widget::render`).
- In `inject_sitewide_tutor` beim `widget::render(...)`-Aufruf den Launcher-Stil
  auf `fab` erzwingen (analog zur `displaymode`-Coercion `embedded`->`docked`) —
  z.B. Option `launcherstyle => 'fab'` mitgeben, ODER pruefen, ob `launcherstyle`
  ueberhaupt schon per Options-Array durchgereicht wird; ggf. in `widget::render`
  einen Options-Override fuer `launcherstyle` ergaenzen.
- Nur fuer die **sitewide-Injektion** erzwingen; der platzierte Block-Launcher
  bleibt konfigurierbar (embedded/pill/fab je nach Instanz/Branding).
- Verifizieren auf einer Kurs-Unterseite (z.B. `mod/elli/view.php?id=…`): Tutor
  erscheint als runder FAB unten rechts, kein Leerstreifen oben, Breadcrump
  direkt unter der Navbar; Klick oeffnet das Docked-Panel; Icon-System intakt.
- Danach `vendor-tutor-suite.sh` -> Monorepo -> deploy (dev.eledia.ai).

**Offene Entscheidung**
- FAB fix erzwingen fuer sitewide, oder als Setting (`sitewidelauncherstyle`)?
  Empfehlung: fix `fab` — der sitewide-Launcher hat keinen sinnvollen Inline-Platz
  auf fremden Unterseiten. Kosmetisch, kein Bug; niedrige Prioritaet.
