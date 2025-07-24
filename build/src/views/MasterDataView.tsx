/**
 * Titel: Stammdaten-Ansicht
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/MasterDataView.tsx
 */
import React, { useState, useEffect } from 'react';
import { MasterData, User } from '../types';
import { LoadingSpinner } from '../components/common/LoadingSpinner';

export const MasterDataView: React.FC<{ 
    onBack: () => void;
    masterData: Record<number, MasterData>;
    users: User[];
    currentUser: User;
    onSave: (userId: number, data: MasterData) => void;
    onEditRole: (user: User) => void;
    locations: string[];
}> = ({ onBack, masterData, users, currentUser, onSave, onEditRole, locations }) => {
    const [selectedUserId, setSelectedUserId] = useState<number>(currentUser.id);
    const [formData, setFormData] = useState<MasterData | null>(masterData[selectedUserId]);

    useEffect(() => {
        if (masterData[selectedUserId]) {
            setFormData(masterData[selectedUserId]);
        } else {
             setFormData(null); // Handle case where data might not be available yet
        }
    }, [selectedUserId, masterData]);

    const handleUserChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setSelectedUserId(Number(e.target.value));
    };

    const handleHoursChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const hours = parseFloat(e.target.value);
        setFormData(prev => prev ? ({...prev, weeklyHours: isNaN(hours) ? 0 : hours}) : null);
    };

    const handleDayChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { value, checked } = e.target;
        setFormData(prev => {
            if (!prev) return null;
            const newWorkdays = checked
                ? [...prev.workdays, value]
                : prev.workdays.filter(day => day !== value);
            return {...prev, workdays: newWorkdays};
        });
    };
    
    const handleHomeOfficeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData(prev => prev ? ({...prev, canWorkFromHome: e.target.checked}) : null);
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        if (formData) {
            onSave(selectedUserId, formData);
        }
    };

    const workdaysOptions = ["Mo", "Di", "Mi", "Do", "Fr"];
    const selectedUser = users.find(u => u.id === selectedUserId) || currentUser;

    if (!formData) {
        return <div className="view-container"><LoadingSpinner/></div>;
    }

    return (
        <div className="view-container">
            <header className="view-header">
                <h2>Stammdaten verwalten</h2>
            </header>
            <form className="master-data-form" onSubmit={handleSave}>
                <div className="master-data-actions">
                    <div className="filter-group">
                        <label htmlFor="user-select">Benutzer auswählen</label>
                        <select id="user-select" value={selectedUserId} onChange={handleUserChange}>
                            {users.map(u => <option key={u.id} value={u.id}>{u.name}{u.id === currentUser.id ? ' (Ich)' : ''}</option>)}
                        </select>
                    </div>
                     <button type="button" className="nav-button" onClick={() => onEditRole(selectedUser)}>Rolle vergeben/bearbeiten</button>
                </div>
                <div className="form-grid">
                    <div className="form-group">
                        <label htmlFor="weeklyHours">Regelmäßige Wochenarbeitszeit</label>
                        <input type="number" id="weeklyHours" value={formData.weeklyHours} onChange={handleHoursChange} step="0.25"/>
                    </div>
                    <div className="form-group">
                        <label>Regelmäßige Wochenarbeitstage</label>
                        <div className="checkbox-group">
                            {workdaysOptions.map(day => (
                                <label key={day}>
                                    <input type="checkbox" value={day} checked={formData.workdays.includes(day)} onChange={handleDayChange}/>
                                    {day}
                                </label>
                            ))}
                        </div>
                    </div>
                    <div className="form-group">
                        <label>Zugeordnete Standorte</label>
                         <select multiple disabled size={3}>
                            {locations.map(loc => <option key={loc}>{loc}</option>)}
                        </select>
                    </div>
                     <div className="form-group">
                        <label>Home Office</label>
                         <div className="checkbox-group">
                             <label><input type="checkbox" checked={formData.canWorkFromHome} onChange={handleHomeOfficeChange}/> Erlaubt</label>
                         </div>
                    </div>
                </div>
                <div className="master-data-actions">
                     <button type="submit" className="action-button">Änderungen speichern</button>
                </div>
            </form>
            <footer className="view-footer">
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
