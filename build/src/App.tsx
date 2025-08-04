/**
 * Titel: Haupt-Wrapper der Anwendung (BFF-Architektur)
 * Version: 2.0
 * Letzte Aktualisierung: 10.11.2024
 * Autor: MP-IT
 * Status: Final
 * Datei: /src/App.tsx
 * Beschreibung: Stellt nicht mehr den MSAL-Kontext bereit. Prüft stattdessen den serverseitigen Session-Status beim Laden der App, um zu entscheiden, ob die Login-Seite oder die Hauptanwendung angezeigt wird.
 */
import React, { useState, useEffect } from 'react';
import { LoadingSpinner } from './components/common/LoadingSpinner';
import { SignInPage } from './views/SignInPage';
import { MainAppView } from './views/MainAppView';
import { api } from '../api';
import ErrorBoundary from './components/ErrorBoundary';

const App: React.FC = () => {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null); // null = loading

  useEffect(() => {
    const checkSession = async () => {
      try {
        // Prüft die serverseitige Session mit einem leichten API-Aufruf.
        // `fetchApi` wird bei einem 401-Fehler (keine Session) einen Fehler auslösen.
        await api.checkAuthStatus();
        setIsAuthenticated(true);
      } catch {
        // Der Benutzer ist nicht authentifiziert.
        setIsAuthenticated(false);
      }
    };
    checkSession();
  }, []);

  // Während der Prüfung des Auth-Status wird ein Lade-Spinner angezeigt.
  if (isAuthenticated === null) {
    return (
      <div className="app-container">
        <LoadingSpinner />
      </div>
    );
  }

  // Je nach Authentifizierungsstatus die entsprechende Ansicht rendern.
  return (
    <ErrorBoundary>
      {isAuthenticated 
        ? <MainAppView /> 
        : <div className="app-container"><SignInPage /></div>}
    </ErrorBoundary>
  );
};

export default App;