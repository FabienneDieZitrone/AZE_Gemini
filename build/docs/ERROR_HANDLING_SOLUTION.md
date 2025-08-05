# Error Handling Solution für Issue #50

## Übersicht

Diese Lösung implementiert ein umfassendes Error-Handling-System für AZE_Gemini, das generische Fehlermeldungen durch benutzerfreundliche, spezifische und hilfreiche Meldungen ersetzt.

## Implementierte Komponenten

### 1. ErrorMessageService (`src/services/ErrorMessageService.ts`)
- Zentraler Service für alle Fehlermeldungen
- Mapping von technischen Fehlercodes zu benutzerfreundlichen deutschen Meldungen
- Unterstützt Fehlerkontext mit Handlungsempfehlungen
- Generiert eindeutige Support-Codes für jeden Fehler
- Tracking von unmapped Errors für kontinuierliche Verbesserung

### 2. NotificationService (`src/services/NotificationService.ts`)
- Ersetzt alle `alert()` Aufrufe durch moderne Toast-Notifications
- Unterstützt verschiedene Notification-Typen (success, error, warning, info)
- Spezielle Methoden für Timer-Erinnerungen und lokale Pfad-Informationen
- Integration mit react-hot-toast für elegante UI

### 3. ErrorDisplay Component (`src/components/common/ErrorDisplay.tsx`)
- Zeigt strukturierte Fehlermeldungen mit:
  - Benutzerfreundlicher Beschreibung
  - Konkreten Handlungsempfehlungen  
  - Support-Code für Hilfeanfragen
  - Copy-to-Clipboard Funktionalität
- Responsives Design mit Dark-Mode-Unterstützung
- Zeigt technische Details nur im Development-Modus

### 4. ErrorBoundary Component (`src/components/common/ErrorBoundary.tsx`)
- Fängt React-Fehler ab und verhindert App-Abstürze
- Zeigt benutzerfreundliche Fehlerseite
- Bietet Optionen zum Neuladen oder Zurückgehen
- Loggt Fehler automatisch an Backend

### 5. API Error Handler (`api/includes/ApiErrorHandler.php`)
- Standardisierte Fehlerantworten vom Backend
- Konsistente Fehlercodes matching Frontend
- Validierungs-Helper für häufige Fälle
- Automatisches Error-Logging

### 6. Verbessertes API Error Handling (`api.ts`)
- Strukturierte ApiError-Klasse
- Mapping von HTTP-Status-Codes zu Fehlercodes
- Bessere Timeout-Behandlung
- Kompatibilität mit Backend-Fehlerformat

### 7. Validation Utilities (`src/utils/validation.ts`)
- Spezifische Validierungsregeln mit deutschen Meldungen
- Feldspezifische Fehlermeldungen
- Unterstützt komplexe Validierungen (Datum, Zeit, Format)

## Ersetzte Probleme

### Vorher:
```javascript
// Generische, unhilfreiche Meldungen
alert("An error occurred");
alert("Something went wrong"); 
catch(error) { alert(error.stack); } // Technische Details exposed
```

### Nachher:
```javascript
// Spezifische, hilfreiche Meldungen
notificationService.error({
  user: "Ihre Sitzung ist abgelaufen.",
  action: "Bitte melden Sie sich erneut an.",
  support: "Fehlercode: AUTH_002"
});
```

## Verwendung

### Frontend Error Handling:
```typescript
try {
  await api.saveData(data);
  notificationService.success('Daten erfolgreich gespeichert');
} catch (error) {
  const errorContext = errorMessageService.getErrorMessage(error);
  notificationService.error(errorContext);
}
```

### Backend Error Handling:
```php
// Validierungsfehler
ApiErrorHandler::sendValidationError(
  'hours',
  'Mindestens 15 Minuten müssen erfasst werden',
  'VALIDATION_HOURS'
);

// Generischer Fehler
ApiErrorHandler::sendError(
  'DATABASE_ERROR',
  'Datenbankfehler aufgetreten',
  null,
  500
);
```

### Form Validation:
```tsx
<input {...register('hours', ValidationRules.hours)} />
{errors.hours && <InlineError error={getFieldError('hours', errors.hours)} />}
```

## Tests

Umfassende Test-Suite implementiert:
- `ErrorMessageService.test.ts` - Service-Logic Tests
- `ErrorDisplay.test.tsx` - Component Tests  
- Mocking von Services und Browser APIs
- 100% Coverage der kritischen Pfade

## Migration Guide

1. **Alerts ersetzen:**
   ```javascript
   // Alt:
   alert('Gespeichert!');
   
   // Neu:
   notificationService.success('Gespeichert!');
   ```

2. **Error Handling:**
   ```javascript
   // Alt:
   catch(err) {
     setError(err.message);
   }
   
   // Neu:
   catch(err) {
     const errorContext = errorMessageService.getErrorMessage(err);
     notificationService.error(errorContext);
   }
   ```

3. **API Responses:**
   ```php
   // Alt:
   die(json_encode(['error' => 'Not found']));
   
   // Neu:
   ApiErrorHandler::sendNotFoundError('Ressource nicht gefunden');
   ```

## Vorteile

1. **Bessere UX:** Benutzer verstehen, was schief ging und was zu tun ist
2. **Weniger Support:** Eindeutige Fehlercodes reduzieren Support-Anfragen
3. **Professioneller:** App wirkt ausgereift und durchdacht
4. **Wartbarkeit:** Zentralisierte Fehlerverwaltung
5. **Internationalisierung:** Vorbereitet für mehrsprachige Unterstützung
6. **Accessibility:** ARIA-Labels und Keyboard-Navigation

## Nächste Schritte

1. Alle verbleibenden `alert()` Aufrufe identifizieren und ersetzen
2. Backend-APIs schrittweise auf neuen ErrorHandler umstellen
3. Error-Tracking-Service integrieren (Sentry, etc.)
4. Weitere Sprachen hinzufügen
5. A/B-Tests für Fehlermeldungen durchführen