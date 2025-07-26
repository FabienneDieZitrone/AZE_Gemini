# 🛠️ **Entwicklungsrichtlinien**

## **Security-First (Issue #19):**
```bash
# NIEMALS Credentials im Code!
# .env Datei verwenden
# Input-Validation IMMER
# SQL-Injection Prevention
# XSS-Protection aktivieren
```

## **React/TypeScript:**
- Funktionale Komponenten mit Hooks verwenden
- Strikte TypeScript-Typisierung
- Props-Interfaces definieren
- Custom Hooks für Logic-Wiederverwendung

## **PHP Standards:**
- PSR-12 Coding Style
- Prepared Statements für DB-Queries
- Strukturierte JSON-Responses
- Error-Logging implementieren

## **Git Workflow:**
```bash
# Standard Workflow
git add .
git commit -m "Beschreibung der Änderungen"
git push origin main

# Mit Token (bereits konfiguriert)
# Remote: https://FabienneDieZitrone:TOKEN@github.com/FabienneDieZitrone/AZE_Gemini.git
```