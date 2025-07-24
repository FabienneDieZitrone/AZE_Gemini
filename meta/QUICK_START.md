---
title: Quick Start Guide - Arbeitszeiterfassung
description: Schnelleinstieg für neue Chat-Sessions zur Fortsetzung der Entwicklung
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Anleitung
---

# Quick Start Guide: Arbeitszeiterfassung

## 🚀 Sofort loslegen in 3 Schritten

### Schritt 1: Kontext laden
```
Ich arbeite an einer Arbeitszeiterfassungsanwendung.
Die Projektdateien befinden sich in /app/AZE/
Bitte lies zuerst die ZENTRALE_ANWEISUNGSDATEI.md für alle Details.
```

### Schritt 2: Status prüfen
```bash
cd /app/AZE
ls -la
cat ZENTRALE_ANWEISUNGSDATEI.md
```

### Schritt 3: Nächsten Entwicklungsschritt ausführen
```
Der nächste Schritt ist 1.1 - Projekt-Setup.
Der Prompt dafür ist in: Prompts/Schritt_1_1_Projekt_Setup.md
```

## 📋 Checkliste für neue Session

- [ ] ZENTRALE_ANWEISUNGSDATEI.md gelesen
- [ ] Arbeitsplan zur Kenntnis genommen
- [ ] Aktuellen Schritt identifiziert
- [ ] Entsprechenden Prompt geöffnet
- [ ] Entwicklungsumgebung bereit
- [ ] `meta/test-projekt.sh` auf dem Zielsystem ausgeführt und Ergebnis notiert

## 🎯 Direkt-Prompts für häufige Aufgaben

### Projekt-Setup starten:
```
Erstelle die Projektstruktur gemäß /app/AZE/Prompts/Schritt_1_1_Projekt_Setup.md
```

### Datenbank-Modelle erstellen:
```
Implementiere die Entity-Modelle gemäß /app/AZE/Prompts/Schritt_1_2_Datenbankdesign.md
```

### Repository-Pattern:
```
Erstelle das Repository-Pattern gemäß /app/AZE/Prompts/Schritt_2_1_Repository_Pattern.md
```

## 🔧 Wichtige Befehle

```bash
# Zum Projektverzeichnis
cd /app/AZE

# Übersicht verschaffen
find . -name "*.md" -type f

# Nächsten Schritt anzeigen
cat Zusammenfassung_Arbeitsplan.md | grep "Nächster Schritt"

# Alle Prompts auflisten
ls -1 Prompts/
```

## 📁 Verzeichnisstruktur

```
/app/AZE/
├── ZENTRALE_ANWEISUNGSDATEI.md ← START HIER!
├── Arbeitsplan_Arbeitszeiterfassung.md
├── PROJEKT_ZUSAMMENFASSUNG.md
├── QUICK_START.md (diese Datei)
├── Prompts/
│   ├── Schritt_1_1_Projekt_Setup.md ← AKTUELL
│   ├── Schritt_1_2_Datenbankdesign.md
│   └── ... (weitere Schritte)
└── Arbeitszeiterfassung/ ← WIRD ERSTELLT
```

## ⚡ Schnell-Info

- **Projekt**: Arbeitszeiterfassung für Bildungsträger
- **Tech-Stack**: .NET 8.0, C# 12.0, Windows Forms
- **Aktueller Schritt**: 1.1 - Projekt-Setup
- **Geschätzte Zeit**: 30 Minuten
- **Nächster Schritt**: 1.2 - Datenbankdesign

## 🆘 Hilfe

```bash
# Bei Problemen:
cat /app/AZE/ZENTRALE_ANWEISUNGSDATEI.md | grep -A5 "Wichtige"

# Arbeitsplan einsehen:
cat /app/AZE/Arbeitsplan_Arbeitszeiterfassung.md

# Bewertung lesen:
cat /app/AZE/Arbeitsplan_Bewertung.md
```

## Schrittabschluss

Ein Entwicklungsschritt gilt erst als abgeschlossen, wenn er auf deinem Zielsystem erfolgreich getestet wurde. Führe dazu `meta/test-projekt.sh` (Linux/WSL) oder `build-windows.cmd` (Windows) aus und sende mir die Konsolenausgabe als Bestätigung. Zusätzlich erfolgt nach jedem Schritt eine Selbstbewertung von 1-10. Nur bei einer Bewertung von 10 ohne erkennbare Verbesserungspotentiale wird der Schritt endgültig abgeschlossen.
**Tipp**: Beginne immer mit dem Lesen der ZENTRALE_ANWEISUNGSDATEI.md!