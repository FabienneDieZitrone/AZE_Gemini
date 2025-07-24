---
title: Prompt für Schritt 2.1 - Repository-Pattern implementieren
description: Detaillierter Prompt zur Implementierung des Repository-Patterns
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 2.1: Repository-Pattern implementieren

## Aufgabe
Implementiere ein generisches Repository-Pattern mit spezifischen Repositories für die Arbeitszeiterfassungsanwendung.

## Zu erstellende Komponenten

### 1. IRepository<T> Interface
```csharp
- Task<T> GetByIdAsync(int id)
- Task<IEnumerable<T>> GetAllAsync()
- Task<IEnumerable<T>> FindAsync(Expression<Func<T, bool>> predicate)
- Task<T> AddAsync(T entity)
- Task UpdateAsync(T entity)
- Task DeleteAsync(T entity)
- Task<int> SaveChangesAsync()
```

### 2. GenericRepository<T> Basisklasse
- Implementierung des IRepository Interface
- Include-Funktionalität für Eager Loading
- Pagination-Support
- Sortierung
- Async/Await durchgängig

### 3. Spezifische Repository-Interfaces
- IBenutzerRepository
- IArbeitszeitRepository  
- IStandortRepository
- IAenderungsprotokollRepository

### 4. Spezifische Repository-Implementierungen
Mit speziellen Methoden wie:
- GetBenutzerMitStammdatenAsync()
- GetArbeitszeitenFuerZeitraumAsync(DateTime von, DateTime bis)
- GetUngenehmigeAenderungenAsync()
- GetBenutzerNachStandortAsync(int standortId)

### 5. Unit of Work Pattern
- IUnitOfWork Interface
- UnitOfWork Implementierung
- Transaction-Support
- Repository-Factory

### 6. Offline-Repository Varianten
- Basis-Repository für SQLite
- Sync-Status Tracking
- Conflict Resolution Logic

## Spezifische Anforderungen
1. Generische Constraints verwenden (where T : class)
2. Dependency Injection vorbereiten
3. Logging-Integration
4. Exception Handling mit Custom Exceptions
5. Query-Optimization (No-Tracking für Read-Only)
6. Bulk-Operations Support

## Benötigte Dateien
- Entity-Modelle aus Schritt 1.2
- ApplicationDbContext
- OfflineDbContext

## Erwartete Ausgabe
Repository-Struktur:
```
DAL/
├── Interfaces/
│   ├── IRepository.cs
│   ├── IUnitOfWork.cs
│   ├── IBenutzerRepository.cs
│   ├── IArbeitszeitRepository.cs
│   ├── IStandortRepository.cs
│   └── IAenderungsprotokollRepository.cs
├── Repositories/
│   ├── GenericRepository.cs
│   ├── BenutzerRepository.cs
│   ├── ArbeitszeitRepository.cs
│   ├── StandortRepository.cs
│   ├── AenderungsprotokollRepository.cs
│   └── Offline/
│       ├── OfflineRepository.cs
│       └── SyncRepository.cs
└── UnitOfWork/
    └── UnitOfWork.cs
```

## Beispiel-Methoden
```csharp
// BenutzerRepository
Task<Benutzer> GetBenutzerByUsernameAsync(string username);
Task<IEnumerable<Benutzer>> GetBenutzerMitOffenenArbeitszeitenAsync();
Task<bool> IstBenutzerAktivAsync(int benutzerId);

// ArbeitszeitRepository  
Task<Arbeitszeit> GetAktuelleArbeitszeitAsync(int benutzerId);
Task<decimal> GetWochenarbeitszeitAsync(int benutzerId, DateTime woche);
Task<IEnumerable<Arbeitszeit>> GetUnsynchronisierteZeitenAsync();
```

## Hinweise
- Thread-Safe Implementation
- Dispose-Pattern korrekt implementieren
- ConfigureAwait(false) für Library-Code
- Prepared Statements für SQL Injection Schutz