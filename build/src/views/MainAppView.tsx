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
import { Role, Theme, User, TimeEntry, ViewState, MasterData, ApprovalRequest, HistoryEntry, SupervisorNotification, GlobalSettings, ReasonData } from '../types';

import { getStartOfWeek, formatTime, calculateDurationInSeconds } from '../utils/time';
import { Logo } from '../components/common/Logo';
import { LoadingSpinner } from '../components/common/LoadingSpinner';
import { ThemeToggle } from '../components/common/ThemeToggle';
import { EditEntryModal } from '../components/modals/EditEntryModal';
import { ConfirmDeleteModal } from '../components/modals/ConfirmDeleteModal';
import { RoleAssignmentModal } from '../components/modals/RoleAssignmentModal';
import { SupervisorNotificationModal } from '../components/modals/SupervisorNotificationModal';

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
  const [isTracking, setIsTracking] = useState<boolean>(false);
  const [elapsedTime, setElapsedTime] = useState<number>(0);
  const [activeTimerStartTime, setActiveTimerStartTime] = useState<number | null>(null);
  const [currentTimerId, setCurrentTimerId] = useState<number | null>(null);
  
  const [editingEntry, setEditingEntry] = useState<TimeEntry | null>(null);
  const [deletingEntry, setDeletingEntry] = useState<TimeEntry | null>(null);
  const [editingRoleForUser, setEditingRoleForUser] = useState<User | null>(null);

  const [supervisorNotifications, setSupervisorNotifications] = useState<SupervisorNotification[]>([]);
  const [showSupervisorModal, setShowSupervisorModal] = useState(false);
  const [currentLocation] = useState('Zentrale Berlin');
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

  // SERVER-FIRST: Check for running timer on server after login
  useEffect(() => {
    if (currentUser) {
      checkForRunningTimer();
    }
  }, [currentUser]);

  const checkForRunningTimer = async () => {
    try {
      // Check for incomplete entries (stop_time = NULL)
      const response = await fetch('/api/time-entries.php?action=check_running', {
        method: 'GET',
        credentials: 'include'
      });
      
      if (response.ok) {
        const data = await response.json();
        if (data.hasRunningTimer && data.runningTimer) {
          const startTime = new Date(`${data.runningTimer.date}T${data.runningTimer.startTime}`).getTime();
          setActiveTimerStartTime(startTime);
          setIsTracking(true);
          setCurrentTimerId(data.runningTimer.id);
          // Don't set elapsedTime here - it will be calculated by the effect
          console.log('Running timer found on server:', data.runningTimer);
        }
      }
    } catch (error) {
      console.warn('Failed to check for running timer:', error);
    }
  };


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
  
  useEffect(() => {
    let interval: ReturnType<typeof setInterval> | null = null;
    let reminderTimeout: ReturnType<typeof setTimeout> | null = null;
    if (isTracking && activeTimerStartTime) {
      interval = setInterval(() => {
        setElapsedTime(Math.floor((Date.now() - activeTimerStartTime) / 1000));
      }, 1000);
      
      reminderTimeout = setTimeout(() => {
        if(isTracking){
            alert("Erinnerung: Die Zeiterfassung läuft noch. Haben Sie vergessen, auf 'Stop' zu klicken?");
        }
      }, 8 * 60 * 60 * 1000); // 8 hours reminder
    }
    return () => { 
        if (interval) clearInterval(interval); 
        if (reminderTimeout) clearTimeout(reminderTimeout);
    };
  }, [isTracking, activeTimerStartTime]);
  
  useEffect(() => {
    if (!currentUser || !globalSettings || !masterData || timeEntries.length === 0) return;
    const canCheck = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    if (!canCheck) return;

    const notifications: SupervisorNotification[] = [];
    const thresholdSeconds = globalSettings.overtimeThreshold * 3600;
    
    const lastWeekStart = getStartOfWeek(new Date(new Date().setDate(new Date().getDate() - 7)));
    const lastWeekEnd = new Date(lastWeekStart);
    lastWeekEnd.setDate(lastWeekStart.getDate() + 6);

    const subordinates = users.filter(u => u.id !== currentUser.id);

    subordinates.forEach(user => {
      const userMaster = masterData[user.id];
      if (!userMaster) return;

      const weeklySollSeconds = userMaster.weeklyHours * 3600;
      const userEntriesLastWeek = timeEntries.filter(e => {
        return e.userId === user.id && e.date >= lastWeekStart.toISOString().split('T')[0] && e.date <= lastWeekEnd.toISOString().split('T')[0];
      });
      
      const totalSecondsWorked = userEntriesLastWeek.reduce((sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime), 0);
      const deviationSeconds = totalSecondsWorked - weeklySollSeconds;
      
      if(Math.abs(deviationSeconds) > thresholdSeconds) {
          notifications.push({
              employeeName: user.name,
              deviationHours: deviationSeconds / 3600,
          });
      }
    });

    if(notifications.length > 0) {
        setSupervisorNotifications(notifications);
        setShowSupervisorModal(true);
    }
  }, [currentUser, users, masterData, timeEntries, globalSettings]);
  
  const refreshData = async () => {
    try {
        const initialData = await api.loginAndGetInitialData();
        setCurrentUser(initialData.currentUser);
        setUsers(initialData.users);
        setMasterData(initialData.masterData);
        setTimeEntries(initialData.timeEntries);
        setApprovalRequests(initialData.approvalRequests);
        setHistory(initialData.history);
        setGlobalSettings(initialData.globalSettings);
    } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Aktualisieren der Daten.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'refreshData'});
    }
  }
  
  const handleToggleTracking = async () => {
    if (!currentUser) return;

    try {
      if (isTracking) {
        // WORKAROUND: Use POST with action=stop instead of PUT (Apache blocks PUT)
        if (!currentTimerId) {
          throw new Error('No timer ID available');
        }
        
        const now = new Date();
        const stopTime = now.toTimeString().split(' ')[0]; // HH:MM:SS format
        
        const response = await fetch('/api/time-entries.php?action=stop', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ 
            id: currentTimerId,
            stopTime: stopTime,
            updatedBy: currentUser.name
          })
        });

        if (response.ok) {
          console.log('Timer stopped on server via time-entries.php');
          
          // Update local state
          setIsTracking(false);
          setActiveTimerStartTime(null);
          setCurrentTimerId(null);
          setElapsedTime(0);
          
          // Reload time entries to show completed entry
          await refreshData();
        } else {
          throw new Error('Failed to stop timer on server');
        }
      } else {
        // QUICK-FIX: Start timer using time-entries.php POST method with NULL stop_time
        const now = new Date();
        const startTime = now.toTimeString().split(' ')[0]; // HH:MM:SS format
        const date = now.toISOString().split('T')[0]; // YYYY-MM-DD format
        
        const response = await fetch('/api/time-entries.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ 
            userId: currentUser.id,
            username: currentUser.name,
            date: date,
            startTime: startTime,
            stopTime: null, // DB allows NULL for running timers
            location: currentLocation,
            role: currentUser.role,
            updatedBy: currentUser.name
          })
        });

        if (response.ok) {
          const data = await response.json();
          console.log('Timer started on server via time-entries.php:', data);
          
          // Update local state
          const startTimeMs = new Date(`${date}T${startTime}`).getTime();
          setActiveTimerStartTime(startTimeMs);
          setIsTracking(true);
          setCurrentTimerId(data.id);
          setElapsedTime(0);
        } else {
          throw new Error('Failed to start timer on server');
        }
      }
    } catch (err) {
      const action = isTracking ? 'Stoppen' : 'Starten';
      const msg = `Fehler beim ${action} der Zeiterfassung: ${(err as Error).message}`;
      setError(msg);
      api.logError({
        message: msg, 
        stack: (err as Error).stack, 
        context: `handleToggleTracking - ${action.toLowerCase()}`
      });
    }
  };
  
  const handleLocalLinkClick = (e: React.MouseEvent, url: string) => {
    e.preventDefault();
    alert(`In der finalen Anwendung würde nun der lokale Pfad geöffnet:\n${url}\n\nDies ist in einem Web-Browser aus Sicherheitsgründen nicht möglich.`);
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
        await refreshData();
        alert('Ihre Änderung wurde erfasst und wird zur Genehmigung weitergeleitet.');
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
        await refreshData();
        setDeletingEntry(null);
      } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Beantragen der Löschung.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleDeleteRequest'});
      }
    }
  };

  const handleMasterDataSave = async (userId: number, data: MasterData) => {
      try {
        await api.updateMasterData(userId, data);
        await refreshData();
        alert(`Stammdaten für ${users.find(u=>u.id===userId)?.name} wurden gespeichert.`);
      } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Speichern der Stammdaten.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleMasterDataSave'});
      }
  };
  
  const handleRoleSave = async (userId: number, newRole: Role) => {
    try {
        await api.updateUserRole(userId, newRole);
        await refreshData();
        alert(`Rolle für ${users.find(u=>u.id===userId)?.name} wurde auf ${newRole} geändert.`);
    } catch(err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Ändern der Rolle.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleRoleSave'});
    }
  };
  
  const processRequest = async (requestId: string, finalStatus: 'genehmigt' | 'abgelehnt') => {
      try {
        await api.processApprovalRequest(requestId, finalStatus);
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
        alert('Globale Einstellungen gespeichert.');
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Fehler beim Speichern der Einstellungen.';
        setError(msg);
        api.logError({message: msg, stack: (err as Error).stack, context: 'handleGlobalSettingsSave'});
      }
  };

  const calculatedOvertimeSeconds = useMemo(() => {
    if (!currentUser || !masterData[currentUser.id]) return 0;
    const userMasterData = masterData[currentUser.id];
    if (!userMasterData || userMasterData.workdays.length === 0) return 0;

    const dailySollSeconds = (userMasterData.weeklyHours / userMasterData.workdays.length) * 3600;

    const dayMap: { [key: string]: number } = { 'Mo': 1, 'Di': 2, 'Mi': 3, 'Do': 4, 'Fr': 5, 'Sa': 6, 'So': 0 };
    const workdaysSet = new Set(userMasterData.workdays.map(d => dayMap[d as keyof typeof dayMap]));

    const dailyTotals = timeEntries
        .filter(e => e.userId === currentUser.id)
        .reduce((acc, entry) => {
            const duration = calculateDurationInSeconds(entry.startTime, entry.stopTime);
            acc[entry.date] = (acc[entry.date] || 0) + duration;
            return acc;
        }, {} as Record<string, number>);

    let totalDifference = 0;
    for (const date in dailyTotals) {
        const d = new Date(date + "T00:00:00");
        const dayOfWeek = d.getDay();
        
        if (workdaysSet.has(dayOfWeek)) {
            totalDifference += (dailyTotals[date] - dailySollSeconds);
        } else {
            totalDifference += dailyTotals[date];
        }
    }
    return totalDifference;
  }, [timeEntries, currentUser, masterData]);

  const formattedOvertime = useMemo(() => {
      const totalHours = calculatedOvertimeSeconds / 3600;
      const sign = totalHours >= 0 ? '+' : '-';
      return `(${sign}${Math.abs(totalHours).toFixed(2)}h)`;
  }, [calculatedOvertimeSeconds]);

  const handleLogout = () => {
    // SERVER-FIRST: Check for running timer and inform user
    if (isTracking && activeTimerStartTime) {
      const confirmLogout = window.confirm(
        'Sie haben eine laufende Zeiterfassung!\n\n' +
        'Die Zeit wird auf dem Server weiterverfolgt und kann beim nächsten Login fortgesetzt werden.\n\n' +
        'Möchten Sie abmelden?\n\n' +
        'Tipp: Sie können die Zeiterfassung auch vor dem Abmelden stoppen.'
      );
      
      if (!confirmLogout) {
        return; // User cancelled logout
      }
      
      console.log('Logout with running timer - timer continues on server');
    }
    
    window.location.href = '/api/auth-logout.php';
  };
  
  const renderContent = () => {
    if (isLoading) return <LoadingSpinner />;
    if (error) return <div className="error-message full-page-error">{error}</div>;
    if (!currentUser || !globalSettings) return <LoadingSpinner />;
  
    const canSeeMasterData = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    const canApprove = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    const isAdmin = currentUser.role === 'Admin';
  
    switch (viewState.current) {
      case 'timesheet': return <TimeSheetView onBack={() => setViewState({ current: 'main'})} currentUser={currentUser} onShowDetails={(date, username) => setViewState({ current: 'daydetail', context: { date, username } })} timeEntries={timeEntries} masterData={masterData} approvalRequests={approvalRequests} allUsers={users} locations={globalSettings.locations}/>;
      case 'masterdata': return <MasterDataView onBack={() => setViewState({ current: 'main'})} masterData={masterData} users={users} currentUser={currentUser} onSave={handleMasterDataSave} onEditRole={(user) => setEditingRoleForUser(user)} locations={globalSettings.locations}/>;
      case 'daydetail': return <DayDetailView onBack={() => setViewState({ current: 'timesheet'})} onGoToMain={() => setViewState({ current: 'main' })} onShowHistory={() => setViewState({ current: 'changehistory', context: viewState.context })} date={viewState.context.date} username={viewState.context.username} userRole={currentUser.role} entries={timeEntries} approvalRequests={approvalRequests} onEdit={(entry) => setEditingEntry(entry)} onDelete={(entry) => setDeletingEntry(entry)}/>;
      case 'approvals': return <ApprovalView onBack={() => setViewState({ current: 'main' })} requests={approvalRequests} onApprove={(id) => processRequest(id, 'genehmigt')} onReject={(id) => processRequest(id, 'abgelehnt')}/>;
      case 'changehistory': return <ChangeHistoryView onBack={() => setViewState({ current: 'daydetail', context: viewState.context })} history={history} allUsers={users} locations={globalSettings.locations}/>;
      case 'dashboard': return <DashboardView onBack={() => setViewState({ current: 'main' })} timeEntries={timeEntries} users={users} currentUser={currentUser} locations={globalSettings.locations}/>;
      case 'globalsettings': return <GlobalSettingsView onBack={() => setViewState({ current: 'main' })} settings={globalSettings} onSave={handleGlobalSettingsSave}/>;
      case 'main':
      default:
        return (
          <>
            <header className="main-view-header" aria-live="polite">{`${currentUser.name} ${formattedOvertime} - ${currentDate}`}</header>
            <div className="location-display">Erkannter Standort: <strong>{currentLocation}</strong></div>
            <section className="tracking-section" aria-label="Zeiterfassung"><div className="label">Zeiterfassung starten / stoppen</div><div className="tracking-controls"><button onClick={handleToggleTracking} className={`toggle-button ${isTracking ? 'stop-button' : 'start-button'}`} aria-live="polite">{isTracking ? 'Stop' : 'Start'}</button>{isTracking && (<div className="timer-display" aria-label="Abgelaufene Zeit">{formatTime(elapsedTime, true)}</div>)}</div></section>
            <nav className="nav-buttons" aria-label="Hauptnavigation">
              <button className="nav-button" onClick={() => setViewState({ current: 'timesheet' })}>Arbeitszeiten anzeigen</button>
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
    <div className="app-container">
      <div className="app-header-bar">
        <Logo />
        <h1 className="app-main-title">MP Arbeitszeiterfassung</h1>
        {currentUser && <button onClick={handleLogout} className="logout-button">Abmelden</button>}
      </div>
      {renderContent()}
      {editingEntry && currentUser && globalSettings &&( <EditEntryModal entry={editingEntry} onClose={() => setEditingEntry(null)} onSave={handleEditRequest} changeReasons={globalSettings.changeReasons} currentUser={currentUser}/> )}
      {deletingEntry && ( <ConfirmDeleteModal onCancel={() => setDeletingEntry(null)} onConfirm={handleDeleteRequest}/> )}
      {editingRoleForUser && currentUser && ( <RoleAssignmentModal user={editingRoleForUser} currentUser={currentUser} onClose={() => setEditingRoleForUser(null)} onSave={handleRoleSave}/> )}
      {showSupervisorModal && ( <SupervisorNotificationModal notifications={supervisorNotifications} onClose={() => setShowSupervisorModal(false)}/> )}
    </div>
  );
};