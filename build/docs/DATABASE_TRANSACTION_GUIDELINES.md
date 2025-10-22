# Database Transaction Guidelines

**KRITISCH**: Fehlende `commit()` Aufrufe führen zu DATENVERLUST!

## 🚨 Das Problem

Das AIOS-Projekt verwendet `DatabaseConnection.php` mit:
```php
$this->connection->autocommit(false);  // Zeile 171
```

**Das bedeutet:**
- ✅ Alle Queries funktionieren
- ✅ `affected_rows` zeigt korrekte Werte
- ✅ Verifikations-Queries innerhalb der Transaction zeigen neue Werte
- ❌ **ABER**: Ohne explizites `commit()` wird ALLES gerollt back!

## ✅ Korrekte Verwendung

### Option 1: Manuelles Commit (aktuell in users.php)

```php
// UPDATE ausführen
$stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
$stmt->bind_param('si', $newRole, $userId);
$stmt->execute();
$affectedRows = $stmt->affected_rows;
$stmt->close();

// Verifikation
$verify = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$verify->bind_param('i', $userId);
$verify->execute();
$verify->bind_result($verifiedRole);
$verify->fetch();
$verify->close();

// KRITISCH: Explizites Commit!
if ($verifiedRole === $newRole) {
    $conn->commit();  // ← NIEMALS vergessen!
    // Jetzt erst ist die Änderung persistent!
}
```

### Option 2: Transaction-Helper (empfohlen für neue APIs)

```php
require_once __DIR__ . '/transaction-helper.php';

$affectedRows = executeUpdateWithCommit(
    $conn,
    "UPDATE users SET role = ? WHERE id = ?",
    'si',
    [$newRole, $userId]
);
// Commit passiert automatisch!
```

### Option 3: Wrapper-Funktion

```php
$affectedRows = executeWithCommit($conn, function($conn) use ($userId, $newRole) {
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param('si', $newRole, $userId);
    $stmt->execute();
    $result = $stmt->affected_rows;
    $stmt->close();
    return $result;
});
// Automatisches Commit + Rollback bei Fehler
```

## 🔍 Code-Review-Checklist

**Vor jedem Commit prüfen:**

- [ ] Enthält die API-Datei `UPDATE`, `INSERT` oder `DELETE` Statements?
- [ ] Wird `DatabaseConnection::getInstance()->getConnection()` verwendet?
- [ ] Gibt es ein explizites `$conn->commit()` nach erfolgreichen Updates?
- [ ] Ist das `commit()` NACH allen Verifikationen aber VOR der Response?
- [ ] Gibt es Error-Handling mit `$conn->rollback()`?

## ⚠️ Häufige Fehler

### ❌ FALSCH: Kein Commit
```php
$stmt->execute();
$affectedRows = $stmt->affected_rows;
// Vergessen: $conn->commit();
send_response(200, ['success' => true]);
// ← Transaction wird gerollt back!
```

### ❌ FALSCH: Commit nach Response
```php
send_response(200, ['success' => true]);
exit();  // ← Script endet hier
$conn->commit();  // ← Wird nie erreicht!
```

### ✅ RICHTIG: Commit vor Response
```php
$stmt->execute();
$conn->commit();  // ← ERST commiten
send_response(200, ['success' => true]);
```

## 🧪 Testing

**Manuelle Verifikation nach API-Änderungen:**

1. Änderung via API durchführen
2. Browser **NICHT** refreshen
3. Direkt in Datenbank prüfen (z.B. phpMyAdmin oder MySQL CLI):
   ```sql
   SELECT * FROM users WHERE id = 5;
   ```
4. Änderung sollte SOFORT in DB sichtbar sein
5. Erst dann Browser refreshen

**Wenn Änderung NICHT in DB:**
→ Fehlendes `commit()`!

## 📋 Betroffene API-Dateien

Alle Dateien die `DatabaseConnection` verwenden und Daten ändern:

- ✅ `api/users.php` - **GEFIXT**: Hat jetzt `$conn->commit()`
- ⚠️ `api/time-entries.php` - **PRÜFEN**: UPDATE/INSERT/DELETE vorhanden
- ⚠️ `api/masterdata.php` - **PRÜFEN**: UPDATE vorhanden
- ⚠️ `api/approvals.php` - **PRÜFEN**: UPDATE vorhanden
- ⚠️ `api/settings.php` - **PRÜFEN**: UPDATE vorhanden

## 🔧 Nächste Schritte

1. **Alle API-Dateien prüfen** die Daten ändern
2. **Explizite Commits hinzufügen** wo fehlend
3. **Tests implementieren** die tatsächliche DB-Persistenz prüfen
4. **Erwägen**: Umstieg auf `transaction-helper.php` für neue APIs

## 📚 Weiterführende Dokumentation

- MySQL Transactions: https://dev.mysql.com/doc/refman/8.0/en/commit.html
- PHP MySQLi Transactions: https://www.php.net/manual/en/mysqli.commit.php
- ACID Properties: https://en.wikipedia.org/wiki/ACID

---

**Erstellt**: 2025-10-21
**Grund**: Critical data loss bug in users.php (Git commit: bf0ac66)
**Autor**: Günnix
