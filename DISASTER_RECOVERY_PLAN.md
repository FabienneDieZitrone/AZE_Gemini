# Disaster Recovery Plan - AZE_Gemini
## Version 1.0 - 2025-08-04

### 1. ÜBERSICHT
Dieser Plan definiert die Verfahren zur Wiederherstellung der AZE_Gemini Zeiterfassungsanwendung nach einem katastrophalen Ausfall.

### 2. KRITISCHE KOMPONENTEN
- **Datenbank**: MySQL mit Zeiterfassungsdaten
- **Anwendung**: PHP Backend + React Frontend
- **Authentifizierung**: Azure AD Integration
- **Datenspeicher**: Benutzer-, Zeit- und Genehmigungsdaten

### 3. BACKUP-STRATEGIE

#### 3.1 Automatisierte Backups
```bash
# Tägliches Backup-Script (cron)
0 2 * * * /app/AZE_Gemini/scripts/daily-backup.sh
```

#### 3.2 Backup-Komponenten
- Datenbank-Dump (täglich)
- Anwendungscode (Git)
- Konfigurationsdateien
- Logs (wöchentlich)

### 4. RECOVERY TIME OBJECTIVES (RTO)
- **Kritische Funktionen**: 2 Stunden
- **Vollständige Wiederherstellung**: 4 Stunden
- **Datenverlust (RPO)**: Maximal 24 Stunden

### 5. WIEDERHERSTELLUNGSVERFAHREN

#### 5.1 Sofortmaßnahmen (0-30 Minuten)
1. Incident Response Team aktivieren
2. Ausfallursache identifizieren
3. Stakeholder informieren

#### 5.2 Wiederherstellung (30-120 Minuten)
1. **Datenbank wiederherstellen**:
   ```bash
   mysql -u root -p aze_zeiterfassung < /backups/latest.sql
   ```

2. **Anwendung deployen**:
   ```bash
   git clone https://github.com/FabienneDieZitrone/AZE_Gemini.git
   cd AZE_Gemini
   ./deploy.sh
   ```

3. **Konfiguration wiederherstellen**:
   ```bash
   cp /backups/config/* /app/AZE_Gemini/config/
   ```

#### 5.3 Validierung (120-240 Minuten)
1. Systemtests durchführen
2. Datenintegrität prüfen
3. Benutzerauthentifizierung testen
4. Monitoring aktivieren

### 6. FAILOVER-SZENARIEN

#### 6.1 Datenbank-Ausfall
- Primär: Restore von Backup
- Sekundär: Read-Replica aktivieren

#### 6.2 Anwendungs-Ausfall
- Primär: Neustart der Services
- Sekundär: Deployment auf Backup-Server

#### 6.3 Komplettausfall
- Aktivierung des DR-Standorts
- DNS-Umleitung
- Vollständige Wiederherstellung

### 7. KONTAKTE

| Rolle | Name | Kontakt | Verantwortung |
|-------|------|---------|---------------|
| DR-Koordinator | TBD | TBD | Gesamtkoordination |
| DBA | TBD | TBD | Datenbank-Recovery |
| DevOps | TBD | TBD | Infrastruktur |
| Kommunikation | TBD | TBD | Stakeholder-Info |

### 8. TESTING

#### 8.1 Test-Schedule
- Monatlich: Backup-Validierung
- Quartalsweise: Teilweise DR-Übung
- Jährlich: Vollständige DR-Simulation

#### 8.2 Test-Dokumentation
- Test-Datum
- Durchgeführte Schritte
- Identifizierte Probleme
- Verbesserungsmaßnahmen

### 9. MAINTENANCE

Dieser Plan wird quartalsweise überprüft und bei Bedarf aktualisiert.

**Letzte Überprüfung**: 2025-08-04
**Nächste Überprüfung**: 2025-11-04