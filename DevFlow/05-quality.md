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

### bug02 Fehlender `MOODLE_INTERNAL`-Guard in `block_elediaaitutor.php`

Feature:  feat01
Severity: S1
Status:   fixed
Linked:   task04

**Beschreibung**
Die Block-Hauptdatei war als nicht-autoloaded Moodle-Datei nicht gegen direkten
Aufruf geschuetzt.

**Fix**
`defined('MOODLE_INTERNAL') || die();` wurde direkt nach dem GPL-Header
ergaenzt.

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
Status:   open
Linked:   task05

**Beschreibung**
`classes/output/plugin_shell.php::action_slots()` erzeugt den Help-Link
hardkodiert auf `/local/lernhive/support.php`. Ohne installiertes
`local_lernhive` fuehrt das Fragezeichen im Shell-Header auf eine tote Seite.

**Erwartet**
Der Help-Link wird nur gerendert, wenn `local_lernhive` vorhanden ist, oder
zeigt auf einen blockeigenen Fallback.

### bug10 Settings-Shell-Fallback ist zu fragil

Feature:  feat03
Severity: S3
Status:   open
Linked:   task05

**Beschreibung**
`amd/src/settings_shell.js` baut die Shell-Struktur auch dann auf, wenn
`config.headerHtml` fehlt. Zudem fehlen Fallback-Regeln fuer einige in
`plugin_shell_header.mustache` angebotene `lh-*`-Slots.

**Erwartet**
Ohne Header-Kontext bleibt das Moodle-Formular nutzbar. Alle genutzten oder im
Template angebotenen Shell-Slots besitzen eine sinnvolle Fallback-Darstellung.

### bug11 Fokus- und A11y-Details in Chat- und Settings-UI

Feature:  feat01 / feat03 / feat04
Severity: S3
Status:   open
Linked:   task05

**Beschreibung**
Der UX-Review nennt mehrere kleine Accessibility-Punkte: `:focus`-Fallback fuer
den Send-Button, sichtbare `:focus-visible`-Ringe fuer Settings-Karten und
Backnav, `aria-label` fuer History-Liste/Composer sowie `role="note"` statt
`role="alert"` fuer statische Privacy-Hinweise.

**Erwartet**
Tastaturfokus ist sichtbar und statische Inhalte werden von Screenreadern nicht
als dynamische Alerts angesagt.

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

---

## Tests

### test01 Lokaler Tutor-Smoke

Feature: feat01 / feat02
Status:  partial

**Schritte**
1. Moodle unter `http://localhost:8080` oeffnen.
2. Mit Testnutzer anmelden.
3. Tutor-Block oder `/blocks/elediaaitutor/view.php` oeffnen.
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
- `node --check public/blocks/elediaaitutor/amd/src/admin_launcher.js`: bestanden.
- Moodle-Container mit aktuellem Block-Plugin synchronisiert.
- Moodle-Caches im Container gepurged.

**Nicht ausgefuehrt**
PHPUnit ist in der lokalen Containerumgebung nicht initialisiert. Der direkte
Aufruf scheitert an fehlendem Moodle-Testbootstrap bzw. `@root@` in
`phpunit.xml.dist`.

**Naechster Schritt bei Bedarf**
Moodle PHPUnit-Umgebung initialisieren und mindestens `ltm_test` sowie
`rag_client_test` ausfuehren.

### test03 UX/UI-Review-Verifikation

Feature: feat01 / feat03 / feat04
Status:  partial
Linked:  task05

**Geprueft**
- Settings-Override-Checkboxen visuell vereinfacht.
- Geaendertes `styles.css` in den lokalen Moodle-Container kopiert.
- Moodle-Caches im Container gepurged.

**Offen**
- Browser-Sichtung der kompletten Settings-Seite nach Abschluss von `bug09` bis
  `bug11`.
- Tastatur-/Screenreader-Smoke fuer Chat, History und Settings-Shell.
