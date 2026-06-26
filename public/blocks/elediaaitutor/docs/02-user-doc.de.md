# eLeDia.ai Tutor

Der eLeDia.ai Tutor stellt Lernenden einen KI-gestuetzten Chat direkt in Moodle bereit. Je nach Konfiguration erscheint er als Block im Kurs, als schwebendes Panel, als Dialog oder als Vollbildansicht. Der Tutor arbeitet immer mit den Rechten der angemeldeten Person und sieht nur Moodle-Inhalte, die diese Person selbst sehen darf.

## Nutzung fuer Lernende

Beim ersten Start bestaetigen Lernende die Datenschutzhinweise. Danach koennen sie Fragen zum aktuellen Kurs oder allgemeine Moodle-Fragen stellen. Vorgeschlagene Einstiegsfragen erscheinen als Chips unter der Begruessung, wenn die Website oder der Block sie konfiguriert.

- Eine Nachricht wird mit **Enter** gesendet; **Shift+Enter** fuegt einen Zeilenumbruch ein.
- Der Antwortstil kann zwischen **Erklaeren**, **Nur Hinweise** und **Quiz mich** wechseln, sofern die Website dies erlaubt.
- Antworten koennen kopiert werden; fehlgeschlagene Antworten lassen sich erneut versuchen.
- Ueber **Neue Unterhaltung** beginnt ein frischer Verlauf.
- Wenn Verlauf aktiviert ist, oeffnet das Uhr-Symbol gespeicherte Unterhaltungen.
- Im Datenschutzdialog koennen Nutzende ihre eigenen Tutor-Daten loeschen.

## Kurskontext

In einem Kurs beantwortet der Tutor kursbezogene Fragen, zum Beispiel zu Aufgaben, Materialien oder naechsten Schritten. Ausserhalb eines Kurses kann der Tutor allgemeiner helfen, etwa zu Moodle-Navigation oder sichtbaren Kursen. Kurschat funktioniert nur dort, wo der Tutor fuer den Kurs aktiviert ist.

## Tutor-Designs verwalten

Administratorinnen und Administratoren verwalten Tutor-Designs unter **Website-Administration > Plugins > Bloecke > eLeDia.ai Tutor > Tutoren**. Dort stehen Vorlagen und gespeicherte Tutor-Profile zur Verfuegung.

- Eine Vorlage oder ein gespeicherter Tutor kann auf die gesamte Website angewendet werden.
- Eigene Tutor-Profile koennen erstellt, bearbeitet und dupliziert werden.
- Tutor-Pakete koennen importiert und exportiert werden; Einstellungen und Bilder bleiben zusammen.
- Einzelne Blockinstanzen koennen unten auf der Seite ein eigenes Tutor-Design erhalten.
- Lehrende koennen je nach Governance-Einstellung ein eigenes Design fuer ihre Blockinstanz nutzen.

## Administration

Die zentrale Verbindung zur KI wird in den Blockeinstellungen konfiguriert. Dort werden der RAG/MCP-Endpunkt, Authentifizierung, Tool-Namen, Streaming, Limits, Datenschutztexte und die Moodle-MCP-Service-Auswahl gepflegt. Ohne ausgewaehlten MCP-Dienst kann der Tutor keine Moodle-bezogenen AI-Funktionen ausfuehren.

Fuer lokale Entwicklung koennen unsichere Transporte und private Hosts erlaubt werden. In Produktion sollten diese Optionen deaktiviert bleiben, sofern der RAG/MCP-Dienst nicht gezielt intern betrieben wird.

## Datenschutz und Barrierefreiheit

Der Tutor erzwingt die Zustimmung zu den Datenschutzhinweisen serverseitig. Die Benutzeroberflaeche ist per Tastatur bedienbar, neue Antworten werden fuer Screenreader angekuendigt, und reduzierte Bewegung wird respektiert.
