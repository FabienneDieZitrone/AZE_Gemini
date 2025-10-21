/**
 * Titel: Hauptansicht der Anwendung (BFF-Architektur)
 * Version: 3.0
 * Letzte Aktualisierung: 18.07.2025
 * Autor: MP-IT
 * Datei: /src/views/MainAppView.tsx
 * Beschreibung: Kernkomponente nach dem Login. MSAL-Logik wurde entfernt. Der Logout leitet an einen PHP-Endpunkt weiter, der die Session zerstört.
 */
import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { api } from '../../api';
import { Role, Theme, User, TimeEntry, ViewState, MasterData, ApprovalRequest, HistoryEntry, GlobalSettings, ReasonData } from '../types';
import { TimerService } from '../components/TimerService';
import { notificationService, Toaster } from '../services/NotificationService';
import { ErrorDisplay } from '../components/common/ErrorDisplay';
import '../components/common/ErrorDisplay.css';

import { calculateDurationInSeconds } from '../utils/time';
import { TIME } from '../constants';
import { Logo } from '../components/common/Logo';
import { LoadingSpinner } from '../components/common/LoadingSpinner';
import { ThemeToggle } from '../components/common/ThemeToggle';
import { ErrorDebugOverlay } from '../components/ErrorDebugOverlay';
import { EditEntryModal } from '../components/modals/EditEntryModal';
import { ConfirmDeleteModal } from '../components/modals/ConfirmDeleteModal';
import { RoleAssignmentModal } from '../components/modals/RoleAssignmentModal';
import { SupervisorNotificationModal } from '../components/modals/SupervisorNotificationModal';
import { NewEntryModal } from '../components/modals/NewEntryModal';
import { useSupervisorNotifications } from '../hooks/useSupervisorNotifications';

import { TimeSheetView } from './TimeSheetView';
import { MasterDataView } from './MasterDataView';
import { DayDetailView } from './DayDetailView';
import { ApprovalView } from './ApprovalView';
import { ChangeHistoryView } from './ChangeHistoryView';
import { DashboardView } from './DashboardView';
import { GlobalSettingsView } from './GlobalSettingsView';

export const MainAppView: React.FC = () => {
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [masterData, setMasterData] = useState<Record<number, MasterData>>({});
  const [timeEntries, setTimeEntries] = useState<TimeEntry[]>([]);
  const [approvalRequests, setApprovalRequests] = useState<ApprovalRequest[]>([]);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [globalSettings, setGlobalSettings] = useState<GlobalSettings | null>(null);
  
  const [viewState, setViewState] = useState<ViewState>({ current: 'main' });
  const [hasRunningTimer, setHasRunningTimer] = useState<boolean>(false);
  
  const [editingEntry, setEditingEntry] = useState<TimeEntry | null>(null);
  const [deletingEntry, setDeletingEntry] = useState<TimeEntry | null>(null);
  const [editingRoleForUser, setEditingRoleForUser] = useState<User | null>(null);
  const [requestNewEntryOpen, setRequestNewEntryOpen] = useState<boolean>(false);

  const [currentLocation, setCurrentLocation] = useState<string>('Zentrale Berlin');
  const [theme, setTheme] = useState<Theme>('light');
  
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  const initializeAndFetchData = useCallback(async () => {
    try {
        setIsLoading(true);
        setError(null);
        
        const initialData = await api.loginAndGetInitialData();
        
        setCurrentUser(initialData.currentUser);
        setUsers(initialData.users);
        setMasterData(initialData.masterData);
        setTimeEntries(initialData.timeEntries);
        setApprovalRequests(initialData.approvalRequests);
        setHistory(initialData.history);
        setGlobalSettings(initialData.globalSettings);
        if ((initialData as any).currentLocation) {
          setCurrentLocation((initialData as any).currentLocation);
        }
        
    } catch (err) {
        // Die Fehlerbehandlung (inkl. 401-Redirect) wird global in api.ts erledigt.
        // Hier loggen wir nur den Fehler oder zeigen eine Meldung an, falls der Prozess nicht unterbrochen wurde.
        const errorMessage = err instanceof Error ? err.message : 'Fehler beim Laden der Anwendungsdaten.';
        if (errorMessage !== 'Session expired or invalid.') {
            setError(errorMessage);
            api.logError({ message: errorMessage, stack: (err as Error).stack, context: 'initializeAndFetchData' });
        }
    } finally {
        setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    initializeAndFetchData();
  }, [initializeAndFetchData]);

  // Auto-refresh entries when navigating to the timesheet view
  useEffect(() => {
    if (viewState.current === 'timesheet') {
      refreshData();
    }
  }, [viewState.current]);

  // Beim Öffnen der Genehmigungsansicht: Pending-Genehmigungen separat laden
  useEffect(() => {
    const loadApprovals = async () => {
      if (viewState.current !== 'approvals') return;
      try {
        // Liste sofort leeren, um alte Login-Payload-Einträge zu entfernen
        setApprovalRequests([]);
        const pending = await api.getPendingApprovals();
        setApprovalRequests(pending || []);
      } catch (err) {
        // Kein harter Fehler – UI bleibt nutzbar
        const msg = err instanceof Error ? err.message : 'Fehler beim Laden der Genehmigungen.';
        setError(msg);
        api.logError({ message: msg, stack: (err as Error)?.stack, context: 'loadApprovals' });
      }
    };
    loadApprovals();
  }, [viewState.current]);

  // Timer callbacks
  const handleTimerStart = useCallback((_timerId: number) => {
    setHasRunningTimer(true);
    refreshData();
  }, []);

  const handleTimerStop = useCallback((_timerId: number) => {
    setHasRunningTimer(false);
    refreshData();
  }, []);


  const currentDate = useMemo(() => {
    const today = new Date();
    const options: Intl.DateTimeFormatOptions = { weekday: 'long', year: 'numeric', month: '2-digit', day: '2-digit' };
    return new Intl.DateTimeFormat('de-DE', options).format(today);
  }, []);

  const toggleTheme = () => {
      setTheme(prev => (prev === 'light' ? 'dark' : 'light'));
  };
  
  useEffect(() => {
    document.body.setAttribute('data-theme', theme);
  }, [theme]);
  
  // Make notificationService available globally for timer
  useEffect(() => {
    window.notificationService = notificationService;
  }, []);
  
  // Use the supervisor notifications hook
  const { 
    supervisorNotifications, 
    showSupervisorModal, 
    closeSupervisorModal 
  } = useSupervisorNotifications({
    currentUser,
    users,
    masterData,
    timeEntries,
    globalSettings
  });
  
  const refreshData = async () => {
    try {
        const initialData = await api.loginAndGetInitialData();
        setCurrentUser(initialData.currentUser);
        setUsers(initialData.users);
        setMasterData(initialData.masterData);
        setTimeEntries(initialData.timeEntries);
        // Wichtig: approvals werden separat über dedizierte Endpunkte geladen
        // und hier nicht überschrieben, damit Umschalter Ausstehend/Alle
        // und frische Pending-Einträge nicht durch den Login-Payload
        // wieder auf alte Werte zurückgesetzt werden.
        setHistory(initialData.history);
        setGlobalSettings(initialData.globalSettings);
        if ((initialData as any).currentLocation) {
          setCurrentLocation((initialData as any).currentLocation);
        }
    } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Aktualisieren der Daten.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'refreshData'});
    }
  }
  
  
  const handleLocalLinkClick = (e: React.MouseEvent, url: string) => {
    e.preventDefault();
    notificationService.localPathInfo(url);
  };

  const handleEditRequest = async (entry: TimeEntry, reasonData: ReasonData) => {
      if (!currentUser || !editingEntry) return;
      const requestData = {
        type: 'edit' as const,
        entryId: editingEntry.id,
        newData: { startTime: entry.startTime, stopTime: entry.stopTime },
        reasonData,
      };
      try {
        await api.requestEntryChange(requestData);
        // Lade Genehmigungen zusätzlich direkt
        try { setApprovalRequests(await api.getPendingApprovals()); } catch {}
        await refreshData();
        notificationService.success('Ihre Änderung wurde erfasst und wird zur Genehmigung weitergeleitet.');
        setEditingEntry(null);
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Beantragen der Änderung.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleEditRequest'});
      }
  };

  const handleDeleteRequest = async () => {
    if (deletingEntry && currentUser) {
      const requestData = { type: 'delete' as const, entryId: deletingEntry.id };
      try {
        await api.requestEntryChange(requestData);
        try { setApprovalRequests(await api.getPendingApprovals()); } catch {}
        await refreshData();
        setDeletingEntry(null);
      } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Beantragen der Löschung.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleDeleteRequest'});
      }
    }
  };

  const handleNewEntryRequest = async (data: { date: string; startTime: string; stopTime: string; location: string; role: Role; reasonData: { reason: string; details: string } }) => {
    try {
      await api.requestEntryChange({
        type: 'create',
        newData: {
          date: data.date,
          startTime: data.startTime,
          stopTime: data.stopTime,
          location: data.location,
          role: data.role,
          userId: currentUser?.id,
          username: currentUser?.name,
          updatedBy: currentUser?.name,
        },
        reasonData: data.reasonData,
      });
      setRequestNewEntryOpen(false);
      try { setApprovalRequests(await api.getPendingApprovals()); } catch {}
      await refreshData();
      notificationService.success('Neuer Zeiteintrag wurde zur Genehmigung eingereicht.');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Fehler beim Beantragen eines neuen Eintrags.';
      setError(msg);
      api.logError({message: msg, stack: (err as Error).stack, context: 'handleNewEntryRequest'});
    }
  }

  const handleMasterDataSave = async (userId: number, data: MasterData) => {
      try {
        await api.updateMasterData(userId, data);
        await refreshData();
        const userName = users.find(u=>u.id===userId)?.name || 'Benutzer';
        notificationService.success(`Stammdaten für ${userName} wurden gespeichert.`);
      } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Speichern der Stammdaten.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleMasterDataSave'});
      }
  };
  
  const handleRoleSave = async (userId: number, newRole: Role) => {
    try {
        // Close modal IMMEDIATELY for better UX (prevents showing stale role data)
        setEditingRoleForUser(null);

        await api.updateUserRole(userId, newRole);
        await refreshData();

        const userName = users.find(u=>u.id===userId)?.name || 'Benutzer';
        notificationService.success(`Rolle für ${userName} wurde auf ${newRole} geändert.`);
    } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Ändern der Rolle.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleRoleSave'});
    }
  };
  
  const processRequest = async (requestId: string, finalStatus: 'genehmigt' | 'abgelehnt') => {
      try {
        await api.processApprovalRequest(requestId, finalStatus);
        try { setApprovalRequests(await api.getPendingApprovals()); } catch {}
        await refreshData();
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Bearbeiten des Antrags.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'processRequest'});
      }
  };
  
  const handleGlobalSettingsSave = async (settings: GlobalSettings) => {
      try {
        await api.updateGlobalSettings(settings);
        await refreshData();
        notificationService.success('Globale Einstellungen gespeichert.');
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Speichern der Einstellungen.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleGlobalSettingsSave'});
      }
  };

  const calculatedOvertimeSeconds = useMemo(() => {
    if (!currentUser || !masterData[currentUser.id]) return 0;
    const md = masterData[currentUser.id] as any;

    // ✅ FIX: Defensive validation for masterData structure
    if (!md || typeof md !== 'object') {
      console.warn('[MainAppView] Invalid masterData for user:', currentUser.id);
      return 0;
    }

    const workdays: string[] = Array.isArray(md.workdays) ? md.workdays : [];
    const flexible: boolean = !!md.flexibleWorkdays;
    const dailyHours: Record<string, number> = md.dailyHours || {};

    const dayNameByIndex: Record<number, string> = { 0: 'So', 1: 'Mo', 2: 'Di', 3: 'Mi', 4: 'Do', 5: 'Fr', 6: 'Sa' };
    const workdaySet = new Set(workdays);

    const dailyTotals = timeEntries
      .filter(e => e.userId === currentUser.id)
      .reduce((acc, entry) => {
        const duration = calculateDurationInSeconds(entry.startTime, entry.stopTime);
        acc[entry.date] = (acc[entry.date] || 0) + duration;
        return acc;
      }, {} as Record<string, number>);

    let totalDifference = 0;
    for (const date in dailyTotals) {
      const d = new Date(date + 'T00:00:00');
      const dayName = dayNameByIndex[d.getDay()];
      const actual = dailyTotals[date];

      if (flexible) {
        // Bei flexibel gibt es keine feste Sollzeit pro Tag – alles zählt als geleistete Zeit
        totalDifference += actual;
        continue;
      }

      if (workdaySet.has(dayName)) {
        const sollHours = dailyHours?.[dayName] ?? (md.weeklyHours && workdays.length ? md.weeklyHours / workdays.length : 8);
        const soll = sollHours * TIME.SECONDS_PER_HOUR;
        totalDifference += (actual - soll);
      } else {
        // Nicht regulärer Arbeitstag → alles als Pluszeit zählen
        totalDifference += actual;
      }
    }
    return totalDifference;
  }, [timeEntries, currentUser, masterData]);

  const formattedOvertime = useMemo(() => {
      const totalHours = calculatedOvertimeSeconds / TIME.SECONDS_PER_HOUR;
      const sign = totalHours >= 0 ? '+' : '-';
      return `(${sign}${Math.abs(totalHours).toFixed(2)}h)`;
  }, [calculatedOvertimeSeconds]);

  const handleLogout = () => {
    if (hasRunningTimer) {
      const confirmLogout = window.confirm(
        'Sie haben eine laufende Zeiterfassung!\n\n' +
        'Die Zeit wird auf dem Server weiterverfolgt und kann beim nächsten Login fortgesetzt werden.\n\n' +
        'Möchten Sie abmelden?\n\n' +
        'Tipp: Sie können die Zeiterfassung auch vor dem Abmelden stoppen.'
      );
      
      if (!confirmLogout) {
        return; // User cancelled logout
      }
      
    }
    
    window.location.href = '/api/auth-logout.php';
  };
  
  const renderContent = () => {
    if (isLoading) return <LoadingSpinner />;
    if (error) return <ErrorDisplay error={{ message: error }} onRetry={initializeAndFetchData} />;
    if (!currentUser || !globalSettings) {
      return <ErrorDisplay
        error={{ message: error || "Keine Benutzerdaten geladen. Bitte laden Sie die Seite neu." }}
        onRetry={initializeAndFetchData}
      />;
    }
  
    const canSeeMasterData = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    const canApprove = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    const isAdmin = currentUser.role === 'Admin';
  
    switch (viewState.current) {
      case 'timesheet': return <TimeSheetView onBack={() => setViewState({ current: 'main'})} currentUser={currentUser} onShowDetails={(date, username) => setViewState({ current: 'daydetail', context: { date, username } })} timeEntries={timeEntries} masterData={masterData} approvalRequests={approvalRequests} allUsers={users} locations={globalSettings.locations}/>;
      case 'masterdata': return <MasterDataView onBack={() => setViewState({ current: 'main'})} masterData={masterData} users={users} currentUser={currentUser} onSave={handleMasterDataSave} onEditRole={(user) => setEditingRoleForUser(user)} locations={globalSettings.locations}/>;
      case 'daydetail': return <DayDetailView onBack={() => setViewState({ current: 'timesheet'})} onGoToMain={() => setViewState({ current: 'main' })} onShowHistory={() => setViewState({ current: 'changehistory', context: viewState.context })} date={viewState.context.date} username={viewState.context.username} userRole={currentUser.role} entries={timeEntries} approvalRequests={approvalRequests} onEdit={(entry) => setEditingEntry(entry)} onDelete={(entry) => setDeletingEntry(entry)}/>;
      case 'approvals': return <ApprovalView onBack={() => setViewState({ current: 'main' })} requests={approvalRequests} onApprove={(id) => processRequest(id, 'genehmigt')} onReject={(id) => processRequest(id, 'abgelehnt')} onLoadAll={async ()=>{ try { setApprovalRequests(await api.getAllApprovals()); } catch(e){} }} onLoadPending={async ()=>{ try { setApprovalRequests(await api.getPendingApprovals()); } catch(e){} }} />;
      case 'changehistory': return <ChangeHistoryView onBack={() => setViewState({ current: 'daydetail', context: viewState.context })} history={history} allUsers={users} locations={globalSettings.locations}/>;
      case 'dashboard': return <DashboardView onBack={() => setViewState({ current: 'main' })} timeEntries={timeEntries} users={users} currentUser={currentUser} locations={globalSettings.locations}/>;
      case 'globalsettings': return <GlobalSettingsView onBack={() => setViewState({ current: 'main' })} settings={globalSettings} onSave={handleGlobalSettingsSave}/>;
      case 'main':
      default:
        return (
          <>
            <header className="main-view-header" aria-live="polite">{`${currentUser.name} ${formattedOvertime} - ${currentDate}`}</header>
            <div className="location-display">Erkannter Standort: <strong>{currentLocation}</strong></div>
            <TimerService 
              currentUser={currentUser}
              onTimerStart={handleTimerStart}
              onTimerStop={handleTimerStop}
              onError={setError}
            />
            <nav className="nav-buttons" aria-label="Hauptnavigation">
              <button className="nav-button" onClick={() => setViewState({ current: 'timesheet' })}>Arbeitszeiten anzeigen</button>
              <button className="nav-button" onClick={() => setRequestNewEntryOpen(true)}>Zeit nachtragen</button>
              <button className="nav-button" onClick={() => setViewState({ current: 'dashboard' })}>Dashboard</button>
              {canSeeMasterData && (<button className="nav-button" onClick={() => setViewState({ current: 'masterdata' })}>Stammdaten</button>)}
              {canApprove && (<button className="nav-button" onClick={() => setViewState({ current: 'approvals' })}>Genehmigungen{approvalRequests.length > 0 && <span className="notification-badge">{approvalRequests.length}</span>}</button>)}
              {isAdmin && (<button className="nav-button" onClick={() => setViewState({ current: 'globalsettings' })}>Globale Einstellungen</button>)}
            </nav>
            <section className="links-container" aria-label="Nützliche Links"><div className="links-section">{links.map(link => (link.isLocal ? (<button key={link.name} onClick={(e) => handleLocalLinkClick(e, link.url)} className="link-button">{link.name}</button>) : (<a key={link.name} href={link.url} target="_blank" rel="noopener noreferrer" className="link-item">{link.name}</a>)))}</div></section>
            <div className="main-view-settings"><ThemeToggle theme={theme} toggleTheme={toggleTheme} /></div>
          </>
        );
    }
  };
  
  const links: {name: string, url: string, isLocal?: boolean}[] = [ { name: 'Jobrouter/Urlaubsworkflow', url: 'http://jobrouter.mikropartner.de' }, { name: 'Ticketsystem', url: 'http://ticket.mikropartner.de' }, { name: 'MPWeb 3.0', url: 'http://mpweb.mikropartner.de' }, { name: 'Verbis', url: 'https://jobboerse2.arbeitsagentur.de/verbis/login' }, { name: 'MP-Laufwerke verbinden', url: 'C:\\tools\\NetzLW.bat', isLocal: true }, { name: 'Aktuelle Telefonliste', url: 'O:\\Mikropartner_Allgemein\\Telefonliste _13_12_2024 Änderungen vorbehalten.pdf', isLocal: true }];

  return (
    <>
      <div className="app-container">
        <div className="app-header-bar">
          <Logo />
          <h1 className="app-main-title">MP Arbeitszeiterfassung</h1>
          {currentUser && <button onClick={handleLogout} className="logout-button">Abmelden</button>}
        </div>
        {renderContent()}
        {editingEntry && currentUser && globalSettings &&( <EditEntryModal entry={editingEntry} onClose={() => setEditingEntry(null)} onSave={handleEditRequest} changeReasons={globalSettings.changeReasons} currentUser={currentUser}/> )}
        {deletingEntry && ( <ConfirmDeleteModal onCancel={() => setDeletingEntry(null)} onConfirm={handleDeleteRequest}/> )}
        {requestNewEntryOpen && currentUser && globalSettings && (
          <NewEntryModal onClose={() => setRequestNewEntryOpen(false)} onSubmit={handleNewEntryRequest} locations={globalSettings.locations} defaultRole={currentUser.role} changeReasons={globalSettings.changeReasons} />
        )}
        {editingRoleForUser && currentUser && ( <RoleAssignmentModal user={editingRoleForUser} currentUser={currentUser} onClose={() => setEditingRoleForUser(null)} onSave={handleRoleSave}/> )}
        {showSupervisorModal && ( <SupervisorNotificationModal notifications={supervisorNotifications} onClose={closeSupervisorModal}/> )}
      </div>
      <Toaster position="top-right" />
      <ErrorDebugOverlay />
    </>
  );
};
