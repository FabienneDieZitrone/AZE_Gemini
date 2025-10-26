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
            const workdays = Array.isArray(md.workdays) ? md.workdays : defaultForm.workdays;

            // Initialize dailyHours with defaults if empty/null
            let dailyHours = md.dailyHours || {};
            if (!md.dailyHours || Object.keys(md.dailyHours).length === 0) {
                // Calculate default hours per day
                const hoursPerDay = workdays.length > 0 ? md.weeklyHours / workdays.length : 8;
                dailyHours = {};
                workdays.forEach((day: string) => {
                    dailyHours[day] = Math.round(hoursPerDay * 4) / 4; // Round to nearest 0.25
                });
            }

            setFormData({
                weeklyHours: md.weeklyHours,
                workdays: workdays,
                canWorkFromHome: !!md.canWorkFromHome,
                locations: Array.isArray(md.locations) ? md.locations : [],
                flexibleWorkdays: !!md.flexibleWorkdays,
                dailyHours: dailyHours
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
            // Validate: sum of daily hours must equal weekly hours
            const dailyHoursSum = Object.values((formData as any).dailyHours || {}).reduce((sum: number, h: any) => sum + (parseFloat(h) || 0), 0);
            const weeklyHours = formData.weeklyHours || 0;
            if (Math.abs(dailyHoursSum - weeklyHours) > 0.01) { // Allow small floating point differences
                alert(`Die Summe der täglichen Stunden (${dailyHoursSum.toFixed(2)}h) muss der regelmäßigen Wochenarbeitszeit (${weeklyHours.toFixed(2)}h) entsprechen!`);
                return;
            }
            onSave(selectedUserId, formData);
        }
    };

    const workdaysOptions = ["Mo", "Di", "Mi", "Do", "Fr"];
    const workdaysLabels: Record<string, string> = {
        "Mo": "Montag",
        "Di": "Dienstag",
        "Mi": "Mittwoch",
        "Do": "Donnerstag",
        "Fr": "Freitag"
    };
    const selectedUser = users.find(u => u.id === selectedUserId) || currentUser;

    // Calculate daily hours sum for validation display
    const dailyHoursSum = formData ? Object.values((formData as any).dailyHours || {}).reduce((sum: number, h: any) => sum + (parseFloat(h) || 0), 0) : 0;
    const weeklyHours = formData?.weeklyHours || 0;
    const hoursMatch = Math.abs(dailyHoursSum - weeklyHours) < 0.01;

    // Check if current user can assign locations (Security Fix: Only Admin - 2025-10-26)
    const canAssignLocations = currentUser.role === 'Admin';

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
                <div className="form-grid" style={{ gridTemplateColumns: '1fr', maxWidth: 800 }}>
                    <div className="form-group">
                        <label htmlFor="weeklyHours" style={{ fontWeight: 600 }}>Regelmäßige Wochenarbeitszeit</label>
                        <input type="number" id="weeklyHours" value={formData.weeklyHours} onChange={handleHoursChange} step="0.25"/>
                    </div>
                    <div className="form-group">
                        <label style={{ fontWeight: 600 }}>Regelmäßige Wochenarbeitstage</label>
                        <div style={{ marginTop: 8 }}>
                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                <input type="checkbox" checked={(formData as any).flexibleWorkdays || false} onChange={e => setFormData(prev => prev ? ({ ...(prev as any), flexibleWorkdays: e.target.checked } as any) : prev)} />
                                Flexibel
                            </label>
                        </div>
                        {/* Daily hours with checkbox as header - horizontal layout */}
                        <div style={{ marginTop: 12, display: 'flex', gap: 16, flexWrap: 'wrap' }}>
                          {workdaysOptions.map(day => (
                            <div key={day} style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                              <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 600, cursor: 'pointer' }}>
                                <input type="checkbox" value={day} checked={formData.workdays.includes(day)} onChange={handleDayChange}/>
                                {workdaysLabels[day]}
                              </label>
                              {formData.workdays.includes(day) && (
                                <input
                                  type="number"
                                  min={0}
                                  step={0.25}
                                  value={(formData as any).dailyHours?.[day] ?? 8}
                                  onChange={e => {
                                    const v = parseFloat(e.target.value);
                                    setFormData(prev => prev ? ({ ...(prev as any), dailyHours: { ...(prev as any).dailyHours, [day]: isNaN(v) ? 0 : v } } as any) : prev)
                                  }}
                                  style={{ width: 90 }}
                                  placeholder="Std."
                                />
                              )}
                            </div>
                          ))}
                        </div>
                        {formData.workdays.length > 0 && (
                          <div style={{ marginTop: 12, padding: 8, backgroundColor: hoursMatch ? '#d4edda' : '#f8d7da', color: hoursMatch ? '#155724' : '#721c24', borderRadius: 4, fontSize: '0.9em' }}>
                            {hoursMatch
                              ? `✓ Summe stimmt: ${weeklyHours.toFixed(2)}h (Regelmäßige Wochenarbeitszeit) = ${dailyHoursSum.toFixed(2)}h (Tägliche Stunden aller ausgewählten Tage)`
                              : `⚠ Summe stimmt nicht: ${weeklyHours.toFixed(2)}h (Regelmäßige Wochenarbeitszeit) ≠ ${dailyHoursSum.toFixed(2)}h (Tägliche Stunden) - Differenz: ${Math.abs(dailyHoursSum - weeklyHours).toFixed(2)}h`
                            }
                          </div>
                        )}
                    </div>
                    <div className="form-group">
                        <label style={{ fontWeight: 600 }}>Home Office</label>
                        <div className="checkbox-group">
                            <label><input type="checkbox" checked={formData.canWorkFromHome} onChange={handleHomeOfficeChange}/> Erlaubt</label>
                        </div>
                    </div>
                    {canAssignLocations && (
                        <div className="form-group">
                            <label style={{ fontWeight: 600 }}>Zugeordnete Standorte</label>
                            <select multiple size={4} value={(formData as any).locations || []} onChange={(e) => {
                                const vals = Array.from(e.target.selectedOptions).map(o => o.value);
                                setFormData(prev => prev ? ({ ...(prev as any), locations: vals } as any) : prev);
                            }}>
                                {[...locations].sort((a, b) => a.localeCompare(b, 'de', {sensitivity: 'base'})).map(loc => <option key={loc} value={loc}>{loc}</option>)}
                            </select>
                            <div style={{ marginTop: 4, fontSize: '0.9em', color: '#666' }}>
                                Halten Sie Strg/Cmd gedrückt um mehrere Standorte auszuwählen
                            </div>
                        </div>
                    )}
                </div>
                <div className="master-data-actions">
                     <button type="submit" className="action-button" disabled={!hoursMatch} title={!hoursMatch ? 'Bitte korrigieren Sie die Stundensumme' : ''}>Änderungen speichern</button>
                </div>
            </form>
            <footer className="view-footer">
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
