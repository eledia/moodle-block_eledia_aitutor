# eLeDia.ai Tutor — Lösungsüberblick

> **Ein agentischer KI-Tutor für Moodle, der Ihre Kurse wirklich kennt.**
> Ein retrieval-gestützter (RAG) Assistent, der direkt in Moodle lebt *und* in
> jedem KI-Client, den Ihr Team bereits nutzt — fundiert in Ihren Kursinhalten,
> fähig, in Echtzeit auf Moodle-Daten zuzugreifen, vollständig an Ihre Institution
> anpassbar und angetrieben vom Sprachmodell Ihrer Wahl.

**Dokument:** Lösungsüberblick · **Version:** 2.0 · **Datum:** 14. Juni 2026 ·
**Erstellt von:** eLeDia GmbH, Berlin

---

> **Hinweis für die Dokumentgestaltung**
> Dies ist die Quellfassung für eine kundenorientierte Broschüre. Mit
> `📸 SCREENSHOT` markierte Zeilen kennzeichnen Bildpositionen; die vollständige
> Aufnahmeliste steht am Ende. Die Aussagen bitte unverändert übernehmen — sie
> entsprechen dem tatsächlichen Funktionsumfang. Es werden eine englische und eine
> deutsche Ausgabe gepflegt; die Produkte werden mit englischer und deutscher
> Oberfläche ausgeliefert.

---

## 1. Was wir anbieten

eLeDia vereint mehrere Bausteine zu einem nahtlosen Lernassistenten:

1. **eLeDia.ai Tutor** — ein elegantes, natives Chat-Erlebnis direkt in Moodle,
   vollständig brandbar und pro Kurs oder website-weit konfigurierbar.
2. **Moodle-MCP-Server** — ein sicheres Gateway, das Moodle zu einem
   *Werkzeuganbieter* macht, den jeder KI-Agent nutzen kann — im Namen der
   einzelnen lernenden Person und streng innerhalb ihrer Berechtigungen.
3. **Der agentische RAG-/Tutor-Dienst** — das „Gehirn": er ruft Wissen aus Ihren
   Kursmaterialien ab, denkt in mehreren Schritten, fragt bei Bedarf live in
   Moodle nach und antwortet in natürlicher Sprache — angetrieben vom KI-Modell
   Ihrer Wahl.
4. **Automatische Inhalts-Ingestion** — hält die Wissensbasis des Tutors aktuell,
   während sich Ihre Kurse verändern (Abschnitt 7).

Gemeinsam ergeben sie einen Tutor, der **fundiert** ist (er nennt echte Quellen),
**vernetzt** (er schlägt in Echtzeit in Moodle nach), **markenkonform** (er sieht
aus und spricht wie *Ihre* Institution) und **flexibel** (er läuft mit unseren
Modellen oder Ihren).

📸 SCREENSHOT: der eLeDia.ai Tutor in einem Moodle-Kurs — eine formatierte Antwort
mit Quellen-Karte und den Vorschlagsfragen.

---

## 2. Das Lernerlebnis in Moodle

Ein ansprechender, barrierearmer Chat-Assistent sitzt direkt im Kurs. Keine neuen
Logins, kein Kontextwechsel — Lernende stellen Fragen in natürlicher Sprache und
erhalten klare, formatierte Antworten mit Verweisen auf die passenden
Kursmaterialien.

- **Anzeigemodi für jede Seite.** Ein **angedocktes, schwebendes Panel** (die
  Voreinstellung — eine Schaltfläche, die ein aufgeräumtes Chat-Fenster öffnet),
  ein **eingebettetes** Panel auf der Seite, ein zentrierter **Dialog (Modal)**
  oder **Vollbild**. Pro Website und pro Kurs wählbar.
- **Mobil & barrierearm.** Responsive Layouts, Tastaturbedienung,
  Screenreader-Beschriftungen und Rücksicht auf „reduzierte Bewegung".
- **Vorschlagsfragen.** Begrüßen Sie Lernende mit Ein-Klick-Startfragen, damit der
  Einstieg immer gelingt.
- **Antwortstile.** Wechseln Sie den Lehrmodus zwischen **Erklären** (ausführliche
  Erklärungen), **Nur Hinweise** (führt, ohne die Lösung zu verraten) und
  **Abfragen** (Übungsfragen) — Lehrende können einen Stil festlegen oder die Wahl
  freigeben.
- **Gesprächsverlauf.** Lernende können frühere Gespräche erneut öffnen und
  fortsetzen; der Verlauf wird pro Kurs getrennt geführt (oder in einer globalen
  Platzierung zusammengefasst).
- **Datenschutz zuerst.** Vor der ersten Nutzung lesen und bestätigen Lernende Ihre
  Datenschutzhinweise; sie können diese jederzeit erneut einsehen und ihre eigenen
  Daten löschen.

📸 SCREENSHOT: die vier Anzeigemodi nebeneinander (angedockt / eingebettet /
Dialog / Vollbild).
📸 SCREENSHOT: der Begrüßungsbildschirm mit Vorschlagsfragen und der Auswahl
Erklären / Hinweise / Abfragen.

---

## 3. Machen Sie ihn zu Ihrem — Branding, Persona & „Tutor-Profile"

Der eLeDia.ai Tutor ist **vollständig brandbar** — er kann zu einem natürlichen
Teil Ihrer Institution werden statt ein generischer Chatbot zu bleiben.

### Jedes Designdetail ist eine Einstellung
Farben, Flächen, Texte, Nachrichtenblasen, Schriften, Eckenrundung, Abstände,
Schatten, das Avatar-Leuchten, die Start-Schaltfläche — **jedes Designdetail ist
über bedienfreundliche Steuerelemente einstellbar**: echte Farbwähler (Farbfeld
anklicken *oder* einen Hex-Code einfügen) und verständliche Auswahllisten
(„Klein / Mittel / Groß", „Dezent / Stark"), niemals Rohcode. Jede Einstellung
trägt eine kurze Erklärung mit Beispiel.

📸 SCREENSHOT: die Farbwähler und benannten Auswahllisten in den Design-Einstellungen.

### Eine eigene Persona & System-Prompt
Geben Sie dem Tutor einen **Namen, eine Rolle, einen Tonfall und eine Zielgruppe**
sowie freie **Anweisungen**, die prägen, wie er spricht — z. B. „ein geduldiger
Mathe-Tutor für das erste Studienjahr; warm und ermutigend". Diese Persona wird
der KI als Leitlinie übergeben (sie hebt niemals Sicherheits- oder Grounding-Regeln
auf).

📸 SCREENSHOT: die Felder „Persona & System-Prompt".

### Ihr Logo und Avatar
Laden Sie Ihr eigenes **Tutor-Logo** (Kopfzeile + Start-Schaltfläche) und einen
**Gesprächs-Avatar** hoch; beide erscheinen durchgängig im Chat.

### Fertige Looks — und ein bisschen Spaß
Wir liefern **fünf eingebaute Vorlagen (Presets)** mit — *eLeDia (Standard)*,
*Wald*, *Mitternacht*, *Hoher Kontrast* und das verspielte *HAL 9000* — jede eine
vollständige, gut lesbare Farbpalette samt Start-Persona. Mit einem Klick als
Website-Standard oder auf jeden einzelnen Block anwenden. Jede Vorlage öffnet als
angedocktes, schwebendes Panel.

📸 SCREENSHOT: die Seite „Tutoren" mit den Vorlagen-Karten, jeweils mit einer
Live-Mini-Vorschau der Palette.

### Speichern, teilen und wiederverwenden mit „Tutor-Profilen"
Erstellen Sie eigene benannte **Tutor-Profile** und **exportieren / importieren**
Sie sie als einzelne Datei — Einstellungen *und* Bilder inklusive. Einmal gestalten
und über Kurse oder sogar andere Moodle-Instanzen hinweg wiederverwenden. Beim
Import wird das Profil als Momentaufnahme übernommen (keine versteckten
Verknüpfungen, die später brechen).

📸 SCREENSHOT: der Export eines Tutors und der Import-Dialog.

### White-Label
Ersetzen oder verbergen Sie den Fußzeilen-Hinweis für einen vollständig
white-gelabelten Assistenten und ergänzen Sie bei Bedarf institutionsspezifisches
Custom-CSS für pixelgenaue Kontrolle.

---

## 4. Wer steuert was — Governance der Administration & Self-Service für Lehrende

Der Tutor verbindet **institutionelle Einheitlichkeit** mit **Freiheit auf
Kursebene**.

- **Die Administration legt den website-weiten Standard-Tutor fest** und entscheidet
  für *jede* Einstellung, ob Lehrende sie pro Kurs überschreiben dürfen.
  Standardmäßig sind alle Design-/Persona-Einstellungen für Lehrende offen; die
  Administration kann jede davon sperren, um ein einheitliches Erscheinungsbild zu
  wahren.
- **Lehrende passen ihren Kurs-Tutor an** — nur mit den Einstellungen, die die
  Administration freigegeben hat, mit denselben bedienfreundlichen Farbwählern und
  Auswahllisten.
- **Self-Service-Import/-Export für Lehrende.** Aus der Block-Konfiguration heraus
  können bearbeitende Lehrende ihren Tutor **exportieren**, einen erhaltenen Tutor
  **importieren** oder einen der fertigen Tutoren der Institution **anwenden** —
  beschränkt auf den eigenen Kurs.
- **Zentrale Verwaltung.** Die Administration verwaltet die gesamte Tutor-Bibliothek
  (Vorlagen und gespeicherte Profile), wendet einen Tutor auf die Website oder auf
  einen bestimmten Block an und importiert/exportiert website-weite Tutoren — alles
  auf einer Seite.

📸 SCREENSHOT: die Administrationsseite „Tutoren verwalten" — Website-Tutoren und die
Steuerung pro Instanz (Anwenden / Export / Import).
📸 SCREENSHOT: die Kontrollkästchen „Überschreibung pro Instanz erlauben" je
Einstellung.

---

## 5. Zwei Wege, denselben Tutor zu nutzen

Derselbe Assistent ist überall dort verfügbar, wo Ihre Nutzenden arbeiten.

### In Moodle — für alle Lernenden und Lehrenden
Der native Chat-Block (Abschnitte 2–3), direkt in den Kurs eingebettet.

### In jedem MCP-Host — für Power-User, Mitarbeitende und Integrationen
Da Moodle als **Model-Context-Protocol-(MCP-)Server** bereitgestellt wird, lassen
sich dieselben Funktionen aus externen KI-Clients nutzen — etwa **Claude Desktop**,
KI-fähigen IDEs oder einem **eigenen MCP-Host**. Eine mitarbeitende Person verbindet
ihre bevorzugte KI-App und arbeitet direkt mit Moodle-Daten — jeweils mit einem
persönlichen, berechtigungsgebundenen Token.

📸 SCREENSHOT: Claude Desktop (oder ein eigener Client), verbunden mit dem
Moodle-MCP-Server, beantwortet eine Frage zu einem Kurs.

---

## 6. Agentisch und fundiert — kein gewöhnlicher Chatbot

- **Retrieval-gestützt (RAG):** Antworten basieren auf *Ihren* Kursinhalten, nicht
  nur auf den Trainingsdaten des Modells — das reduziert Halluzinationen und hält
  Antworten am Lehrplan. Quellen werden neben der Antwort angezeigt.
- **Agentisch:** Der Tutor denkt in Schritten und nutzt **Werkzeuge** — er kann
  belegte Kurse, anstehende Aufgaben und Fristen, Noten, Kursinhalte und
  Ankündigungen nachschlagen, Inhalte durchsuchen und mehr — live aus Moodle.
- **Berechtigungsbewusst by design:** Jeder Abruf erfolgt unter der Identität und
  den Rechten der lernenden Person. Der Tutor kann nie Daten preisgeben, auf die
  sie nicht ohnehin Zugriff hätte.
- **Allgemeinwissen-Modus.** Hat ein Kurs noch keine Wissensbasis, kann der Tutor
  dennoch aus dem Allgemeinwissen des Modells helfen (klar gekennzeichnet) — oder
  auf ausschließlich fundierte Antworten beschränkt werden.

📸 SCREENSHOT: eine Antwort mit Live-Moodle-Abruf („Was ist diese Woche fällig?")
mit Quellen-Karte und dem Hinweis „basiert auf Kursmaterialien".

**Beispielfragen, die er gut beantwortet**

- „In welchen Kursen bin ich eingeschrieben, und worauf sollte ich diese Woche
  achten?"
- „Was muss ich für die Aufgabe dieser Woche tun, und wann ist sie fällig?"
- „Erkläre das Thema dieser Woche und zeige mir die passende Ressource im Kurs."
- „Wie stehe ich bisher da — wie sind meine aktuellen Noten?"

---

## 7. Immer aktuell — automatische Inhalts-Ingestion

Ein Tutor ist nur so gut wie sein Wissen. Die eLeDia-Ingestion speist Ihre
Kursinhalte fortlaufend in die RAG-Wissensbasis ein, damit Antworten fundiert und
aktuell bleiben. Sie extrahiert den Lehrtext aus Ihren Aktivitäten und Materialien
und übergibt ihn an den RAG-Dienst — automatisch bei jeder Änderung und auf Wunsch
per Massen-Reindex.

- **Breite Abdeckung:** Moodles Kern-Aktivitäten und -Materialien (Buch, Textseite,
  Lektion, Test, Glossar, Aufgabe, Datei, Verzeichnis, SCORM, IMS-Pakete, H5P und
  mehr) sowie verbreitete Erweiterungen.
- **Automatisch und inkrementell:** neue, geänderte oder gelöschte Inhalte werden
  sofort synchronisiert; ein einziger Reindex erfasst einen ganzen Kurs neu.
- **Datenschutzfreundlich:** Es werden nur *Lehrinhalte* erfasst — niemals Abgaben
  oder Antworten von Lernenden.
- **Mandantenfähig sauber:** deterministische Source-IDs halten die Wissensbasis
  über viele Moodle-Instanzen hinweg ordentlich.
- **Erweiterbar:** neue Aktivitätstypen lassen sich ohne Änderung am Kerncode
  unterstützen.

---

## 8. Einblick für Lehrende — Fragen-Analytik

Ein optionaler, **datenschutzsicherer** Analysebericht zeigt Lehrenden, was
Lernende fragen — **ohne jegliche Identität der Lernenden**. Er macht aus
Alltagsfragen ein Bild davon, wo eine Lerngruppe Unterstützung braucht.

- **Kennzahlen auf einen Blick:** Gesamtzahl der Fragen, Fragen der letzten 7 Tage
  und wie viele Antworten in Kursmaterialien fundiert waren.
- **Verständnis-Hotspots:** Fragen gebündelt nach Thema / Hauptquelle, damit Sie
  sehen, welche Materialien die meisten Fragen auslösen — verlinkt zur Aktivität.
- **Verlauf über die Zeit** und eine **Liste der jüngsten Fragen** (nur
  Fragetext, keine Namen), mit dem verwendeten Antwortstil.
- Daten werden nur so lange aufbewahrt, wie Sie es konfigurieren, und sind von den
  Datenschutzwerkzeugen abgedeckt.

📸 SCREENSHOT: der neu gestaltete Analysebericht — Kennzahlen-Karten, die
„Hotspots"-Balken und das Diagramm „Fragen pro Tag".

---

## 9. Ihr Modell, Ihre Daten — Ihre Wahl

Der RAG-Dienst verbindet sich über ein **LiteLLM-Gateway** und gibt Ihnen volle
Kontrolle darüber, *welches* Sprachmodell den Tutor antreibt:

- **eLeDia-gehostete Modelle nutzen** — wir betreiben das Gateway und bieten Zugang
  zu führenden Modellen (z. B. OpenAI), mit europäischen Hosting-Optionen.
- **Eigenen Anbieter einbringen** — richten Sie das Gateway auf Ihr eigenes
  Foundation-Model-Abonnement oder ein selbstgehostetes/offenes Modell. Anbieter
  wechseln oder mischen, ohne in Moodle etwas zu ändern.

Das bedeutet **keine Bindung auf der Modellebene**, planbare Kostenkontrolle und
die Möglichkeit, Anforderungen an Datenstandort und Beschaffung zu erfüllen.

📸 SCREENSHOT / DIAGRAMM: das LiteLLM-Gateway leitet zu mehreren Modellanbietern.

---

## 10. Sicherheit, Datenschutz & Datensouveränität

Von Tag eins nach Moodle- und Enterprise-Sicherheitsstandards gebaut:

- **Geheimnisse bleiben serverseitig.** Moodle-Tokens und
  Modell-/Anbieter-Zugangsdaten gelangen nie in den Browser; die App der
  lernenden Person spricht ausschließlich mit Moodle.
- **Nutzerbezogene, kurzlebige Tokens.** Jede KI-Interaktion nutzt ein an die
  einzelne lernende Person und den konfigurierten Dienst gebundenes Token —
  automatisch bereitgestellt, rotiert, widerrufen und auditierbar.
- **Geringste Rechte.** Der Tutor sieht genau das, was die lernende Person sieht —
  nicht mehr.
- **Dokumentierte Einwilligung.** Bestätigung Ihrer Datenschutzhinweise vor der
  ersten Nutzung, mit Self-Service-Datenlöschung.
- **DSGVO-bereit.** Volle Unterstützung der Moodle-Privacy-API; Gesprächsdaten und
  ihre Aufbewahrung sind transparent, exportierbar und löschbar.
- **Sie behalten die Kontrolle über den Datenfluss.** Wählen Sie, wo der RAG-Dienst
  und die Modelle laufen; On-Prem-/EU-Hosting-Optionen sind verfügbar.

📸 SCREENSHOT: der Datenschutz-Dialog und/oder die Token-Übersicht der Administration.

---

## 11. So funktioniert es

```text
          ┌─────────────────────────┐        ┌──────────────────────────┐
          │   Moodle (Ihr LMS)      │        │  Beliebiger MCP-Host     │
          │  ┌───────────────────┐   │        │  (Claude Desktop, eigene │
          │  │ eLeDia.ai Tutor    │  │        │   App, IDE, …)           │
          │  │ Chat-Block         │  │        └────────────┬─────────────┘
          │  └─────────┬─────────┘   │                     │
          │            │ serverseitig│                     │ nutzerbezogenes
          │            ▼ (Secrets)   │                     │ MCP-Token
          │  nutzerbezogenes Token   │                     │
          └────────────┼─────────────┘                     │
                       │                                    │
                       ▼                                    │
        ┌──────────────────────────────┐                   │
        │  Agentischer RAG-/Tutor-Dienst│◄──────────────────┘
        │  • Retrieval über Kursinhalte │
        │  • mehrstufiges Reasoning      │
        │  • ruft Moodle-Werkzeuge auf   │──────┐
        └───────────────┬───────────────┘       │ Rückruf nach Moodle
                        │                        ▼  (als die lernende Person)
                        │              ┌────────────────────────────┐
                        ▼              │   Moodle-MCP-Server         │
        ┌──────────────────────────┐  │   Kurse, Aufgaben, Noten,   │
        │  LiteLLM-Modell-Gateway   │  │   Inhalte, Kalender …       │
        │  leitet zum gewählten LLM │  └────────────────────────────┘
        └───────────────┬──────────┘
                        │
            ┌───────────┴───────────┐
            ▼                       ▼
   eLeDia-gehostete Modelle   Eigener Anbieter der Kundin
   (z. B. OpenAI über unser   (eigenes Foundation-Model /
    Gateway, EU-Optionen)      eigener Endpunkt)
```

📸 SCREENSHOT / DIAGRAMM: das ASCII-Diagramm oben durch eine gestaltete Grafik
ersetzen.

**Schritt für Schritt, wenn eine lernende Person eine Frage stellt:**

1. Sie tippt in den eLeDia.ai-Tutor-Block in Moodle (oder ihren MCP-Client).
2. Moodle übergibt die Anfrage an den **agentischen RAG-Dienst** zusammen mit einem
   **kurzlebigen, nutzerbezogenen Token** — nie im Browser sichtbar — und der
   Persona des Tutors.
3. Der RAG-Dienst **ruft** relevante Passagen aus Ihrer Kurs-Wissensbasis **ab**
   und **fragt bei Bedarf live in Moodle nach** (Kurse, Fristen, Noten, eine
   Ressource) — über den MCP-Server, als die lernende Person und nur mit ihren
   Sichtrechten.
4. Er formuliert die Antwort mit dem **von Ihnen gewählten Sprachmodell** (über das
   LiteLLM-Gateway) und liefert eine fundierte, mit Quellen belegte Antwort.

---

## 12. Was Sie erhalten

| Komponente | Rolle |
|---|---|
| **eLeDia.ai Tutor** (Moodle-Block) | Das Chat-Erlebnis in Moodle, vollständiges Branding & Tutor-Profile sowie der sichere Connector. |
| **Moodle-MCP-Server** (Web-Service) | Stellt Moodle als berechtigungsgebundene Werkzeuge für KI-Agenten bereit; Self-Service-Tokenverwaltung für Nutzende. |
| **Agentischer RAG-/Tutor-Dienst** | Retrieval über Ihre Inhalte + mehrstufiges Reasoning + Moodle-Werkzeugnutzung. |
| **Inhalts-Ingestion** | Extrahiert Kursinhalte und speist die RAG-Wissensbasis — automatisch und auf Wunsch. |
| **LiteLLM-Modell-Gateway** | Leitet zu eLeDia-gehosteten Modellen oder Ihrem eigenen Anbieter. |
| **Integration & Onboarding** | Einrichtung, Wissensbasis-Ingestion, Modellkonfiguration, Branding und Schulung. |

---

## 13. Warum eLeDia

- Eine **vollständige, integrierte** Lösung — kein aufgesetzter Chatbot: LMS, Agent,
  Werkzeuge und Modellebene sind aufeinander abgestimmt.
- **Markenkonform:** vollständig anpassbarer Look und eigene Persona, mit teilbaren
  Tutor-Profilen.
- **Offene Standards (MCP):** dieselbe Investition dient Moodle *und* externen
  KI-Werkzeugen.
- **Modell-agnostisch** und **datensouverän** — Sie wählen die KI und wo sie läuft.
- **Native, barrierearme, zweisprachige** (Deutsch & Englisch) Moodle-Plugins,
  CI-getestet gegen aktuelle Moodle-Versionen.
- Bereitgestellt und betreut von **eLeDia**, einem spezialisierten Moodle-Partner.

📸 SCREENSHOT: eLeDia-Logo / Marken-Lockup für die Abschlussseite.

---

## 14. In einfachen Worten (Glossar-Box)

- **MCP (Model Context Protocol):** ein offener Standard, der KI-Assistenten den
  sicheren Zugriff auf externe Werkzeuge und Datenquellen ermöglicht. Wir nutzen
  ihn, damit jeder KI-Client mit Moodle arbeiten kann und der Tutor Moodle als
  Werkzeugkasten verwenden kann.
- **RAG (Retrieval-Augmented Generation):** KI-Antworten in Ihren eigenen Inhalten
  verankern, statt sich allein auf das Gedächtnis des Modells zu verlassen.
- **Agentisch:** Die KI denkt in mehreren Schritten und ruft Werkzeuge auf
  (nachschlagen, handeln), statt eine einzelne Einmalantwort zu geben.
- **Tutor-Profil / Vorlage:** ein vollständiges, benanntes Erscheinungsbild samt
  Persona, das angewendet, gespeichert, exportiert und importiert werden kann.
- **LiteLLM:** ein Gateway, das eine einheitliche Anbindung an viele
  Modellanbieter bietet — so kann das Modell frei gewählt oder gewechselt werden.

---

## Aufnahmeliste für Screenshots (für die Gestaltung)

In einem sauberen Demokurs aufnehmen, idealerweise mit zwei kontrastierenden
Vorlagen, damit die Bandbreite des Brandings sichtbar wird. Empfohlene Reihenfolge:

1. **Hero:** Tutor (angedocktes Panel) im Kurs geöffnet, formatierte Antwort + Quellen-Karte.
2. **Anzeigemodi:** angedockt, eingebettet, Dialog, Vollbild (2×2-Montage).
3. **Begrüßung:** Persona-Begrüßung, Vorschlagsfragen, Antwortstil-Auswahl.
4. **Design-Einstellungen:** Farbwähler + benannte Auswahllisten.
5. **Persona-Felder:** Name / Rolle / Tonfall / Zielgruppe / Anweisungen.
6. **Tutoren-Bibliothek:** Vorlagen-Karten mit Paletten-Vorschau (HAL + Wald zeigen).
7. **Import / Export:** der Export-Download und der Import-Dialog.
8. **Governance:** Kontrollkästchen „Überschreibung pro Instanz erlauben".
9. **Tutoren verwalten:** Website-Tutoren + Anwenden/Export/Import pro Instanz.
10. **MCP-Host:** Claude Desktop (oder eigener Client) antwortet aus Moodle.
11. **Fundierte Antwort:** Live-Moodle-Abruf mit Hinweis „basiert auf Kursmaterialien".
12. **Analysebericht:** Kennzahlen-Karten, Hotspots-Balken, Diagramm pro Tag.
13. **Datenschutz:** Dialog der Datenschutzhinweise / Erst-Einwilligung.
14. **Architekturdiagramm:** gestaltete Fassung der Grafik aus Abschnitt 11.
15. **Abschluss:** eLeDia-Marken-Lockup.

---

*Erstellt zur Vertriebsunterstützung. Technische Referenzen — die Plugin-README,
die Administrations-/Integrationsleitfäden und die Spezifikation des
RAG-/Tutor-MCP-Servers — begleiten dieses Dokument für Interessenten mit
Detailbedarf.*
