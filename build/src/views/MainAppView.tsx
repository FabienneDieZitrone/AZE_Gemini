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
import { OvertimeBreakdownModal } from '../components/modals/OvertimeBreakdownModal';
import { useSupervisorNotifications } from '../hooks/useSupervisorNotifications';

import { TimeSheetView } from './TimeSheetView';
import { MasterDataView } from './MasterDataView';
import { DayDetailView } from './DayDetailView';
import { ApprovalView } from './ApprovalView';
import { ChangeHistoryView } from './ChangeHistoryView';
import { DashboardView } from './DashboardView';
import { GlobalSettingsView } from './GlobalSettingsView';
import { OnboardingView } from './OnboardingView';

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
  const [showOvertimeBreakdown, setShowOvertimeBreakdown] = useState<boolean>(false);

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
    pendingOnboardingUsers,
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
        console.log('[refreshData] START - Calling api.loginAndGetInitialData()');
        const initialData = await api.loginAndGetInitialData();
        console.log('[refreshData] API response received, users count:', initialData.users?.length);
        console.log('[refreshData] User data sample:', initialData.users?.slice(0, 3));

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
        console.log('[refreshData] State updates completed');
    } catch(err) {
        console.error('[refreshData] ERROR:', err);
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

        // Check if it's a validation error from backend
        if (msg.includes('VALIDATION_ERROR') || msg.includes('Summe der täglichen Stunden')) {
          // Extract the user-friendly error message from the backend response
          const match = msg.match(/Die Summe der täglichen Stunden[^!]+!/);
          const validationMsg = match ? match[0] : 'Die Summe der täglichen Stunden muss der regelmäßigen Wochenarbeitszeit entsprechen!';
          notificationService.error(validationMsg);
        } else {
          setError(msg);
        }
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleMasterDataSave'});
      }
  };
  
  const handleRoleSave = async (userId: number, newRole: Role) => {
    try {
        console.log('[handleRoleSave] START', { userId, newRole, currentUsers: users.length });

        // Close modal IMMEDIATELY for better UX (prevents showing stale role data)
        setEditingRoleForUser(null);
        console.log('[handleRoleSave] Modal closed');

        await api.updateUserRole(userId, newRole);
        console.log('[handleRoleSave] API call successful');

        const usersBefore = users.find(u => u.id === userId);
        console.log('[handleRoleSave] User BEFORE refreshData:', usersBefore);

        await refreshData();
        console.log('[handleRoleSave] refreshData() completed');

        const usersAfter = users.find(u => u.id === userId);
        console.log('[handleRoleSave] User AFTER refreshData:', usersAfter);

        const userName = users.find(u=>u.id===userId)?.name || 'Benutzer';
        notificationService.success(`Rolle für ${userName} wurde auf ${newRole} geändert.`);
        console.log('[handleRoleSave] SUCCESS - Toast shown');
    } catch(err) {
        console.error('[handleRoleSave] ERROR:', err);
        const msg = err instanceof Error ? err.message : 'Fehler beim Ändern der Rolle.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleRoleSave'});
    }
  };

  const handleDeleteUser = async (userId: number) => {
    try {
      const userName = users.find(u => u.id === userId)?.name || 'Benutzer';

      await api.deleteUser(userId);
      await refreshData();

      notificationService.success(`Benutzer ${userName} wurde erfolgreich gelöscht.`);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Fehler beim Löschen des Benutzers.';
      setError(msg);
      api.logError({message: msg, stack: (err as Error).stack, context: 'handleDeleteUser'});
      throw err; // Re-throw so the UI can handle it
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

  const handleOnboardingComplete = async (homeLocation: string) => {
    try {
      await api.completeOnboarding(homeLocation);
      await refreshData();
      notificationService.success('Onboarding abgeschlossen! Sie können nun Ihre Arbeitszeit erfassen.');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Fehler beim Abschließen des Onboardings.';
      throw new Error(msg);
    }
  };

  const handleNavigateToMasterData = () => {
    closeSupervisorModal();
    setViewState({ current: 'masterdata' });
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
        // Bei flexiblen Arbeitstagen: Berechne durchschnittliche Sollzeit basierend auf Wochenstunden
        if (workdaySet.has(dayName)) {
          const sollHours = md.weeklyHours && workdays.length ? md.weeklyHours / workdays.length : 8;
          const soll = sollHours * TIME.SECONDS_PER_HOUR;
          totalDifference += (actual - soll);
        } else {
          // Nicht regulärer Arbeitstag → alles als Pluszeit zählen
          totalDifference += actual;
        }
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

    // Onboarding-Check: Neue Mitarbeiter müssen zuerst Heimat-Standort wählen
    if (currentUser.needsOnboarding) {
      return (
        <OnboardingView
          globalSettings={globalSettings}
          userName={currentUser.name}
          onComplete={handleOnboardingComplete}
        />
      );
    }
  
    const canSeeMasterData = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    const canApprove = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    const isAdmin = currentUser.role === 'Admin';
  
    switch (viewState.current) {
      case 'timesheet': return <TimeSheetView onBack={() => setViewState({ current: 'main'})} currentUser={currentUser} onShowDetails={(date, username) => setViewState({ current: 'daydetail', context: { date, username } })} timeEntries={timeEntries} masterData={masterData} approvalRequests={approvalRequests} allUsers={users} locations={globalSettings.locations}/>;
      case 'masterdata': return <MasterDataView onBack={() => setViewState({ current: 'main'})} masterData={masterData} users={users} currentUser={currentUser} onSave={handleMasterDataSave} onEditRole={(user) => setEditingRoleForUser(user)} onDeleteUser={handleDeleteUser} locations={globalSettings.locations}/>;
      case 'daydetail': return <DayDetailView onBack={() => setViewState({ current: 'timesheet'})} onGoToMain={() => setViewState({ current: 'main' })} onShowHistory={() => setViewState({ current: 'changehistory', context: viewState.context })} date={viewState.context.date} username={viewState.context.username} userRole={currentUser.role} entries={timeEntries} approvalRequests={approvalRequests} onEdit={(entry) => setEditingEntry(entry)} onDelete={(entry) => setDeletingEntry(entry)} allUsers={users}/>;
      case 'approvals': return <ApprovalView onBack={() => setViewState({ current: 'main' })} requests={approvalRequests} onApprove={(id) => processRequest(id, 'genehmigt')} onReject={(id) => processRequest(id, 'abgelehnt')} onLoadAll={async ()=>{ try { setApprovalRequests(await api.getAllApprovals()); } catch(e){} }} onLoadPending={async ()=>{ try { setApprovalRequests(await api.getPendingApprovals()); } catch(e){} }} />;
      case 'changehistory': return <ChangeHistoryView onBack={() => setViewState({ current: 'daydetail', context: viewState.context })} history={history} allUsers={users} locations={globalSettings.locations}/>;
      case 'dashboard': return <DashboardView onBack={() => setViewState({ current: 'main' })} timeEntries={timeEntries} users={users} currentUser={currentUser} locations={globalSettings.locations}/>;
      case 'globalsettings': return <GlobalSettingsView onBack={() => setViewState({ current: 'main' })} settings={globalSettings} onSave={handleGlobalSettingsSave}/>;
      case 'main':
      default:
        return (
          <>
            <header className="main-view-header" aria-live="polite">
              {currentUser.name}{' '}
              <span
                className="overtime-display-clickable"
                onClick={() => setShowOvertimeBreakdown(true)}
                title="Klicken für detaillierte Übersicht"
              >
                {formattedOvertime}
              </span>
              {' '}- {currentDate}
            </header>
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
          </>
        );
    }
  };
  
  const links: {name: string, url: string, isLocal?: boolean}[] = [ { name: 'Jobrouter/Urlaubsworkflow', url: 'http://jobrouter.mikropartner.de' }, { name: 'Ticketsystem', url: 'http://ticket.mikropartner.de' }, { name: 'MPWeb 3.0', url: 'http://mpweb.mikropartner.de' }, { name: 'Verbis', url: 'https://jobboerse2.arbeitsagentur.de/verbis/login' }, { name: 'PDF', url: 'http://pdf.mikropartner.de' }, { name: 'MP-Laufwerke verbinden', url: 'C:\\tools\\NetzLW.bat', isLocal: true }, { name: 'Aktuelle Telefonliste', url: 'O:\\Mikropartner_Allgemein\\Telefonliste _13_12_2024 Änderungen vorbehalten.pdf', isLocal: true }];

  return (
    <>
      <div className="app-container">
        <div className="app-header-bar">
          <Logo />
          <h1 className="app-main-title">Arbeitszeiterfassung</h1>
          <ThemeToggle theme={theme} toggleTheme={toggleTheme} />
          {currentUser && <button onClick={handleLogout} className="logout-button">Abmelden</button>}
        </div>
        {renderContent()}
        {editingEntry && currentUser && globalSettings &&( <EditEntryModal entry={editingEntry} onClose={() => setEditingEntry(null)} onSave={handleEditRequest} changeReasons={globalSettings.changeReasons} currentUser={currentUser}/> )}
        {deletingEntry && ( <ConfirmDeleteModal onCancel={() => setDeletingEntry(null)} onConfirm={handleDeleteRequest}/> )}
        {requestNewEntryOpen && currentUser && globalSettings && (
          <NewEntryModal onClose={() => setRequestNewEntryOpen(false)} onSubmit={handleNewEntryRequest} locations={globalSettings.locations} defaultRole={currentUser.role} changeReasons={globalSettings.changeReasons} />
        )}
        {editingRoleForUser && currentUser && ( <RoleAssignmentModal user={editingRoleForUser} currentUser={currentUser} onClose={() => setEditingRoleForUser(null)} onSave={handleRoleSave}/> )}
        {showSupervisorModal && ( <SupervisorNotificationModal notifications={supervisorNotifications} pendingOnboardingUsers={pendingOnboardingUsers} onClose={closeSupervisorModal} onNavigateToMasterData={handleNavigateToMasterData}/> )}
        {showOvertimeBreakdown && currentUser && masterData[currentUser.id] && (
          <OvertimeBreakdownModal
            timeEntries={timeEntries}
            userId={currentUser.id}
            masterData={masterData[currentUser.id]}
            onClose={() => setShowOvertimeBreakdown(false)}
          />
        )}
        <footer className="app-footer">
          <div className="footer-content">
            <div className="footer-logo-header">
              <img
                src="/logo-white.png"
                alt="MIKRO PARTNER Logo"
                className="app-logo-img"
              />
            </div>
            <div className="footer-divider"></div>
            <div className="footer-columns-container">
              <div className="footer-column">
                <h3 className="footer-heading">KONTAKT</h3>
                <p className="footer-text">Montag bis Freitag: 09:00 - 15:00 Uhr</p>
                <p className="footer-text">Telefon: 0800-0060057</p>
                <p className="footer-text">E-Mail: kundenbetreuung@mikropartner.de</p>
              </div>
              <div className="footer-column">
                <h3 className="footer-heading">LINK</h3>
                <a href="https://www.mikropartner.de" target="_blank" rel="noopener noreferrer" className="footer-link">Willkommen</a>
                <div className="footer-dropdown">
                  <a href="https://www.mikropartner.de/angebote/" target="_blank" rel="noopener noreferrer" className="footer-link footer-dropdown-trigger">Angebote</a>
                  <div className="footer-dropdown-menu">
                    <a href="https://www.mikropartner.de/angebote/6-richtige/" target="_blank" rel="noopener noreferrer" className="footer-dropdown-item">6 Richtige</a>
                    <a href="https://www.mikropartner.de/angebote/einzelcoaching-im-modulsystem/" target="_blank" rel="noopener noreferrer" className="footer-dropdown-item">Einzelcoaching im Modulsystem</a>
                    <a href="https://www.mikropartner.de/angebote/bewerbungscoaching-4-0-mit-ki/" target="_blank" rel="noopener noreferrer" className="footer-dropdown-item">Bewerbungscoaching 4.0 mit KI</a>
                    <a href="https://www.mikropartner.de/angebote/fachkraft-reinigungsgewerbe/" target="_blank" rel="noopener noreferrer" className="footer-dropdown-item">Fachkraft Reinigungsgewerbe</a>
                  </div>
                </div>
                <a href="https://www.mikropartner.de/uber-uns/" target="_blank" rel="noopener noreferrer" className="footer-link">Über uns</a>
                <a href="https://www.mikropartner.de/standorte/" target="_blank" rel="noopener noreferrer" className="footer-link">Standorte</a>
                <a href="https://www.mikropartner.de/kontakt/" target="_blank" rel="noopener noreferrer" className="footer-link">Kontakt</a>
                <a href="https://www.mikropartner.de/mediathek/" target="_blank" rel="noopener noreferrer" className="footer-link">Mediathek</a>
              </div>
              <div className="footer-column">
                <a href="https://www.mikropartner.de/standorte/" target="_blank" rel="noopener noreferrer" className="footer-heading-link">STANDORTE</a>
                <a href="https://www.mikropartner.de/zertifizierung" target="_blank" rel="noopener noreferrer" className="footer-heading-link">ZERTIFIZIERUNGEN</a>
                <div className="footer-social">
                  <a href="https://www.instagram.com/mikropartner/" target="_blank" rel="noopener noreferrer" className="footer-social-link" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                  </a>
                  <a href="https://www.facebook.com/mikropartner" target="_blank" rel="noopener noreferrer" className="footer-social-link" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                  </a>
                  <a href="https://www.youtube.com/user/mikropartner" target="_blank" rel="noopener noreferrer" className="footer-social-link" aria-label="YouTube">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                  </a>
                  <a href="https://www.linkedin.com/company/mikro-partner" target="_blank" rel="noopener noreferrer" className="footer-social-link" aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div className="footer-bottom">
            <div className="footer-legal-links">
              <a href="https://www.mikropartner.de/impressum/" target="_blank" rel="noopener noreferrer">Impressum</a>
              <span>|</span>
              <a href="https://www.mikropartner.de/ds" target="_blank" rel="noopener noreferrer">Datenschutz</a>
              <span>|</span>
              <a href="https://www.mikropartner.de/hinweisgeberschutz/" target="_blank" rel="noopener noreferrer">Hinweisgeberschutz</a>
            </div>
            <div className="footer-copyright">© {new Date().getFullYear()} MIKRO PARTNER</div>
          </div>
        </footer>
      </div>
      <Toaster position="top-right" />
      <ErrorDebugOverlay />
    </>
  );
};
