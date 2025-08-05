# Datenbankbackup über HostEurope Webinterface einrichten

## Beschreibung
Da bei HostEurope keine direkten Skripte auf dem Webserver ausgeführt werden können, muss das Datenbankbackup über das HostEurope Webinterface konfiguriert werden.

## Aufgaben
- [ ] Login ins HostEurope KIS (Kunden-Informations-System)
- [ ] MySQL-Datenbank-Backup einrichten
- [ ] Automatische Backup-Routine konfigurieren (täglich)
- [ ] Backup-Retention festlegen (mindestens 7 Tage)
- [ ] Test-Restore durchführen zur Verifizierung

## Hinweise
Die vorbereiteten Backup-Skripte im Repository können als Referenz für lokale Entwicklungsumgebungen genutzt werden, sind aber nicht für den HostEurope Produktionsserver geeignet.

## Priorität
**KRITISCH** - Ohne Backup besteht das Risiko eines vollständigen Datenverlusts

## Labels
- security
- critical
- infrastructure