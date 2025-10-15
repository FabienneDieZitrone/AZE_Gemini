/**
 * Titel: Historien-Ansicht (Audit Trail)
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/ChangeHistoryView.tsx
 */
import React, { useState, useMemo } from 'react';
import { HistoryEntry, User } from '../types';

export const ChangeHistoryView: React.FC<{
    onBack: () => void;
    history: HistoryEntry[];
    allUsers: User[];
    locations: string[];
}> = ({ onBack, history, allUsers, locations }) => {
    const [filters, setFilters] = useState({
        startDate: '',
        endDate: '',
        user: 'alle',
        location: 'alle',
        action: 'alle',
    });

    const handleFilterChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        setFilters(prev => ({...prev, [e.target.name]: e.target.value}));
    };

    const filteredHistory = useMemo(() => {
        return history.filter(item => {
            if (filters.startDate && item.entry.date < filters.startDate) return false;
            if (filters.endDate && item.entry.date > filters.endDate) return false;
            if (filters.user !== 'alle' && item.entry.userId !== Number(filters.user)) return false;
            if (filters.location !== 'alle' && item.entry.location !== filters.location) return false;
            if (filters.action !== 'alle' && item.type !== filters.action) return false;
            return true;
        });
    }, [history, filters]);

    return (
        <div className="view-container">
            <header className="view-header">
                <h2>Anzeige der Änderungen (Historie)</h2>
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </header>
            
            <div className="history-filters-container">
                <div className="filter-group">
                    <label htmlFor="startDate">Von</label>
                    <input type="date" name="startDate" value={filters.startDate} onChange={handleFilterChange} />
                </div>
                <div className="filter-group">
                    <label htmlFor="endDate">Bis</label>
                    <input type="date" name="endDate" value={filters.endDate} onChange={handleFilterChange} />
                </div>
                 <div className="filter-group">
                    <label htmlFor="user-filter">Benutzer</label>
                    <select name="user" value={filters.user} onChange={handleFilterChange}>
                        <option value="alle">Alle</option>
                        {allUsers.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                    </select>
                </div>
                <div className="filter-group">
                    <label htmlFor="location-filter">Standort</label>
                    <select name="location" value={filters.location} onChange={handleFilterChange}>
                        <option value="alle">Alle Standorte</option>
                        {locations.map(loc => <option key={loc} value={loc}>{loc}</option>)}
                    </select>
                </div>
                <div className="filter-group">
                    <label htmlFor="action-filter">Aktion</label>
                    <select name="action" value={filters.action} onChange={handleFilterChange}>
                        <option value="alle">Alle Aktionen</option>
                        <option value="edit">Bearbeitet</option>
                        <option value="delete">Gelöscht</option>
                    </select>
                </div>
            </div>

            <div className="table-wrapper">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Aktion</th>
                            <th>Benutzer</th>
                            <th>Details</th>
                            <th>Grund</th>
                            <th>Bearbeitet von</th>
                            <th>Bearbeitet am</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filteredHistory.length === 0 && (
                            <tr><td colSpan={8} className="cell-center">Keine Einträge für die gewählten Filter.</td></tr>
                        )}
                        {filteredHistory.map(item => (
                            <tr key={item.id}>
                                <td className="cell-center">{new Date(item.entry.date + "T00:00:00").toLocaleDateString('de-DE')}</td>
                                <td className="cell-center">{item.type === 'edit' ? 'Bearbeitet' : 'Gelöscht'}</td>
                                <td className="text-left">{item.entry.username}</td>
                                <td className="text-left">
                                    {item.type === 'edit' ?
                                        `Zeit von ${item.entry.startTime} auf ${item.newData?.startTime} & ${item.entry.stopTime} auf ${item.newData?.stopTime} geändert.` :
                                        `Eintrag von ${item.entry.startTime} - ${item.entry.stopTime} gelöscht.`
                                    }
                                </td>
                                <td className="text-left">{item.reasonData?.reason === 'Sonstige' ? item.reasonData.details : item.reasonData?.reason || 'N/A'}</td>
                                <td className="text-left">{item.resolvedBy}</td>
                                <td className="cell-center">{new Date(item.resolvedAt).toLocaleString('de-DE')}</td>
                                <td className="cell-center" style={{color: item.finalStatus === 'genehmigt' ? 'var(--green-color)' : 'var(--red-color)'}}>
                                    {item.finalStatus}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <footer className="view-footer">
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
