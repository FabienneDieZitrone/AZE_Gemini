---
title: Prompt für Schritt 3.3 - Rollenbasierte Zugriffskontrolle
description: Detaillierter Prompt zur Implementierung des RBAC-Systems
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 3.3: Rollenbasierte Zugriffskontrolle (RBAC)

## Aufgabe
Implementiere ein vollständiges rollenbasiertes Zugriffskontrollsystem für die Arbeitszeiterfassungsanwendung mit 5 Benutzerrollen und hierarchischen Berechtigungen.

## Zu erstellende Komponenten

### 1. IAuthorizationService Interface
```csharp
public interface IAuthorizationService
{
    Task<bool> HasPermissionAsync(int benutzerId, Permission permission);
    Task<bool> CanAccessDataAsync(int benutzerId, int targetUserId);
    Task<bool> CanAccessStandortAsync(int benutzerId, int standortId);
    Task<bool> CanEditArbeitszeitAsync(int benutzerId, int arbeitszeitId);
    Task<bool> CanApproveChangesAsync(int benutzerId, int targetUserId);
    Task<IEnumerable<Permission>> GetUserPermissionsAsync(int benutzerId);
}
```

### 2. Permission Enum
```csharp
[Flags]
public enum Permission
{
    None = 0,
    
    // Basis-Berechtigungen
    ViewOwnData = 1 << 0,
    EditOwnData = 1 << 1,
    
    // Erweiterte Berechtigungen
    ViewAllStandortData = 1 << 2,
    ViewAllData = 1 << 3,
    EditAllStandortData = 1 << 4,
    EditAllData = 1 << 5,
    
    // Administrative Berechtigungen
    ManageUsers = 1 << 6,
    ManageStandorte = 1 << 7,
    ManageRoles = 1 << 8,
    ManageSystem = 1 << 9,
    
    // Genehmigungs-Berechtigungen
    ApproveStandortChanges = 1 << 10,
    ApproveAllChanges = 1 << 11,
    
    // Spezial-Berechtigungen
    ViewReports = 1 << 12,
    ExportData = 1 << 13,
    ViewAuditLog = 1 << 14,
    DeleteData = 1 << 15
}
```

### 3. RolePermissionMapping.cs
```csharp
public static class RolePermissionMapping
{
    private static readonly Dictionary<Berechtigungsstufe, Permission> RolePermissions = 
        new Dictionary<Berechtigungsstufe, Permission>
    {
        [Berechtigungsstufe.Mitarbeiter] = 
            Permission.ViewOwnData | 
            Permission.EditOwnData,
            
        [Berechtigungsstufe.Honorarkraft] = 
            Permission.ViewOwnData | 
            Permission.EditOwnData,
            
        [Berechtigungsstufe.Standortleiter] = 
            Permission.ViewOwnData | 
            Permission.EditOwnData |
            Permission.ViewAllStandortData |
            Permission.EditAllStandortData |
            Permission.ApproveStandortChanges |
            Permission.ViewReports |
            Permission.ExportData,
            
        [Berechtigungsstufe.Bereichsleiter] = 
            Permission.ViewOwnData | 
            Permission.EditOwnData |
            Permission.ViewAllData |
            Permission.EditAllData |
            Permission.ApproveAllChanges |
            Permission.ManageUsers |
            Permission.ManageStandorte |
            Permission.ViewReports |
            Permission.ExportData |
            Permission.ViewAuditLog,
            
        [Berechtigungsstufe.Admin] = 
            (Permission)(-1) // Alle Berechtigungen
    };
}
```

### 4. AuthorizationService Implementierung
```csharp
public class AuthorizationService : IAuthorizationService
{
    private readonly IBenutzerRepository _benutzerRepository;
    private readonly ISessionManager _sessionManager;
    private readonly ICacheService _cache;
    
    public async Task<bool> HasPermissionAsync(int benutzerId, Permission permission)
    {
        // Cache-Check
        var cacheKey = $"permissions_{benutzerId}";
        if (_cache.TryGet<Permission>(cacheKey, out var cached))
            return cached.HasFlag(permission);
            
        // Benutzer und Rolle laden
        var benutzer = await _benutzerRepository.GetBenutzerMitRolleAsync(benutzerId);
        if (benutzer == null || !benutzer.Aktiv)
            return false;
            
        // Berechtigungen ermitteln
        var permissions = RolePermissionMapping.GetPermissions(benutzer.Rolle.Berechtigungsstufe);
        
        // Cache für 5 Minuten
        _cache.Set(cacheKey, permissions, TimeSpan.FromMinutes(5));
        
        return permissions.HasFlag(permission);
    }
}
```

### 5. AuthorizationPolicyBuilder.cs
```csharp
public class AuthorizationPolicyBuilder
{
    public AuthorizationPolicy BuildPolicy(string policyName)
    {
        return policyName switch
        {
            "ViewOwnData" => new AuthorizationPolicy(Permission.ViewOwnData),
            "EditOwnData" => new AuthorizationPolicy(Permission.EditOwnData),
            "ManageStandort" => new AuthorizationPolicy(
                Permission.ViewAllStandortData | 
                Permission.EditAllStandortData
            ),
            "ApproveChanges" => new AuthorizationPolicy(
                Permission.ApproveStandortChanges | 
                Permission.ApproveAllChanges
            ),
            "AdminOnly" => new AuthorizationPolicy(Permission.ManageSystem),
            _ => throw new ArgumentException($"Unknown policy: {policyName}")
        };
    }
}
```

### 6. DataAccessValidator.cs
```csharp
public class DataAccessValidator : IDataAccessValidator
{
    public async Task<bool> CanAccessUserDataAsync(
        Benutzer accessor, 
        Benutzer target)
    {
        // Eigene Daten immer zugänglich
        if (accessor.BenutzerID == target.BenutzerID)
            return true;
            
        // Admin kann alles
        if (accessor.Rolle.Berechtigungsstufe == Berechtigungsstufe.Admin)
            return true;
            
        // Bereichsleiter kann alle Daten sehen
        if (accessor.Rolle.Berechtigungsstufe == Berechtigungsstufe.Bereichsleiter)
            return true;
            
        // Standortleiter kann nur eigene Standorte
        if (accessor.Rolle.Berechtigungsstufe == Berechtigungsstufe.Standortleiter)
        {
            return await HabenGemeinsamenStandort(accessor, target);
        }
        
        return false;
    }
}
```

### 7. AuthorizationAttribute.cs
```csharp
[AttributeUsage(AttributeTargets.Method | AttributeTargets.Class)]
public class RequirePermissionAttribute : Attribute
{
    public Permission RequiredPermission { get; }
    
    public RequirePermissionAttribute(Permission permission)
    {
        RequiredPermission = permission;
    }
}

// Verwendung:
[RequirePermission(Permission.ManageUsers)]
public async Task<IEnumerable<Benutzer>> GetAllUsersAsync()
{
    // ...
}
```

### 8. Hierarchische Zugriffsprüfung

```csharp
public class HierarchicalAccessControl
{
    public async Task<IEnumerable<int>> GetAccessibleUserIdsAsync(int benutzerId)
    {
        var benutzer = await _benutzerRepository.GetBenutzerMitDetailsAsync(benutzerId);
        
        switch (benutzer.Rolle.Berechtigungsstufe)
        {
            case Berechtigungsstufe.Admin:
            case Berechtigungsstufe.Bereichsleiter:
                // Alle Benutzer
                return await _benutzerRepository.GetAllUserIdsAsync();
                
            case Berechtigungsstufe.Standortleiter:
                // Benutzer der zugewiesenen Standorte
                var standortIds = benutzer.BenutzerStandorte
                    .Select(bs => bs.StandortID);
                return await _benutzerRepository
                    .GetUserIdsByStandorteAsync(standortIds);
                
            default:
                // Nur eigene ID
                return new[] { benutzerId };
        }
    }
}
```

## Spezielle Berechtigungslogik

### 1. Rollenvergabe-Regeln
```csharp
public class RollenVergabeRegeln
{
    public bool KannRolleVergeben(
        Berechtigungsstufe vergebenderRolle, 
        Berechtigungsstufe zuVergebendeRolle)
    {
        return vergebenderRolle switch
        {
            Berechtigungsstufe.Admin => true, // Kann alle Rollen vergeben
            Berechtigungsstufe.Bereichsleiter => 
                zuVergebendeRolle < Berechtigungsstufe.Admin,
            Berechtigungsstufe.Standortleiter => 
                zuVergebendeRolle <= Berechtigungsstufe.Honorarkraft,
            _ => false
        };
    }
}
```

### 2. Genehmigungs-Hierarchie
```csharp
public class GenehmigungsHierarchie
{
    public async Task<Benutzer> GetGenehmiger(Benutzer mitarbeiter)
    {
        // Standortleiter des Hauptstandorts
        var hauptstandort = mitarbeiter.BenutzerStandorte
            .FirstOrDefault(bs => bs.IstHauptstandort);
            
        if (hauptstandort != null)
        {
            return await _benutzerRepository
                .GetStandortleiterAsync(hauptstandort.StandortID);
        }
        
        // Fallback: Bereichsleiter
        return await _benutzerRepository.GetBereichsleiterAsync();
    }
}
```

### 3. Audit-Trail für Berechtigungen
```csharp
public class BerechtigungsAudit
{
    public async Task LogPermissionCheckAsync(
        int benutzerId, 
        Permission permission, 
        bool granted,
        string context)
    {
        var auditEntry = new BerechtigungsAuditLog
        {
            BenutzerID = benutzerId,
            Permission = permission.ToString(),
            Granted = granted,
            Context = context,
            Timestamp = DateTime.Now,
            SessionID = _sessionManager.CurrentSessionId
        };
        
        await _auditRepository.AddAsync(auditEntry);
    }
}
```

## UI-Integration

### 1. AuthorizationHelper für Forms
```csharp
public class UIAuthorizationHelper
{
    public void ConfigureControlsForUser(Form form, Benutzer user)
    {
        // Buttons basierend auf Berechtigungen ein/ausblenden
        var btnStammdaten = form.Controls.Find("btnStammdaten", true).FirstOrDefault();
        if (btnStammdaten != null)
        {
            btnStammdaten.Visible = user.Rolle.Berechtigungsstufe >= 
                Berechtigungsstufe.Standortleiter;
        }
        
        // Menüpunkte konfigurieren
        ConfigureMenuForRole(form.MainMenuStrip, user.Rolle);
    }
}
```

### 2. Datenfilterung
```csharp
public class DataFilterService
{
    public IQueryable<Arbeitszeit> FilterArbeitszeitenForUser(
        IQueryable<Arbeitszeit> query, 
        Benutzer user)
    {
        return user.Rolle.Berechtigungsstufe switch
        {
            Berechtigungsstufe.Admin or 
            Berechtigungsstufe.Bereichsleiter => query,
            
            Berechtigungsstufe.Standortleiter => query.Where(a => 
                user.BenutzerStandorte.Any(bs => 
                    bs.StandortID == a.StandortID)),
                    
            _ => query.Where(a => a.BenutzerID == user.BenutzerID)
        };
    }
}
```

## Benötigte Dateien
- Entity-Modelle aus Schritt 1.2
- Repository-Klassen aus Schritt 2.1
- SessionManager aus Schritt 3.1

## Erwartete Ausgabe
```
BLL/
├── Authorization/
│   ├── AuthorizationService.cs
│   ├── AuthorizationPolicyBuilder.cs
│   ├── DataAccessValidator.cs
│   ├── HierarchicalAccessControl.cs
│   ├── RollenVergabeRegeln.cs
│   └── GenehmigungsHierarchie.cs
├── Models/
│   ├── AuthorizationPolicy.cs
│   ├── BerechtigungsAuditLog.cs
│   └── PermissionCheck.cs
├── Attributes/
│   └── RequirePermissionAttribute.cs
├── Helpers/
│   └── UIAuthorizationHelper.cs
└── Interfaces/
    ├── IAuthorizationService.cs
    └── IDataAccessValidator.cs
Common/
└── Enums/
    └── Permission.cs
```

## Hinweise
- Cache Berechtigungen für Performance
- Thread-Safe Implementation
- Keine hartcodierten Berechtigungen
- Flexible Erweiterbarkeit für neue Rollen
- Audit-Trail für alle Zugriffsprüfungen