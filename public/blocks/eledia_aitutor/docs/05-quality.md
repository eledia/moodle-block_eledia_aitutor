# Qualitaet

Dieses Dokument sammelt Bugs, Testfaelle und Verifikationsergebnisse.

---

## Bugs

### bug01 Lokale RAG-URL kann aus Docker heraus falsch aufloesen

Feature:  feat01 / feat02
Severity: S3
Status:   mitigated
Linked:   task02, test01

**Beschreibung**
Eine RAG-URL wie `http://localhost:8080/...` funktioniert im Browser, kann aber
aus dem Moodle-Container heraus auf den Container selbst zeigen. Wenn dort auf
Port 8080 kein Dienst laeuft, schlaegt der serverseitige RAG-Aufruf fehl.

**Reproduktion**
1. Moodle laeuft im Docker-Container.
2. Tutor-Block RAG-URL auf `http://localhost:8080/...` setzen.
3. Chat-Nachricht senden.

**Erwartet**
Moodle erreicht den RAG/Tutor-MCP-Endpunkt serverseitig.

**Tatsaechlich**
Je nach Docker-Netzwerk ist der Endpunkt nicht erreichbar oder Moodle leitet
wegen `wwwroot`-Mismatch um.

**Stand 2026-06-26**
Fuer die lokale Entwicklung wurden Docker-Loopback/Host-Header-Probleme in den
lokalen Integrationen adressiert. Der generelle Hinweis bleibt relevant fuer
neue Setups: RAG-/Ingest-URLs muessen serverseitig aus dem Moodle-Container
erreichbar sein.

### bug02 Fehlender `MOODLE_INTERNAL`-Guard in `block_eledia_aitutor.php`

Feature:  feat01
Severity: S1
Status:   fixed
Linked:   task04, task07

**Beschreibung**
Die Block-Hauptdatei war als nicht-autoloaded Moodle-Datei nicht gegen direkten
Aufruf geschuetzt.

**Fix**
`defined('MOODLE_INTERNAL') || die();` wurde direkt nach dem GPL-Header
ergaenzt.

**Reconciliation 2026-06-27 (moodle-cs)**
Der offizielle Moodle-CodeChecker (`moodle.Files.MoodleInternal`) meldet den
Guard in seiteneffektfreien bzw. autoloaded Dateien als ueberfluessig
(`MoodleInternalNotNeeded`). In `task07` wurde er daher wieder entfernt aus
`block_eledia_aitutor.php`, `lib.php`, `edit_form.php`,
`classes/hook_callbacks.php`, `classes/output/plugin_page.php` und
`plugin_shell.php`. Das ist sicherheitsneutral: Diese Dateien deklarieren nur
Klassen/Funktionen, ein Direktaufruf fuehrt keinen Code aus. Dateien mit echten
Seiteneffekten (z. B. `settings.php`, Lang-Dateien) behalten den Guard.

### bug03 Unsicheres `unserialize()` in `db/upgrade.php`

Feature:  feat03
Severity: S2
Status:   fixed
Linked:   task04

**Beschreibung**
Ein Upgrade-Step deserialisierte `block_instances.configdata` mit nacktem
`unserialize()`.

**Fix**
Die Stelle nutzt nun `unserialize_object()`.

### bug04 LTM-Sync konnte vor Privacy-Consent extern kommunizieren

Feature:  feat02
Severity: S2
Status:   fixed
Linked:   task04

**Beschreibung**
`set_ltm` konnte ueber `ltm::sync_to_rag()` einen Moodle-MCP-Token an den
RAG-Server senden, bevor der Nutzer den Datenschutzhinweis bestaetigt hatte.

**Fix**
`ltm::sync_to_rag()` prueft jetzt zentral `consent::has_consented()` und bricht
ohne Consent frueh ab.

### bug05 Citation-URLs nur indirekt ueber External-Return-Typ abgesichert

Feature:  feat02
Severity: S3
Status:   fixed
Linked:   task04

**Beschreibung**
`message.mustache` rendert Quellen als `href="{{url}}"`. Die URL war bereits
ueber `PARAM_URL` in External-Returns abgesichert, aber nicht vor der
Template-Uebergabe normalisiert.

**Fix**
URLs werden nun in `rag_client`, `send_message` und `get_history` zusaetzlich
mit `clean_param(..., PARAM_URL)` normalisiert. Das Template dokumentiert diese
Vorbedingung.

### bug06 Inline-JS/CSS im Admin-Navbar-Hook

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task04

**Beschreibung**
Der Output-Hook injizierte Inline-`style` und Inline-`script` fuer den
Admin-Launcher. Das ist mit strikter CSP nicht kompatibel.

**Fix**
CSS wurde nach `styles.css` verschoben. Das Verhalten liegt in
`amd/src/admin_launcher.js` und wird ueber `js_call_amd()` geladen.

### bug07 Veraltete Moodle Context-Alias-Klassen in Produktiv-PHP

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task04

**Beschreibung**
Produktiv-PHP nutzte noch `context_system`, `context_course` und
`context_block`.

**Fix**
Produktiv-PHP nutzt jetzt `\core\context\system`, `\core\context\course` und
`\core\context\block`. Tests koennen bei Bedarf separat stilistisch
modernisiert werden.

### bug08 Direkte DB-Abfrage im Rendering von `manage_tutors.php`

Feature:  feat03
Severity: S4
Status:   fixed
Linked:   task04

**Beschreibung**
Die Blockinstanzen wurden im Rendering-Abschnitt direkt ueber `$DB` geladen.

**Fix**
Die Abfrage liegt nun in `tutor_apply::instance_records()`.

### bug09 Help-Link der Plugin-Shell setzt `local_lernhive` voraus

Feature:  feat03
Severity: S1
Status:   fixed
Linked:   task05

**Beschreibung**
`classes/output/plugin_shell.php::action_slots()` (Zeile 56) erzeugt den
Help-Link hardkodiert auf `/local/lernhive/support.php`. `helpurl` wird immer
gesetzt, sodass das Fragezeichen im Shell-Header (`plugin_shell_header.mustache`)
stets gerendert wird -- ueberall, wo `shell::header()` greift
(`configuration.php`, `settings.php`, `manage_tutors.php`, `report.php`,
`edit_instance.php`, `instance_tutor.php` und die Preview in `view.php`). Ohne
installiertes `local_lernhive` fuehrt der Link auf eine 404-Seite.

**Einordnung als Abhaengigkeit (2026-06-27)**
Dies ist die **einzige echte Laufzeit-Abhaengigkeit** auf `local_lernhive`:
- `version.php` enthaelt **kein** `$plugin->dependencies` -- es gibt keine
  Installations-Abhaengigkeit; das Plugin installiert und laeuft ohne LernHive.
- Alle anderen Fremd-Plugin-Integrationen sind sauber per
  `class_exists()` bzw. `core_component::get_plugin_directory()` abgesichert:
  `local_literag`, `local_ragingest` (`chat_mode.php`, `configuration.php`),
  `webservice_elediamcp` (`token_provider.php:57`), Premium (`premium.php`).
- Die `body:not(.theme-lernhive)`-Regeln in `styles.css` sind **keine**
  Abhaengigkeit, sondern Standalone-Fallback-Styling, das genau dann greift,
  wenn das LernHive-Theme **fehlt**. Die `.lh-*`-Slots werden vom Plugin selbst
  gestylt (Vollstaendigkeit der Fallbacks = `bug10`).
- `settings_shell.js`/`settings.php`-Erwaehnungen von „LernHive" sind nur
  Kommentare zur Design-Herkunft.

Der Help-Link ist damit die einzige Stelle, die nicht dem ansonsten
durchgaengigen Guard-Muster folgt.

**Erwartet**
Der Help-Link zeigt auf eine plugin-eigene Hilfeseite. LernHive darf dieselbe
Dokumentation optional in seinem Support-Hub darstellen, darf aber keine
Runtime-Voraussetzung fuer den Tutor sein.

**Fix 2026-06-27/28**
`plugin_shell::action_slots()` setzt `helpurl` immer auf
`/blocks/eledia_aitutor/help.php`. Die neue `help.php` rendert die vorhandene
Plugin-Dokumentation aus `docs/02-user-doc*.md` in der Plugin-Shell. Damit ist
die Hilfe auch ohne `local_lernhive` erreichbar; das LernHive-Support-Hub bleibt
rein optional.

### bug10 Settings-Shell-Fallback ist zu fragil

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task05

**Beschreibung**
`amd/src/settings_shell.js` baut die Shell-Struktur auch dann auf, wenn
`config.headerHtml` fehlt. Zudem fehlen Fallback-Regeln fuer einige in
`plugin_shell_header.mustache` angebotene `lh-*`-Slots.

**Erwartet**
Ohne Header-Kontext bleibt das Moodle-Formular nutzbar. Alle genutzten oder im
Template angebotenen Shell-Slots besitzen eine sinnvolle Fallback-Darstellung.

**Fix 2026-06-27**
`settings_shell.js` beendet die Shell-Initialisierung kontrolliert, wenn
`config.headerHtml` fehlt, und entfernt dabei den Pending-Zustand. Zusaetzlich
wurden Fallback-Regeln fuer `lh-plugin-infobar`, `lh-plugin-tag`,
`lh-btn-*` und `lh-row-actions` ergaenzt.

### bug11 Fokus- und A11y-Details in Chat- und Settings-UI

Feature:  feat01 / feat03 / feat04
Severity: S3
Status:   fixed
Linked:   task05

**Beschreibung**
Der UX-Review nennt mehrere kleine Accessibility-Punkte: `:focus`-Fallback fuer
den Send-Button, sichtbare `:focus-visible`-Ringe fuer Settings-Karten und
Backnav, `aria-label` fuer History-Liste/Composer sowie `role="note"` statt
`role="alert"` fuer statische Privacy-Hinweise.

**Erwartet**
Tastaturfokus ist sichtbar und statische Inhalte werden von Screenreadern nicht
als dynamische Alerts angesagt.

**Fix 2026-06-27**
Fokus-Fallbacks fuer Send-Button, History-Open-Button, Settings-Hub-Karten und
Backnav ergaenzt. History-Liste und Composer besitzen nun Labels. Der statische
Accuracy-Hinweis im Privacy-Modal nutzt `role="note"`, und die
Abschnittsueberschriften sind als `h3` strukturiert.

### bug12 Settings-Override-Checkboxen waren ueberstylt

Feature:  feat03
Severity: S4
Status:   fixed
Linked:   task05

**Beschreibung**
Die `Ueberschreiben erlauben`-Checkboxen in der Admin-Settings-Shell waren mit
Spezial-Pills, fetter Beschriftung und eigener Checkbox-Optik versehen. Das
wirkte nicht Moodle-nativ.

**Fix**
Die Darstellung wurde auf eine schlichte Moodle-nahe Form reduziert:
normale Checkbox, normales Label und `Standard: Ja/Nein` als einfacher Text.

### bug13 Stored XSS ueber ungepruefte SVG-Uploads (Logo/Avatar)

Feature:  feat03
Severity: S2
Status:   fixed
Linked:   task06, test04

**Beschreibung**
Tutor-Logo und -Avatar akzeptieren `.svg` als Upload-Typ
(`classes/form/tutor_edit_form.php:76`, `edit_instance.php:157`). Beim Import
prueft `tutor_io::is_safe_image()` (`classes/local/tutor_io.php:180`) nur, ob die
ersten 512 Bytes die Zeichenkette `<svg` enthalten, ohne Sanitizing. Die Datei
wird spaeter ueber `block_eledia_aitutor_pluginfile()` (`lib.php:113`) mit
`send_stored_file(..., $forcedownload, ...)` inline ausgeliefert und als
`<img src>` eingebettet. Eine praeparierte SVG mit `<script>`/Event-Handlern
fuehrt damit Code im Browser jedes betrachtenden Nutzers aus.

**Trust-Boundary**
Upload erfordert `block/eledia_aitutor:manage` bzw. `moodle/site:config`. Es ist
also ein Teacher-zu-Student/Admin-XSS, kein anonymes -- deshalb S2 statt S1.

**Erwartet**
SVGs werden vor dem Speichern saniert (Scripts/`on*=`/externe Referenzen
entfernt) oder `.svg` wird aus `accepted_types` entfernt; alternativ werden
diese Dateibereiche mit `forcedownload => true` ausgeliefert.

**Fix 2026-06-27 (Defense in Depth)**
1. Auslieferungsschicht: `block_eledia_aitutor_pluginfile()` (`lib.php`) liefert
   alle Branding-Dateibereiche jetzt unbedingt mit `forcedownload = true` aus.
   Ein als Top-Level-Dokument abgerufenes SVG fuehrt dadurch keinen Code mehr im
   Moodle-Origin aus; die `<img>`-Einbettung bleibt unveraendert. Das schuetzt
   auch bereits gespeicherte Dateien.
2. Speicherpfad (Import): `tutor_io::is_safe_image()` liest jetzt den gesamten
   SVG-Inhalt und lehnt Dateien mit `<script`, `<foreignObject`,
   `javascript:`, `<!ENTITY` oder Inline-Event-Handlern (`on...=`) ab, statt nur
   die ersten 512 Bytes auf `<svg` zu pruefen.

`php -l` fuer `lib.php` und `classes/local/tutor_io.php` gruen.

### bug14 Unescaptes `customcss` erlaubt `</style>`-Breakout

Feature:  feat03
Severity: S3
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`templates/launcher.mustache:43` rendert `<style>{{{customcss}}}</style>` roh.
`security::custom_css()` (`classes/local/security.php:236`) liefert den Wert nur
`trim()`-bereinigt zurueck. Ein Schreibzugriff auf diese Site-Einstellung
erlaubt `</style><script>...</script>` und damit Stored XSS fuer alle Nutzer,
die den Block laden; zudem wird eine strikte CSP unterlaufen.

**Trust-Boundary**
Admin-gated, aber eine CSS-Einstellung ist keine erwartete Script-Flaeche.

**Erwartet**
`custom_css()` entfernt `<`/`>` bzw. lehnt `</style`/`<script` ab oder rendert
ueber einen CSS-Sanitizer.

**Fix 2026-06-27**
`security::custom_css()` entfernt NULs und Angle-Brackets, bevor der Wert in
`<style>` gerendert wird. Zusaetzlich werden `@import`, `expression()` und
`javascript:` entfernt.

### bug15 Fehlender Fokusring am History-Open-Button

Feature:  feat01
Severity: S3
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`.eledia_aitutor-history-open` (`templates/conversation_list.mustache:38`) ist der
primaere Button zum OEffnen einer gespeicherten Konversation, hat in `styles.css`
aber weder `:focus` noch `:focus-visible`. Tastaturnutzer erhalten beim Tabben
durch die History keine sichtbare Fokusmarkierung, waehrend alle anderen
Panel-Controls einen Fokusring besitzen.

**Erwartet**
`.eledia_aitutor-history-open:focus-visible` erhaelt einen sichtbaren Ring analog
zu den uebrigen interaktiven Controls.

**Fix 2026-06-27**
`:focus-visible` fuer `.eledia_aitutor-history-open` ergaenzt.

### bug16 Heading-Reihenfolge im Privacy-Modal springt auf `h5`

Feature:  feat04
Severity: S4
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`templates/privacy_info.mustache` nutzt durchgaengig `<h5>` fuer die
Abschnittsueberschriften, ohne vorausgehende `h1`-`h4` im Dialog. Die
Screenreader-Heading-Navigation landet damit auf `h5` ohne Outline-Kontext.

**Erwartet**
Abschnittsueberschriften beginnen passend unterhalb des Modal-Titels (z. B.
`h2`/`h3`).

**Fix 2026-06-27**
Privacy-Abschnittsueberschriften nutzen nun `h3.eledia_aitutor-privacy-heading`.

### bug17 `appendFailure` baut HTML fuer Triple-Mustache-Slot

Feature:  feat01
Severity: S4
Status:   fixed
Linked:   task06, test04

**Beschreibung**
`amd/src/chat.js:565` setzt `html: '<p>' + this.escape(message) + '</p>'`, das
ueber `{{{html}}}` (`templates/message.mustache:70`) unescaped gerendert wird.
Aktuell sicher durch `this.escape()`, aber ein fragiles Muster: Faellt der
`escape()`-Aufruf bei einer spaeteren AEnderung weg, entsteht still DOM-XSS des
AJAX-Fehlertexts.

**Erwartet**
Fehlertext ueber einen escapeten Slot rendern oder die Vorbedingung am Code
dokumentieren.

**Fix 2026-06-27**
`appendFailure()` uebergibt `failuretext`; `message.mustache` rendert diesen
Wert mit normalem Mustache-Escaping und nutzt `{{{html}}}` nur noch fuer
serverseitig sanitisierte Assistant-Antworten.

---

## Tests

### test01 Lokaler Tutor-Smoke

Feature: feat01 / feat02
Status:  partial

**Schritte**
1. Moodle unter `http://localhost:8080` oeffnen.
2. Mit Testnutzer anmelden.
3. Tutor-Block oder `/blocks/eledia_aitutor/view.php` oeffnen.
4. Consent bestaetigen.
5. "Hallo Tutor" senden.

**Erwartet**
Der Tutor liefert eine Chat-Antwort. Im Fehlerfall wird die konkrete
Konfigurations- oder Transportursache dokumentiert.

**Stand 2026-06-26**
Der lokale Kurskontext/RAG-Fluss wurde im Entwicklungssetup zwischenzeitlich
erfolgreich hergestellt. Fuer eine reproduzierbare Abschlussfreigabe bleibt ein
sauberer Browser-Smoke mit dokumentiertem Nutzer/Block/Kurs offen.

### test02 Review-Fix-Verifikation

Feature: feat01 / feat02 / feat03
Status:  passed-with-environment-note
Linked:  task04

**Geprueft**
- `php -l` ueber alle Plugin-PHP-Dateien lokal: bestanden.
- `php -l` ueber alle Plugin-PHP-Dateien im Moodle-Container: bestanden.
- `node --check public/blocks/eledia_aitutor/amd/src/admin_launcher.js`: bestanden.
- Moodle-Container mit aktuellem Block-Plugin synchronisiert.
- Moodle-Caches im Container gepurged.

**Nicht ausgefuehrt**
PHPUnit ist in der lokalen Containerumgebung nicht initialisiert. Der direkte
Aufruf scheitert an fehlendem Moodle-Testbootstrap bzw. `@root@` in
`phpunit.xml.dist`.

**Naechster Schritt bei Bedarf**
Moodle PHPUnit-Umgebung initialisieren und mindestens `ltm_test` sowie
`rag_client_test` ausfuehren.

**Re-Verifikation 2026-06-26 (Folge-Review)**
Die Fixes zu `bug02`-`bug08` wurden am Code erneut bestaetigt:
`MOODLE_INTERNAL`-Guard, `unserialize_object()`, Consent-Gate in
`ltm::sync_to_rag()`, `PARAM_URL`-Normalisierung in `rag_client`/`send_message`/
`get_history`, `js_call_amd()` statt Inline-JS/CSS, keine Legacy-Context-Aliasse
in Produktiv-PHP und `tutor_apply::instance_records()` sind unveraendert
vorhanden.

### test03 UX/UI-Review-Verifikation

Feature: feat01 / feat03 / feat04
Status:  passed-with-environment-note
Linked:  task05

**Geprueft**
- Settings-Override-Checkboxen visuell vereinfacht.
- Geaendertes `styles.css` in den lokalen Moodle-Container kopiert.
- Moodle-Caches im Container gepurged.
- Statische Syntaxchecks fuer die geaenderten PHP- und AMD-Quellen bestanden.

**Nicht ausgefuehrt**
Vollstaendiger Screenreader-Smoke wurde nicht automatisiert ausgefuehrt.

### test04 Folge-Review-Findings verifizieren

Feature: feat01 / feat03 / feat04
Status:  partial
Linked:  task06

**Ziel**
Die im Folge-Review vom 2026-06-26 gefundenen `bug13` bis `bug17` nach der
Umsetzung verifizieren.

**Schritte**
1. Praeparierte SVG als Logo/Avatar hochladen und pruefen, dass kein Script
   ausgefuehrt bzw. die Datei nur als Download ausgeliefert wird (`bug13`).
2. `customcss` mit `</style><script>` setzen und pruefen, dass kein Script
   ausgefuehrt wird (`bug14`).
3. Mit Tastatur durch die History tabben: sichtbarer Fokusring am
   Open-Button (`bug15`).
4. Screenreader-Outline des Privacy-Modals pruefen (`bug16`).
5. Sichtkontrolle `appendFailure`-Pfad nach Umbau (`bug17`).

**Erwartet**
Keine Script-Ausfuehrung ueber SVG oder `customcss`; sichtbarer Tastaturfokus;
konsistente Heading-Outline.

**Stand 2026-06-27**
Code-Fixes fuer `bug13` bis `bug17` umgesetzt. Statische Syntaxchecks bestanden.
Ein manueller Browser-Exploit-Smoke fuer SVG/`customcss` bleibt fuer die
Abschlussfreigabe empfehlenswert.

### test05 Moodle CodeChecker (moodle-cs)

Feature: -
Status:  passed
Linked:  task07

**Geprueft**
Offizieller Moodle CodeChecker (`phpcs --standard=moodle`,
`moodlehq/moodle-cs`) ueber das gesamte Plugin im lokalen Container.

**Ergebnis 2026-06-27**
`Codechecker: 0 errors, 0 warnings`. Ausgangslage war 920 Errors + 660 Warnings;
behoben via PHPCBF-Autofix (892), manuelle Aufloesung der nicht-konvergierenden
Faelle (u. a. `manage_tutors.php`), Lang-String-Sortierung (2x 658 Strings) und
`MOODLE_INTERNAL`-Bereinigung. Siehe `task07`.

### bug18 `MODIFIER_COMPACT` hat denselben Wert wie `MODIFIER_FULL`

Feature:  feat03
Severity: S4
Status:   open
Linked:   task07

**Beschreibung**
`classes/output/plugin_page.php` definiert
`public const MODIFIER_COMPACT = 'full';` -- identisch zu `MODIFIER_FULL`. Das
sieht nach einem Copy-Paste-Fehler aus (erwartet vermutlich `'compact'`). Beim
Codechecker-Durchgang aufgefallen, aber bewusst NICHT geaendert, da es eine
Verhaltensaenderung waere.

**Erwartet**
Klaeren, ob `MODIFIER_COMPACT` einen eigenen Wert (`'compact'`) braucht oder die
Konstante entfernt werden kann. Pruefen, wo die Konstante verwendet wird.
