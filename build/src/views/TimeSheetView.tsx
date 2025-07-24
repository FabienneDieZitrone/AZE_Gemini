/**
 * Titel: Arbeitszeitenübersicht (Timesheet)
 * Version: 1.1
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/TimeSheetView.tsx
 */
import React, { useState, useEffect, useMemo } from 'react';
import { User, TimeEntry, AggregatedTimeEntry, MasterData, ApprovalRequest } from '../types';
import { getStartOfWeek, formatTime } from '../utils/time';
import { exportToCsv, exportToPdf } from '../utils/export';
import { aggregateTimeEntries } from '../utils/aggregate';

export const TimeSheetView: React.FC<{
    onBack: () => void;
    currentUser: User;
    onShowDetails: (date: string, username: string) => void;
    timeEntries: TimeEntry[];
    masterData: Record<number, MasterData>;
    approvalRequests: ApprovalRequest[];
    allUsers: User[];
    locations: string[];
}> = ({ onBack, currentUser, onShowDetails, timeEntries, masterData, approvalRequests, allUsers, locations }) => {
  const [filters, setFilters] = useState({
    zeitraum: 'diese-woche',
    standort: 'Alle Standorte',
    benutzer: String(currentUser.id),
  });
  
  const [filteredEntries, setFilteredEntries] = useState<AggregatedTimeEntry[]>([]);
  
  const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setFilters(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };
  
  useEffect(() => {
      let entries = timeEntries;
      
      const now = new Date();
      let startDate: Date | null = null;
      let endDate: Date | null = null;

      switch(filters.zeitraum) {
          case 'diese-woche':
              startDate = getStartOfWeek(now);
              endDate = new Date(startDate);
              endDate.setDate(startDate.getDate() + 6);
              break;
          case 'letzte-woche':
              const lastWeek = new Date();
              lastWeek.setDate(now.getDate() - 7);
              startDate = getStartOfWeek(lastWeek);
              endDate = new Date(startDate);
              endDate.setDate(startDate.getDate() + 6);
              break;
          case 'dieser-monat':
              startDate = new Date(now.getFullYear(), now.getMonth(), 1);
              endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
              break;
          case 'letzte-3-monate':
              endDate = new Date();
              startDate = new Date(now.getFullYear(), now.getMonth() - 3, now.getDate());
              break;
          case 'letzte-6-monate':
              endDate = new Date();
              startDate = new Date(now.getFullYear(), now.getMonth() - 6, now.getDate());
              break;
          case 'dieses-jahr':
              startDate = new Date(now.getFullYear(), 0, 1);
              endDate = new Date(now.getFullYear(), 11, 31);
              break;
          case 'letztes-jahr':
              startDate = new Date(now.getFullYear() - 1, 0, 1);
              endDate = new Date(now.getFullYear() - 1, 11, 31);
              break;
          case 'alle':
              break;
      }
      
      if(startDate && endDate) {
          const startDateStr = startDate.toISOString().split('T')[0];
          const endDateStr = endDate.toISOString().split('T')[0];
          entries = entries.filter(e => e.date >= startDateStr && e.date <= endDateStr);
      }

      if (filters.standort !== 'Alle Standorte') {
          entries = entries.filter(e => e.location === filters.standort);
      }
      if (filters.benutzer !== 'Alle Benutzer') {
          entries = entries.filter(e => e.userId === Number(filters.benutzer));
      }
      
      const pendingDeletionIds = new Set(approvalRequests.filter(r => r.type === 'delete').map(r => r.entry.id));
      const entriesForAggregation = entries.filter(e => !pendingDeletionIds.has(e.id));
      
      setFilteredEntries(aggregateTimeEntries(entriesForAggregation));
  }, [timeEntries, approvalRequests, filters]);
  
  const weeklyTotalSeconds = useMemo(() => {
    return filteredEntries.reduce((sum, entry) => sum + entry.totalSeconds, 0);
  }, [filteredEntries]);
  
  const selectedUserMasterData = masterData[Number(filters.benutzer)];

  return (
    <div className="view-container">
      <header className="view-header">
        <h2>Arbeitszeitenübersicht</h2>
      </header>
      
      <div className="timesheet-controls">
        <div className="filters-container">
          <div className="filter-group">
            <label htmlFor="zeitraum-filter">Zeitraum wählen</label>
            <select id="zeitraum-filter" name="zeitraum" value={filters.zeitraum} onChange={handleFilterChange}>
              <option value="diese-woche">Diese Woche</option>
              <option value="letzte-woche">Letzte Woche</option>
              <option value="dieser-monat">Dieser Monat</option>
              <option value="letzte-3-monate">Letzte 3 Monate</option>
              <option value="letzte-6-monate">Letzte 6 Monate</option>
              <option value="dieses-jahr">Dieses Jahr</option>
              <option value="letztes-jahr">Letztes Jahr</option>
              <option value="alle">Alle Arbeitszeiten</option>
            </select>
          </div>
          <div className="filter-group">
            <label htmlFor="standort-filter">Standort</label>
            <select id="standort-filter" name="standort" value={filters.standort} onChange={handleFilterChange}>
              <option>Alle Standorte</option>
              {locations.map(loc => <option key={loc}>{loc}</option>)}
            </select>
          </div>
          <div className="filter-group">
            <label htmlFor="benutzer-filter">Benutzer</label>
            <select id="benutzer-filter" name="benutzer" value={filters.benutzer} onChange={handleFilterChange}>
              <option value={String(currentUser.id)}>{currentUser.name} (Ich)</option>
              {['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role) && (
                 <option value="Alle Benutzer">Alle Benutzer</option>
              )}
              {allUsers.filter(u => u.id !== currentUser.id).map(u => <option key={u.id} value={String(u.id)}>{u.name}</option>)}
            </select>
          </div>
          <div className="export-buttons">
            <button className="action-button" onClick={() => exportToCsv(filteredEntries, masterData)} disabled={filteredEntries.length === 0}>Export (CSV)</button>
            <button className="action-button" onClick={() => exportToPdf(filteredEntries, masterData)} disabled={filteredEntries.length === 0}>Export (PDF)</button>
          </div>
        </div>
        <div className="info-container">
          {selectedUserMasterData && (
              <>
                <div><strong>Wochenarbeitsstunden:</strong> {selectedUserMasterData.weeklyHours.toFixed(2)}</div>
                <div><strong>Wochenarbeitstage:</strong> {selectedUserMasterData.workdays.join(', ')}</div>
                <div>
                    <strong>Summe Zeitraum:</strong> {formatTime(weeklyTotalSeconds)}
                </div>
              </>
          )}
          {currentUser.role !== 'Mitarbeiter' && <div><strong>Rolle:</strong> {currentUser.role}</div>}
        </div>
      </div>

      <div className="table-wrapper">
        <table className="data-table">
          <thead>
            <tr>
              <th>Details</th>
              <th>Username</th>
              <th>Datum</th>
              <th>Startzeit</th>
              <th>Stoppzeit</th>
              <th>Gesamtzeit</th>
              <th>Pause</th>
              <th>Soll/Ist-Diff.</th>
              <th>Standort</th>
              {currentUser.role !== 'Mitarbeiter' && <th>Rolle</th>}
              <th>Geändert von</th>
              <th>Geändert am</th>
            </tr>
          </thead>
          <tbody>
            {filteredEntries.map(entry => {
                const userMasterData = masterData[entry.userId];
                const dailySollTime = userMasterData && userMasterData.workdays.length > 0 ? (userMasterData.weeklyHours / userMasterData.workdays.length) * 3600 : 0;
                const diffSeconds = entry.totalSeconds - dailySollTime;
                const diffSign = diffSeconds >= 0 ? '+' : '-';
                const request = approvalRequests.find(r => r.entry.id === entry.id);
                let rowClass = request ? (request.type === 'edit' ? 'pending-change' : 'pending-deletion') : '';
                if (entry.isUnsynced) rowClass += ' unsynced-entry';

                return (
                  <tr key={`${entry.date}-${entry.username}`} className={rowClass}>
                    <td className="cell-center"><button className="details-button" onClick={() => onShowDetails(entry.date, entry.username)}>Details</button></td>
                    <td className="text-left">{entry.username}</td>
                    <td className="cell-center">{new Date(entry.date + "T00:00:00").toLocaleDateString('de-DE')}</td>
                    <td className="cell-center">{entry.firstStart}</td>
                    <td className="cell-center">{entry.lastStop}</td>
                    <td className="cell-center">{formatTime(entry.totalSeconds)}</td>
                    <td className="cell-center">{formatTime(entry.pauseSeconds)}</td>
                    <td className="cell-center" style={{color: diffSeconds < 0 ? 'var(--red-color)' : 'var(--green-color)'}}>
                      {diffSign}{formatTime(Math.abs(diffSeconds))}
                    </td>
                    <td className="text-left">{entry.location}</td>
                    {currentUser.role !== 'Mitarbeiter' && <td className="text-left">{entry.role}</td>}
                    <td className="text-left">{entry.updatedBy}</td>
                    <td className="cell-center">{new Date(entry.updatedAt).toLocaleString('de-DE')}</td>
                  </tr>
                );
            })}
          </tbody>
        </table>
      </div>
      
      <footer className="view-footer">
        <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
      </footer>
    </div>
  );
};