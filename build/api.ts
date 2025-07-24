/**
 * Titel: API Service für Arbeitszeiterfassung (BFF-Architektur)
 * Version: 8.0 (FINAL & CORRECTED)
 * Letzte Aktualisierung: 24.07.2025
 * Autor: MP-IT
 * Status: Final
 * Datei: /api.ts
 * Beschreibung: Kapselt alle `fetch`-Aufrufe. Die Authentifizierung erfolgt nun über serverseitige HTTP-only Cookies. Die globale 401-Behandlung wurde korrigiert, um die Redirect-Schleife zu beheben.
 */
import type { TimeEntry, EntryChangeRequestPayload, MasterData, Role, GlobalSettings } from './src/types';

// Der Basispfad für alle PHP-API-Endpunkte.
const API_BASE_URL = '/api';

// === HILFSFUNKTION FÜR API-AUFRUFE ===
const fetchApi = async (endpoint: string, options: RequestInit & { isAuthCheck?: boolean } = {}) => {
    const headers = new Headers({ 'Content-Type': 'application/json' });
    if (options.headers) {
        new Headers(options.headers).forEach((value, key) => headers.set(key, value));
    }
    
    // WICHTIG: 'credentials: include' sorgt dafür, dass der Browser das Session-Cookie mitsendet.
    const fetchOptions: RequestInit = {
        ...options,
        headers,
        credentials: 'include' 
    };

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);

    const response = await fetch(`${API_BASE_URL}${endpoint}`, fetchOptions);
    
    clearTimeout(timeoutId);
    
    // Globale Behandlung von abgelaufenen/ungültigen Sessions
    // AUSNAHME: Wenn es der initiale Auth-Check ist, soll keine Weiterleitung erfolgen.
    if (response.status === 401 && !options.isAuthCheck) {
        // Die serverseitige Session ist ungültig. Leite den Benutzer zur Startseite (Login).
        window.location.href = '/'; 
        // Wirf einen Fehler, um die weitere Ausführung des aufrufenden Codes zu stoppen.
        throw new Error('Session expired or invalid.');
    }

    if (!response.ok) {
        const errorText = await response.text();
        
        if (controller.signal.aborted) {
            throw new Error('Die Anfrage hat zu lange gedauert und wurde abgebrochen (Timeout).');
        }

        try {
            const errorData = JSON.parse(errorText);
            throw new Error(errorData.message || `API-Fehler: ${response.status} ${response.statusText}`);
        } catch (e) {
            // Wenn die Antwort kein JSON ist oder der Fehler ein anderer ist, den ursprünglichen Fehler weiterwerfen
            if (e instanceof Error && (e.message.startsWith('API-Fehler:') || e.message.startsWith('Session expired'))) {
                 throw e;
            }
            console.error("Raw API Error Response:", errorText);
            throw new Error(`API-Fehler: ${response.status} ${response.statusText}. Server-Antwort: ${errorText.substring(0, 200)}...`);
        }
    }

    const contentType = response.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
        return response.text().then(text => text ? JSON.parse(text) : null);
    }
    // Für 204 No Content Antworten
    if (response.status === 204) {
        return null;
    }
    
    // Fallback für nicht-JSON Antworten
    return response.text();
};


// === API-Methoden ===
export const api = {
    checkAuthStatus: async () => {
        // Ein leichter Endpunkt, um zu prüfen, ob die Session gültig ist.
        // `isAuthCheck: true` verhindert den Redirect-Loop bei einem 401-Fehler.
        // Der Fehler wird stattdessen an App.tsx weitergegeben.
        await fetchApi('/auth-status.php', { method: 'GET', isAuthCheck: true });
    },

    logError: async (errorData: { message: string, stack?: string, context: string }) => {
        try {
            // Für das Logging ist keine Authentifizierung nötig.
            await fetch(`${API_BASE_URL}/logs.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(errorData),
            });
        } catch (e) {
            console.error("Fehler beim Senden des Fehlerprotokolls:", e);
        }
    },

    loginAndGetInitialData: async () => {
        // Dieser Aufruf funktioniert weiterhin, prüft aber nun die serverseitige Session.
        return fetchApi('/login.php', { method: 'POST' });
    },

    addTimeEntry: async (entryData: Omit<TimeEntry, 'id'>) => {
        return fetchApi('/time-entries.php', {
            method: 'POST',
            body: JSON.stringify(entryData),
        });
    },

    requestEntryChange: async (requestData: EntryChangeRequestPayload) => {
         return fetchApi('/approvals.php', {
            method: 'POST',
            body: JSON.stringify(requestData)
        });
    },

    updateMasterData: async (userId: number, data: MasterData) => {
        return fetchApi('/masterdata.php', {
            method: 'PUT',
            body: JSON.stringify({ userId, ...data })
        });
    },

    updateUserRole: async (userId: number, newRole: Role) => {
         return fetchApi('/users.php', {
            method: 'PATCH',
            body: JSON.stringify({ userId, newRole })
        });
    },

    processApprovalRequest: async (requestId: string, finalStatus: 'genehmigt' | 'abgelehnt') => {
        return fetchApi('/approvals.php', {
            method: 'PATCH',
            body: JSON.stringify({ requestId, finalStatus })
        });
    },

    updateGlobalSettings: async (settings: GlobalSettings) => {
        return fetchApi('/settings.php', {
            method: 'PUT',
            body: JSON.stringify(settings)
        });
    }
};