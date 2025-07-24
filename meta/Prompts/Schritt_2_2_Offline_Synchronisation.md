---
title: Prompt für Schritt 2.2 - Offline-Synchronisation vorbereiten
description: Detaillierter Prompt zur Implementierung der SQLite-Integration und Sync-Queue-Mechanismus
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 2.2: Offline-Synchronisation vorbereiten

## Aufgabe
Implementiere die vollständige Offline-Funktionalität mit SQLite-Datenbank und einem robusten Synchronisationsmechanismus.

## Zu erstellende Komponenten

### 1. OfflineDbContext.cs
```csharp
public class OfflineDbContext : DbContext
{
    // Identische Struktur wie ApplicationDbContext
    // SQLite-spezifische Konfiguration
    // Automatische Datenbank-Erstellung
}
```

### 2. SyncQueue.cs (Entity)
```csharp
public class SyncQueue
{
    public int SyncQueueID { get; set; }
    public string EntityType { get; set; }
    public int EntityID { get; set; }
    public string Operation { get; set; } // INSERT, UPDATE, DELETE
    public string SerializedData { get; set; }
    public DateTime CreatedAt { get; set; }
    public int RetryCount { get; set; }
    public string LastError { get; set; }
    public SyncStatus Status { get; set; }
}
```

### 3. ISyncService Interface
```csharp
public interface ISyncService
{
    Task<bool> IsOnlineAsync();
    Task<SyncResult> SyncAllAsync();
    Task<SyncResult> SyncEntityAsync<T>(T entity) where T : class;
    Task QueueForSyncAsync<T>(T entity, SyncOperation operation) where T : class;
    Task<IEnumerable<SyncQueue>> GetPendingSyncItemsAsync();
    event EventHandler<SyncEventArgs> SyncStatusChanged;
}
```

### 4. SyncService Implementierung
Hauptfunktionalitäten:
- Netzwerk-Status-Überwachung
- Automatische Synchronisation
- Konfliktauflösung
- Fehlerbehandlung und Retry-Logic
- Progress-Reporting

### 5. OfflineRepository<T>
```csharp
public class OfflineRepository<T> : GenericRepository<T> where T : class
{
    private readonly OfflineDbContext _offlineContext;
    private readonly ISyncService _syncService;
    
    public override async Task<T> AddAsync(T entity)
    {
        // In Offline-DB speichern
        // In Sync-Queue einreihen
        // Sync versuchen wenn online
    }
}
```

### 6. NetworkMonitor.cs
```csharp
public class NetworkMonitor : INetworkMonitor
{
    public bool IsOnline { get; private set; }
    public event EventHandler<NetworkStatusEventArgs> NetworkStatusChanged;
    
    Task StartMonitoringAsync();
    Task StopMonitoringAsync();
    Task<bool> CheckConnectivityAsync();
}
```

### 7. ConflictResolver.cs
```csharp
public class ConflictResolver : IConflictResolver
{
    Task<ConflictResolution> ResolveAsync<T>(T localEntity, T serverEntity);
    Task<bool> CanAutoResolveAsync(ConflictInfo conflict);
    ConflictStrategy GetStrategyForEntity(Type entityType);
}
```

### 8. SyncConfiguration.cs
```csharp
public class SyncConfiguration
{
    public Dictionary<Type, SyncStrategy> EntityStrategies { get; set; }
    public int MaxRetryCount { get; set; } = 3;
    public int RetryDelaySeconds { get; set; } = 5;
    public int BatchSize { get; set; } = 100;
    public ConflictResolutionMode DefaultMode { get; set; }
}
```

## Synchronisations-Strategien

### 1. Zeitstempel-basierte Synchronisation
- Jede Entity hat LastModified timestamp
- Server-Zeit als authoritative Quelle
- Client-Server Zeit-Differenz berücksichtigen

### 2. Konfliktauflösungs-Modi
```csharp
public enum ConflictResolutionMode
{
    ServerWins,      // Server-Daten haben Vorrang
    ClientWins,      // Client-Daten haben Vorrang
    LastWriteWins,   // Neueste Änderung gewinnt
    Manual,          // Benutzer entscheidet
    Merge            // Automatisches Merging
}
```

### 3. Entity-spezifische Regeln
- **Arbeitszeiten**: Client wins (lokale Erfassung wichtiger)
- **Stammdaten**: Server wins (zentrale Verwaltung)
- **Benutzer**: Merge (bestimmte Felder zusammenführen)

## Sync-Ablauf

### 1. Startup-Synchronisation
```csharp
public async Task InitialSyncAsync()
{
    // 1. Netzwerk prüfen
    // 2. Ausstehende Items aus Queue laden
    // 3. Nach Priorität sortieren
    // 4. Batch-weise synchronisieren
    // 5. Erfolge/Fehler protokollieren
}
```

### 2. Laufzeit-Synchronisation
```csharp
public async Task RuntimeSyncAsync()
{
    // Alle 30 Sekunden
    // Nur wenn online
    // Nur geänderte Daten
    // Mit Backoff bei Fehlern
}
```

### 3. Shutdown-Synchronisation
```csharp
public async Task FinalSyncAsync()
{
    // Letzte Chance für Sync
    // Timeout nach 10 Sekunden
    // Status in Local Storage
}
```

## Datenbank-Schema (SQLite)

### Zusätzliche Sync-Tabellen:
```sql
CREATE TABLE SyncQueue (
    SyncQueueID INTEGER PRIMARY KEY AUTOINCREMENT,
    EntityType TEXT NOT NULL,
    EntityID INTEGER NOT NULL,
    Operation TEXT NOT NULL,
    SerializedData TEXT NOT NULL,
    CreatedAt TEXT NOT NULL,
    RetryCount INTEGER DEFAULT 0,
    LastError TEXT,
    Status INTEGER NOT NULL
);

CREATE TABLE SyncLog (
    SyncLogID INTEGER PRIMARY KEY AUTOINCREMENT,
    SyncQueueID INTEGER,
    Timestamp TEXT NOT NULL,
    Success INTEGER NOT NULL,
    ErrorMessage TEXT,
    ServerResponse TEXT
);

CREATE TABLE SyncMetadata (
    TableName TEXT PRIMARY KEY,
    LastSyncTime TEXT,
    LastSyncVersion INTEGER,
    PendingChanges INTEGER
);
```

## Error Handling

### 1. Netzwerkfehler
- Automatisches Retry mit Exponential Backoff
- Fehler in Queue speichern
- Benutzer-Notification optional

### 2. Datenkonflikte
- Konflikt-UI anzeigen
- Automatische Auflösung wo möglich
- Audit-Trail für alle Entscheidungen

### 3. Datenintegrität
- Checksums für kritische Daten
- Transactional Sync
- Rollback bei Fehlern

## Benötigte Dateien
- Repository-Klassen aus Schritt 2.1
- Entity-Modelle aus Schritt 1.2
- Konfiguration aus Schritt 1.3

## Erwartete Ausgabe
```
DAL/
├── Context/
│   └── OfflineDbContext.cs
├── Sync/
│   ├── SyncService.cs
│   ├── ConflictResolver.cs
│   ├── NetworkMonitor.cs
│   └── SyncConfiguration.cs
├── Models/
│   ├── SyncQueue.cs
│   ├── SyncLog.cs
│   └── SyncMetadata.cs
├── Repositories/Offline/
│   ├── OfflineRepository.cs
│   └── SyncQueueRepository.cs
└── Interfaces/
    ├── ISyncService.cs
    ├── IConflictResolver.cs
    └── INetworkMonitor.cs
```

## Hinweise
- SQLite In-Memory DB für Unit Tests
- WAL-Mode für bessere Concurrency
- Prepared Statements für Performance
- Compression für SerializedData
- Event-basierte Architektur für UI-Updates