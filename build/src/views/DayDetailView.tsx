/**
 * Titel: Tagesdetail-Ansicht
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/DayDetailView.tsx
 */
import React from 'react';
import { TimeEntry, ApprovalRequest, Role } from '../types';
import { formatTime, calculateDurationInSeconds } from '../utils/time';

export const DayDetailView: React.FC<{
  onBack: () => void;
  onGoToMain: () => void;
  onShowHistory: () => void;
  onEdit: (entry: TimeEntry) => void;
  onDelete: (entry: TimeEntry) => void;
  entries: TimeEntry[];
  approvalRequests: ApprovalRequest[];
  date: string;
  username: string;
  userRole: Role;
}> = ({ onBack, onGoToMain, onShowHistory, onEdit, onDelete, entries, approvalRequests, date, username, userRole }) => {
    
    const entriesForDay = entries.filter(
        entry => entry.date === date && entry.username === username
    );
    
    const pendingRequestIds = new Set(approvalRequests.map(r => r.entry.id));

    const formattedDate = new Date(date + "T00:00:00").toLocaleDateString('de-DE', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
    
    const getRowClass = (entry: TimeEntry) => {
        const request = approvalRequests.find(r => r.entry.id === entry.id);
        let className = '';
        if (request) {
            if (request.type === 'delete') className += ' pending-deletion';
            if (request.type === 'edit') className += ' pending-change';
        }
        if (entry.isUnsynced) {
            className += ' unsynced-entry';
        }
        return className;
    };

    return (
        <div className="view-container">
            <header className="day-detail-header">
                <h2>Tagesdetails für {username}</h2>
                <p>{formattedDate}</p>
            </header>
            
            <div className="day-detail-controls">
                 <button className="action-button" onClick={onShowHistory}>Anzeige der Änderungen</button>
                 <div className="control-group">
                    <button className="nav-button" onClick={onBack}>Zurück zur Übersicht</button>
                    <button className="nav-button" onClick={onGoToMain}>Zurück zur Startseite</button>
                 </div>
            </div>

            <div className="table-wrapper">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Bearbeiten</th>
                            <th>Löschen</th>
                            <th>Username</th>
                            <th>Startzeit</th>
                            <th>Stoppzeit</th>
                            <th>Arbeitszeit</th>
                            <th>Standort</th>
                            {userRole !== 'Mitarbeiter' && <th>Rolle</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {entriesForDay.map(entry => {
                            const totalSeconds = calculateDurationInSeconds(entry.startTime, entry.stopTime);
                            const isPending = pendingRequestIds.has(entry.id);
                            return (
                                <tr key={entry.id} className={getRowClass(entry)}>
                                    <td className="cell-center"><button className="edit-button" onClick={() => onEdit(entry)} disabled={isPending}>Bearbeiten</button></td>
                                    <td className="cell-center"><button className="delete-button" onClick={() => onDelete(entry)} disabled={isPending}>Löschen</button></td>
                                    <td className="text-left">{entry.username}</td>
                                    <td className="cell-center">{entry.startTime}</td>
                                    <td className="cell-center">{entry.stopTime}</td>
                                    <td className="cell-center">{formatTime(totalSeconds)}</td>
                                    <td className="text-left">{entry.location}</td>
                                    {userRole !== 'Mitarbeiter' && <td className="text-left">{entry.role}</td>}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <footer className="view-footer">
                <button className="nav-button" onClick={onGoToMain}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
