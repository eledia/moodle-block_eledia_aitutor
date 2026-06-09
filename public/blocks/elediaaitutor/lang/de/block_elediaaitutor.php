<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'eLeDia.ai Tutor';

// Capabilities.
$string['elediaaitutor:addinstance'] = 'Neuen eLeDia.ai Tutor-Block hinzufügen';
$string['elediaaitutor:myaddinstance'] = 'Neuen eLeDia.ai Tutor-Block zum Dashboard hinzufügen';
$string['elediaaitutor:use'] = 'eLeDia.ai Tutor-Chat verwenden';
$string['elediaaitutor:manage'] = 'eLeDia.ai Tutor-Konfiguration verwalten';
$string['elediaaitutor:viewhistory'] = 'eLeDia.ai Tutor-Gesprächsverlauf ansehen';
$string['elediaaitutor:deleteownhistory'] = 'Eigene eLeDia.ai Tutor-Gespräche löschen';

// UI strings.
$string['launch'] = 'eLeDia.ai Tutor öffnen';
$string['close'] = 'Schließen';
$string['online'] = 'Online';
$string['senderyou'] = 'Sie';
$string['poweredby'] = 'Bereitgestellt von eLeDia.ai';
$string['send'] = 'Nachricht senden';
$string['messagelabel'] = 'Ihre Nachricht an den Tutor';
$string['inputplaceholder'] = 'Fragen Sie den Tutor etwas …';
$string['conversation'] = 'Gespräch';
$string['sources'] = 'Quellen';
$string['source'] = 'Quelle';
$string['history'] = 'Gesprächsverlauf';
$string['nohistory'] = 'Sie haben noch keine gespeicherten Gespräche.';
$string['clearconversation'] = 'Gespräch leeren';
$string['newconversation'] = 'Neues Gespräch';
$string['newstarted'] = 'Neues Gespräch gestartet.';
$string['copy'] = 'Antwort kopieren';
$string['copied'] = 'Antwort in die Zwischenablage kopiert.';
$string['retry'] = 'Erneut versuchen';
$string['thinking'] = 'Der Tutor denkt nach …';
$string['failed'] = 'Entschuldigung, der Tutor konnte nicht antworten. Bitte versuchen Sie es erneut.';
$string['cleared'] = 'Gespräch geleert.';
$string['toolong'] = 'Ihre Nachricht ist zu lang.';
$string['nohistorytool'] = 'Das Laden früherer Nachrichten ist für diesen Tutor nicht verfügbar.';
$string['configerror'] = 'Konfigurationsproblem des eLeDia.ai Tutors';
$string['unavailable_user'] = 'Der Tutor ist derzeit nicht verfügbar. Bitte versuchen Sie es später erneut.';
$string['default_welcome'] = 'Hallo! Ich bin Ihr eLeDia.ai Tutor. Fragen Sie mich zu Ihren Kursen, Aufgaben oder allem, was Sie gerade lernen.';
$string['default_persona'] = 'eLeDia.ai Tutor';
$string['tokenlabel'] = 'eLeDia.ai Tutor-Connector';

// Instance configuration.
$string['config_title'] = 'Blocktitel';
$string['config_displaymode'] = 'Anzeigemodus';
$string['config_displaymode_help'] = 'Wie der Chat dargestellt wird: eingebettet im Block, als angedocktes Panel, das über der Seite schwebt, als zentrierter modaler Dialog oder als Vollbild-Erlebnis.';
$string['config_passcoursecontext'] = 'Kurskontext übergeben';
$string['config_passcoursecontext_help'] = 'Wenn aktiviert und der Block sich auf einer Kursseite befindet, wird die aktuelle Kurs-ID an den Tutor übergeben, damit er kursspezifische Fragen beantworten kann. Der Tutor funktioniert weiterhin für allgemeine Fragen.';
$string['config_fixedcourseid'] = 'Feste Kurs-ID (optional)';
$string['config_fixedcourseid_help'] = 'Erzwingt, dass unabhängig von der Seite eine bestimmte Kurs-ID als Kontext übergeben wird. Auf 0 belassen, um den Seitenkontext zu verwenden.';
$string['config_welcomemessage'] = 'Begrüßungsnachricht';
$string['config_persona'] = 'Bezeichnung der Assistenten-Persona';
$string['config_historyenabled'] = 'Gesprächsverlauf aktivieren';

// Display modes.
$string['displaymode_embedded'] = 'Im Block eingebettet';
$string['displaymode_docked'] = 'Angedocktes schwebendes Panel';
$string['displaymode_modal'] = 'Modaler Dialog';
$string['displaymode_fullscreen'] = 'Vollbild';

// Admin settings — RAG.
$string['setting_header_rag'] = 'RAG-/Tutor-MCP-Server';
$string['setting_header_rag_desc'] = 'Verbindung zum externen RAG-(Retrieval-Augmented-Generation-)/Tutor-MCP-Server, der Chat-Nachrichten beantwortet. Der gesamte Datenverkehr zu diesem Server erfolgt serverseitig von Moodle aus.';
$string['setting_ragserverurl'] = 'URL des RAG-MCP-Servers';
$string['setting_ragserverurl_desc'] = 'Der MCP-Streamable-HTTP-Endpunkt des RAG-/Tutor-Servers, z. B. https://rag.example.com/mcp. Muss HTTPS sein, sofern unten nicht ausdrücklich unsicherer Transport erlaubt wird.';
$string['setting_ragauthmethod'] = 'RAG-Authentifizierungsmethode';
$string['setting_ragauthmethod_desc'] = 'Wie sich Moodle gegenüber dem RAG-Server authentifiziert. „Bearer“ sendet einen Authorization: Bearer-Header; „Eigener Header“ sendet den Token-Wert unverändert als vollständige Header-Zeile.';
$string['authmethod_none'] = 'Keine';
$string['authmethod_bearer'] = 'Bearer-Token';
$string['authmethod_header'] = 'Eigener Header';
$string['setting_ragauthtoken'] = 'RAG-Authentifizierungstoken';
$string['setting_ragauthtoken_desc'] = 'Der Bearer-Token oder die vollständige Zeile „Header-Name: Wert“ für die Methode mit eigenem Header. Verschlüsselt gespeichert und niemals an den Browser gesendet.';
$string['setting_chattoolname'] = 'Name des Chat-Tools';
$string['setting_chattoolname_desc'] = 'Das über tools/call aufgerufene MCP-Tool zur Beantwortung einer Chat-Nachricht. Standard: tutor_chat.';
$string['setting_historytoolname'] = 'Name des Verlauf-Tools';
$string['setting_historytoolname_desc'] = 'Optionales MCP-Tool, das frühere Nachrichten eines Gesprächs zurückgibt, z. B. tutor_get_history. Leer lassen, wenn der Server dies nicht unterstützt.';
$string['setting_deletetoolname'] = 'Name des Lösch-Tools';
$string['setting_deletetoolname_desc'] = 'Optionales MCP-Tool, das ein Gespräch auf dem RAG-Server löscht, z. B. tutor_delete_conversation. Wenn gesetzt, wird beim Löschen eines Gesprächs in Moodle dieses auch auf dem RAG-Server entfernt. Leer lassen, um nur den lokalen Verweis zu löschen.';
$string['setting_allowinsecuretransport'] = 'Unsicheren (HTTP-)Transport erlauben';
$string['setting_allowinsecuretransport_desc'] = 'Erlaubt eine einfache http://-RAG-URL. Dringend abzuraten; nur für die lokale Entwicklung.';
$string['setting_allowprivatenetwork'] = 'Privaten/internen RAG-Host erlauben';
$string['setting_allowprivatenetwork_desc'] = 'Umgeht die cURL-Sicherheit von Moodle (gesperrte Hosts und erlaubte Ports) ausschließlich für den konfigurierten RAG-Server. Aktivieren Sie dies, wenn der RAG-Server in einem internen Netzwerk oder auf einem lokalen Entwicklungs-Host wie host.docker.internal läuft, dessen private Adresse oder nicht standardmäßiger Port andernfalls blockiert würde. In der Produktion AUS lassen: Es entfernt eine Schutzebene gegen SSRF.';
$string['setting_requesttimeout'] = 'Anfrage-Timeout (Sekunden)';
$string['setting_requesttimeout_desc'] = 'Maximale Wartezeit auf eine RAG-Antwort, bevor abgebrochen wird.';
$string['setting_streamingenabled'] = 'Streaming-Antworten aktivieren';
$string['setting_streamingenabled_desc'] = 'Verwendet Streaming (Server-Sent Events), wenn der RAG-Server dies unterstützt. Der Connector verarbeitet gestreamte Antworten transparent.';

// Admin settings — token.
$string['setting_header_token'] = 'Verwaltung der Moodle-MCP-Tokens';
$string['setting_header_token_desc'] = 'Der Connector stellt einen kurzlebigen, nutzerbezogenen Moodle-MCP-Token (über die interne API von webservice_elediamcp) bereit und übergibt ihn an den RAG-Server, damit der Tutor im Namen der lernenden Person handeln kann.';
$string['setting_mcpserviceid'] = 'MCP-externer Dienst';
$string['setting_mcpserviceid_desc'] = 'Der externe MCP-Dienst, auf den Nutzertokens beschränkt sind. Wählen Sie einen der im Plugin webservice_elediamcp konfigurierten Dienste.';
$string['setting_mcpserviceid_none'] = 'Nicht konfiguriert';
$string['setting_tokenlifetime'] = 'Token-Gültigkeitsdauer (Sekunden)';
$string['setting_tokenlifetime_desc'] = 'Wie lange ein bereitgestellter Token gültig bleibt, bevor ein neuer erzeugt wird. Auf 0 setzen für nicht ablaufende Tokens (nicht empfohlen).';

// Admin settings — behaviour.
$string['setting_header_behaviour'] = 'Verhalten und Limits';
$string['setting_defaultdisplaymode'] = 'Standard-Anzeigemodus';
$string['setting_defaultdisplaymode_desc'] = 'Anzeigemodus, der von neuen Block-Instanzen verwendet wird.';
$string['setting_enableglobalchat'] = 'Globalen Chat aktivieren';
$string['setting_enableglobalchat_desc'] = 'Erlaubt dem Tutor, allgemeine (kursunabhängige) Fragen zu beantworten.';
$string['setting_enablecoursechat'] = 'Kurs-Chat aktivieren';
$string['setting_enablecoursechat_desc'] = 'Erlaubt dem Tutor, Kurskontext zu erhalten und kursspezifische Fragen zu beantworten.';
$string['setting_maxmessagelength'] = 'Maximale Nachrichtenlänge';
$string['setting_maxmessagelength_desc'] = 'Die längste akzeptierte Nutzernachricht, in Zeichen.';
$string['setting_ratelimitperminute'] = 'Ratenbegrenzung (Nachrichten pro Minute)';
$string['setting_ratelimitperminute_desc'] = 'Maximale Anzahl von Chat-Nachrichten, die ein einzelner Nutzer pro Minute senden darf. Auf 0 setzen, um zu deaktivieren.';
$string['setting_loggingverbosity'] = 'Protokollierungsdetailgrad';
$string['setting_loggingverbosity_desc'] = 'Wie viel der Connector protokolliert. Geheimnisse und vollständige Nachrichteninhalte werden niemals protokolliert.';
$string['loglevel_errors'] = 'Nur Fehler';
$string['loglevel_normal'] = 'Normal';
$string['loglevel_verbose'] = 'Ausführlich';

// Errors.
$string['error_connector_missing'] = 'Das erforderliche MCP-Connector-Plugin (webservice_elediamcp) ist nicht installiert oder deaktiviert.';
$string['error_service_not_configured'] = 'In den Einstellungen des eLeDia.ai Tutors wurde kein MCP-externer Dienst ausgewählt.';
$string['error_service_unavailable'] = 'Der konfigurierte MCP-externe Dienst ist nicht verfügbar oder nicht aktiviert.';
$string['error_rag_url_missing'] = 'Die URL des RAG-/Tutor-Servers wurde nicht konfiguriert.';
$string['error_rag_url_invalid'] = 'Die konfigurierte URL des RAG-/Tutor-Servers ist keine gültige URL.';
$string['error_rag_url_insecure'] = 'Die URL des RAG-/Tutor-Servers muss HTTPS verwenden.';
$string['error_rag_unavailable'] = 'Der Tutor-Dienst ist vorübergehend nicht verfügbar. Bitte versuchen Sie es erneut.';
$string['error_rag_bad_response'] = 'Der Tutor hat eine unerwartete Antwort zurückgegeben.';
$string['error_rag_tool_error'] = 'Der Tutor konnte Ihre Anfrage nicht abschließen.';
$string['error_message_empty'] = 'Ihre Nachricht ist leer.';
$string['error_message_too_long'] = 'Ihre Nachricht überschreitet die maximale Länge von {$a} Zeichen.';
$string['error_rate_limited'] = 'Sie senden Nachrichten zu schnell. Bitte warten Sie {$a} Sekunden.';
$string['error_token_provision_failed'] = 'Für Ihr Konto konnte kein Moodle-MCP-Token bereitgestellt werden.';
$string['error_invalid_context'] = 'Ungültiger Kontext.';
$string['error_conversation_not_found'] = 'Gespräch nicht gefunden.';
$string['error_course_chat_disabled'] = 'Der Kurs-Chat ist auf dieser Website deaktiviert.';
$string['error_global_chat_disabled'] = 'Der globale Chat ist auf dieser Website deaktiviert.';

// Events.
$string['event_message_sent'] = 'Tutor-Nachricht gesendet';
$string['event_response_received'] = 'Tutor-Antwort empfangen';
$string['event_rag_request_failed'] = 'Tutor-Anfrage fehlgeschlagen';
$string['event_conversation_created'] = 'Tutor-Gespräch erstellt';
$string['event_conversation_cleared'] = 'Tutor-Gespräch gelöscht';
$string['event_token_provisioned'] = 'Tutor-MCP-Token bereitgestellt';
$string['event_configuration_error'] = 'Tutor-Konfigurationsfehler erkannt';

// Privacy.
$string['privacy:conversations'] = 'eLeDia.ai Tutor-Gespräche';
$string['privacy:metadata:block_elediaaitutor_conv'] = 'Schlanke Metadaten zu Ihren Tutor-Gesprächen. Vollständige Transkripte werden auf dem externen RAG-/Tutor-Server gespeichert, nicht in Moodle.';
$string['privacy:metadata:block_elediaaitutor_conv:userid'] = 'Der Nutzer, dem das Gespräch gehört.';
$string['privacy:metadata:block_elediaaitutor_conv:courseid'] = 'Der Kurs, in dem das Gespräch begonnen wurde, sofern vorhanden.';
$string['privacy:metadata:block_elediaaitutor_conv:conversationid'] = 'Die Kennung des Gesprächs auf dem RAG-/Tutor-Server.';
$string['privacy:metadata:block_elediaaitutor_conv:title'] = 'Ein optionaler Anzeigetitel für das Gespräch.';
$string['privacy:metadata:block_elediaaitutor_conv:lastpreview'] = 'Eine kurze Vorschau der jüngsten Nachricht.';
$string['privacy:metadata:block_elediaaitutor_conv:timecreated'] = 'Wann das Gespräch erstellt wurde.';
$string['privacy:metadata:block_elediaaitutor_conv:timemodified'] = 'Wann das Gespräch zuletzt aktualisiert wurde.';
$string['privacy:metadata:rag_server'] = 'Um Ihre Fragen zu beantworten, werden Nachrichten an den externen RAG-/Tutor-Server gesendet, der das vollständige Gespräch gemäß seiner eigenen Richtlinie speichert.';
$string['privacy:metadata:rag_server:userid'] = 'Ihre Moodle-Nutzeridentität (über einen nutzerbezogenen Token), damit der Tutor in Ihrem Namen handeln kann.';
$string['privacy:metadata:rag_server:message'] = 'Der Nachrichtentext, den Sie an den Tutor senden.';
$string['privacy:metadata:rag_server:courseid'] = 'Der Kurskontext, sofern angegeben.';
$string['privacy:metadata:rag_server:conversationid'] = 'Die Gesprächskennung, um den Kontext über mehrere Beiträge hinweg zu erhalten.';

// Cache definitions.
$string['cachedef_usertoken'] = 'Kurzlebiger Speicher für nutzerbezogene Moodle-MCP-Tokens';
$string['cachedef_ratelimit'] = 'Zähler für die Chat-Ratenbegrenzung pro Nutzer';
