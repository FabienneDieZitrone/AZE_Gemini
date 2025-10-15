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
    const defaultForm: MasterData = {
        weeklyHours: 40,
        workdays: ["Mo","Di","Mi","Do","Fr"],
        canWorkFromHome: false,
        locations: [],
        flexibleWorkdays: false,
        dailyHours: {}
    } as any;
    const [formData, setFormData] = useState<MasterData | null>(masterData[selectedUserId] as any || defaultForm);

    useEffect(() => {
        if (masterData[selectedUserId]) {
            // Merge with defaults to ensure new fields exist
            const md = masterData[selectedUserId] as any;
            setFormData({
                weeklyHours: md.weeklyHours,
                workdays: Array.isArray(md.workdays) ? md.workdays : defaultForm.workdays,
                canWorkFromHome: !!md.canWorkFromHome,
                locations: Array.isArray(md.locations) ? md.locations : [],
                flexibleWorkdays: !!md.flexibleWorkdays,
                dailyHours: md.dailyHours || {}
            } as any);
        } else {
            // Provide sane defaults so UI is usable instead of hanging on loader
            setFormData(defaultForm);
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
            const exists = prev.workdays.includes(value);
            let newWorkdays = prev.workdays;
            let newDaily = { ...(prev as any).dailyHours } as Record<string, number>;
            if (checked && !exists) {
                newWorkdays = [...prev.workdays, value];
                if (newDaily[value] == null) newDaily[value] = 8; // default 8h for new day
            }
            if (!checked && exists) {
                newWorkdays = prev.workdays.filter(day => day !== value);
                delete newDaily[value];
            }
            return { ...(prev as any), workdays: newWorkdays, dailyHours: newDaily } as any;
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
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
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
                        <div className="checkbox-group" style={{ gap: 12 }}>
                            {workdaysOptions.map(day => (
                                <label key={day}>
                                    <input type="checkbox" value={day} checked={formData.workdays.includes(day)} onChange={handleDayChange}/>
                                    {day}
                                </label>
                            ))}
                        </div>
                        <div style={{ marginTop: 8 }}>
                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                <input type="checkbox" checked={(formData as any).flexibleWorkdays || false} onChange={e => setFormData(prev => prev ? ({ ...(prev as any), flexibleWorkdays: e.target.checked } as any) : prev)} />
                                Flexibel
                            </label>
                        </div>
                        {/* Daily hours per selected workday */}
                        {formData.workdays.length > 0 && (
                          <div style={{ marginTop: 12 }}>
                            <div style={{ fontWeight: 600, marginBottom: 6 }}>Tägliche Stunden je ausgewähltem Tag</div>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, minmax(0, 1fr))', gap: 8 }}>
                              {formData.workdays.map(d => (
                                <label key={d} style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                  <span>{d}</span>
                                  <input type="number" min={0} step={0.25} value={(formData as any).dailyHours?.[d] ?? 8} onChange={e => {
                                    const v = parseFloat(e.target.value);
                                    setFormData(prev => prev ? ({ ...(prev as any), dailyHours: { ...(prev as any).dailyHours, [d]: isNaN(v) ? 0 : v } } as any) : prev)
                                  }} />
                                </label>
                              ))}
                            </div>
                          </div>
                        )}
                    </div>
                    <div className="form-group">
                        <label>Zugeordnete Standorte</label>
                        <select multiple size={4} value={(formData as any).locations || []} onChange={(e) => {
                            const vals = Array.from(e.target.selectedOptions).map(o => o.value);
                            setFormData(prev => prev ? ({ ...(prev as any), locations: vals } as any) : prev);
                        }}>
                            {locations.map(loc => <option key={loc} value={loc}>{loc}</option>)}
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
