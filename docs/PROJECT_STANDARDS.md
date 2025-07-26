# 📋 **AZE Projektstandards**

## **Refactoring als Standard:**

### **Nach jedem Feature PFLICHT:**
- Code-Review und systematische Cleanup
- Komplexität reduzieren wo möglich (max. 15 Zeilen pro Funktion)
- Dokumentation im Code aktualisieren
- TypeScript-Typen optimieren
- Unused Imports entfernen

### **Refactoring-Checkliste:**
- [ ] **DRY-Prinzip**: Duplikate eliminiert?
- [ ] **Single Responsibility**: Eine Aufgabe pro Funktion/Komponente?
- [ ] **Magic Numbers**: Konstanten definiert?
- [ ] **Error Handling**: Alle Edge-Cases abgedeckt?
- [ ] **Performance**: Unnötige Re-Renders vermieden?

## **Testing-Mindeststandard:**

### **Vor jedem Commit PFLICHT:**
```bash
npm test                    # Unit Tests (>90% Coverage)
npm run build              # Production Build ohne Errors
npm run typecheck          # TypeScript strict Mode
```

### **Test-Kategorien:**
- **Unit Tests**: Business-Logic, Utils, Hooks
- **Integration Tests**: API-Endpoints, Database-Queries  
- **Component Tests**: User-Interactions, Error-States
- **E2E Tests**: Kritische User-Flows (Login, Zeiterfassung)

## **Projektphilosophie:**

### **Open Source First:**
- Immer Open Source Alternative bevorzugen
- Proprietäre Software nur wenn technisch notwendig
- Community-Contributions zurückgeben wo möglich

### **Single Source of Truth:**
- CLAUDE.local.md als Master-Dokumentation
- @imports für modulare Details
- GitHub Issues ↔ lokale Dokumentation synchron
- Keine Duplikate in verschiedenen Dateien

### **Problem-Solving-Ansatz:**
1. **Ist-Zustand analysieren** (nie blind ändern)
2. **Root-Cause identifizieren** (nicht Symptome)
3. **Architektur-Lösung** vor Quick-Fix
4. **Tests schreiben** vor Implementation
5. **User-Feedback** vor finaler Freigabe

## **Persistenz & Robustheit:**

### **System-Restart-Test:**
- Web-Server neu starten → App funktional?
- Database-Connection nach Neustart?
- Session-Handling nach Browser-Reload?
- Environment-Variables korrekt geladen?

### **Cross-Component-Impact:**
- Welche anderen Komponenten betroffen?
- API-Kompatibilität gewährleistet?
- Database-Schema-Änderungen dokumentiert?
- Frontend ↔ Backend Sync intakt?