/**
 * Titel: Typ-Definitionen für die Arbeitszeiterfassung
 * Version: 1.1
 * Letzte Aktualisierung: 18.07.2025
 * Autor: MP-IT
 * Status: Final
 * Datei: /src/types.ts
 * Beschreibung: Zentralisiert alle wiederverwendbaren TypeScript-Typen für die Anwendung. `User` wurde um `azureOid` erweitert.
 */

// === BASIS-TYPEN ===
export type Role = 'Admin' | 'Bereichsleiter' | 'Standortleiter' | 'Mitarbeiter' | 'Honorarkraft';
export type Theme = 'light' | 'dark';

export type User = {
    id: number;
    name: string;
    role: Role;
    azureOid?: string; // Eindeutige Object ID von Azure AD
};

export type TimeEntry = {
    id: number;
    userId: number;
    username: string;
    date: string; // YYYY-MM-DD
    startTime: string; // HH:mm:ss
    stopTime: string; // HH:mm:ss
    location: string;
    role: Role;
    createdAt: string; // ISO String
    updatedBy: string;
    updatedAt: string; // ISO String
    isUnsynced?: boolean;
};

export type AggregatedTimeEntry = TimeEntry & {
    totalSeconds: number;
    firstStart: string;
    lastStop: string;
    pauseSeconds: number;
};

export type ViewState = {
  current: 'main' | 'timesheet' | 'masterdata' | 'daydetail' | 'approvals' | 'changehistory' | 'dashboard' | 'globalsettings';
  context?: any;
};

export type EditFormData = {
    startTime: string; // ISO String
    stopTime: string; // ISO String
    reason: string;
    reasonDetails: string;
};

export type MasterData = {
  weeklyHours: number;
  workdays: string[];
  canWorkFromHome: boolean;
};

export type ReasonData = {
    reason: string;
    details: string;
};

export type EntryChangeRequestPayload = {
    type: 'edit' | 'delete';
    entryId: number;
    newData?: { startTime: string; stopTime: string; };
    reasonData?: ReasonData;
};

export type ApprovalRequest = {
    id: string; // UUID
    type: 'edit' | 'delete';
    entry: TimeEntry;
    newData?: Partial<TimeEntry>;
    reasonData?: ReasonData;
    requestedBy: string;
    status: 'pending';
};

export type HistoryEntry = Omit<ApprovalRequest, 'status'> & {
    finalStatus: 'genehmigt' | 'abgelehnt';
    resolvedAt: string; // ISO String
    resolvedBy: string;
};

export type SupervisorNotification = {
    employeeName: string;
    deviationHours: number;
};

export type GlobalSettings = {
    overtimeThreshold: number;
    changeReasons: string[];
    locations: string[];
};