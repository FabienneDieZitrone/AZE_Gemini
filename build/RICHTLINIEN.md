# Entwicklungsrichtlinien für die Arbeitszeiterfassung (AZE)

Dieses Dokument definiert die Standards, Prozesse und Best Practices für die Entwicklung des Projekts "Arbeitszeiterfassung". Es dient als zentrale Anlaufstelle für alle Entwickler, um Konsistenz, Qualität und Wartbarkeit des Codes sicherzustellen.

## 1. Projektübersicht

- **Zweck**: Eine moderne, zuverlässige Webanwendung zur Erfassung und Verwaltung von Arbeitszeiten für Mitarbeiter und Honorarkräfte.
- **Technologie-Stack**:
  - **Frontend**: React 18+ mit TypeScript, Vite als Build-Tool.
  - **Authentifizierung**: OAuth 2.0 mit MSAL (Microsoft Authentication Library) gegen Azure Active Directory.
  - **Styling**: CSS mit Variablen (für Theming).
  - **Backend (Annahme)**: Node.js / .NET API.
  - **Datenbank**: MySQL/MariaDB.

## 2. Entwicklungsrichtlinien

### Code-Stil und Konventionen

- **Sprache**:
  - **UI-Texte & Kommentare**: Deutsch.
  - **Code (Variablen, Funktionen, Klassen etc.)**: Englisch. Dies fördert die Lesbarkeit und folgt globalen Standards.
- **Formatierung**:
  - Ein automatischer Formatter (z.B. Prettier) ist zu verwenden, um einen einheitlichen Code-Stil zu gewährleisten.
  - **Naming**:
    - **Komponenten**: `PascalCase` (z.B. `TimeSheetView.tsx`).
    - **Variablen & Funktionen**: `camelCase` (z.B. `handleLogin`).
    - **Konstanten**: `UPPER_SNAKE_CASE` (z.B. `API_BASE_URL`).
    - **CSS-Klassen**: `kebab-case` (z.B. `modal-header`).
- **Komponenten-Struktur**:
  - Komponenten sollen klein und auf eine einzige Aufgabe fokussiert sein (Single Responsibility Principle).
  - Props sollen de-strukturiert und typisiert werden.
  - Alle Komponenten sind als `React.FC` zu definieren.

### Dokumentation im Code

- **Datei-Header**: Jede `.ts` und `.tsx` Datei beginnt mit einem standardisierten Kommentarblock (wie bereits praktiziert), der Titel, Version, Autor ("MP-IT"), Status und eine kurze Beschreibung enthält.
- **Funktionen & Methoden**: Alle öffentlichen/exportierten Funktionen, Hooks und Komponenten erhalten eine JSDoc/TSDoc-Dokumentation, die ihre Funktion, Parameter (`@param`) und Rückgabewerte (`@returns`) beschreibt.
- **Komplexe Logik**: Komplexe oder nicht-offensichtliche Code-Abschnitte sind mit Inline-Kommentaren zu erklären.

### State Management

- **Lokal**: Für den Zustand einzelner Komponenten wird `useState` verwendet. Für komplexe lokale Zustände `useReducer`.
- **Global**: Für global benötigte Daten (z.B. angemeldeter Benutzer, globale Einstellungen) wird `React Context` in Kombination mit `useContext` und `useReducer` verwendet. Auf externe State-Management-Bibliotheken wird vorerst verzichtet.

## 3. Wichtige Anforderungen

### Funktional

1.  **Automatische Benutzeranmeldung**: Sicherer Login via OAuth2 (Azure AD).
2.  **Standorterkennung**: Die Erkennung des Standorts erfolgt serverseitig (z.B. IP-basiert). Das Frontend erhält den Standort vom Backend.
3.  **Offline-Fähigkeit**: Die Anwendung muss grundlegende Funktionen (z.B. Start/Stop der Zeiterfassung) offline ermöglichen. Daten werden im Browser (z.B. via **IndexedDB**) zwischengespeichert und automatisch synchronisiert, sobald wieder eine Verbindung besteht.
4.  **Rollenbasierte Berechtigungen**: Strikte Trennung von Sichten und Aktionen basierend auf 5 Rollen (Admin, Bereichsleiter, etc.).
5.  **Genehmigungsworkflow**: Nachträgliche Änderungen oder Löschungen von Zeiteinträgen erfordern die Genehmigung durch einen Vorgesetzten.
6.  **Audit-Trail**: Alle Änderungen an Stammdaten und Zeiteinträgen müssen lückenlos protokolliert und einsehbar sein.

### Technisch & Sicherheit

1.  **DSGVO-Konformität**: Alle Datenverarbeitungsprozesse müssen den Richtlinien der DSGVO entsprechen.
2.  **Sicherheit**:
    - Keine Passwörter oder andere Secrets im Frontend-Code. Authentifizierung ausschließlich via OAuth2.
    - Alle API-Endpunkte (außer Login/Fehler-Logging) sind durch das Bearer-Token abzusichern.
    - Validierung aller Benutzereingaben im Frontend und Backend.
3.  **Performance**:
    - Code-Splitting pro Route/View wird durch Vite automatisch gehandhabt.
    - Vermeidung unnötiger Re-Renders durch `React.memo`, `useCallback` und `useMemo`.
    - Optimierung von Ladezeiten (z.B. Komprimierung von Assets).

### UI-Anforderungen

- **Logo**: Das MP-Logo ist in allen Ansichten prominent im Header platziert.
- **Design**: Responsive Design (Mobile-First-Ansatz), das auf allen gängigen Geräten (Desktop, Tablet, Smartphone) eine gute UX bietet.
- **Farbschema**: Die Farben sind in `index.css` als CSS-Variablen definiert und unterstützen einen Light- und Dark-Mode.
  - **Primärfarbe (Akzent)**: `var(--accent-color)` (#0056b3)
- **Schriftart**: `var(--font-family)` (System-Schriftarten für optimale Performance und natives Look-and-Feel).

## 4. Datenbank

- **Provider**: MySQL/MariaDB
- **Produktionsdatenbank**: `db10454681-aze`
- **WICHTIG**: Die Zugangsdaten dürfen **niemals** im Code (weder Frontend noch Backend) hartcodiert werden. Im Backend sind diese über sichere Umgebungsvariablen zu verwalten.

## 5. Testing

- **Ziel**: Eine hohe Testabdeckung (> 90%) zur Sicherstellung der Code-Qualität.
- **Unit-/Integrationstests**: Geschäftslogik (Hilfsfunktionen, API-Services) wird mit **Vitest** getestet.
- **Komponententests**: UI-Komponenten werden mit **React Testing Library** getestet. Der Fokus liegt auf der Simulation von Benutzerinteraktionen, nicht auf der Implementierungsdetails.
- **Ausführung**: Tests können via `npm test` ausgeführt werden.

## 6. Prozess & Schrittabschluss

Ein Arbeitsschritt (z.B. ein Feature) gilt erst als abgeschlossen, wenn die folgende "Definition of Done" erfüllt ist:

- [ ] Der Code wurde erfolgreich implementiert und erfüllt alle Anforderungen des Tasks.
- [ ] Alle zugehörigen Tests (Unit, Integration, Component) sind geschrieben und laufen erfolgreich (`npm test`).
- [ ] Die Testabdeckung für den neuen/geänderten Code liegt bei über 90%.
- [ ] Der Code wurde von mindestens einer anderen Person im PR-Prozess geprüft und genehmigt.
- [ ] Es gibt keine `TODO`-Kommentare oder auskommentierten Code mehr.
- [ ] Alle Compiler-Warnungen und Linter-Fehler sind behoben.
- [ ] Die Dokumentation (im Code und ggf. in dieser Datei) ist aktualisiert.
- [ ] Der `feature`-Branch wurde erfolgreich in `develop` gemerged.
- [ ] **Benutzerbestätigung**: Der Benutzer hat die Funktion auf dem Zielsystem getestet und als erfolgreich bestätigt.

---

**Goldene Regel**: Code wird öfter gelesen als geschrieben. Schreiben Sie für den nächsten Entwickler - das könnten Sie in 6 Monaten sein! 😊
