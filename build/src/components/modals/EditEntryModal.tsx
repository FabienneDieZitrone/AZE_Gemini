/**
 * Titel: EditEntryModal Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/modals/EditEntryModal.tsx
 */
import React, { useState } from 'react';
import { TimeEntry, EditFormData, ReasonData, User } from '../../types';

export const EditEntryModal: React.FC<{
    entry: TimeEntry;
    onClose: () => void;
    onSave: (entry: TimeEntry, reasonData: ReasonData) => void;
    changeReasons: string[];
    currentUser: User;
}> = ({ entry, onClose, onSave, changeReasons, currentUser }) => {
    const [formData, setFormData] = useState<EditFormData>({
        startTime: `${entry.date}T${entry.startTime}`,
        stopTime: `${entry.date}T${entry.stopTime}`,
        reason: '',
        reasonDetails: ''
    });

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        const { id, value } = e.target;
        setFormData(prev => ({...prev, [id]: value}));
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        const updatedEntryData: Partial<TimeEntry> = {
            startTime: new Date(formData.startTime).toTimeString().split(' ')[0],
            stopTime: new Date(formData.stopTime).toTimeString().split(' ')[0],
            updatedBy: currentUser.name,
            updatedAt: new Date().toISOString(),
        };
        onSave({...entry, ...updatedEntryData}, {reason: formData.reason, details: formData.reasonDetails});
    };

    const isSaveDisabled = formData.reason === '' || (formData.reason === 'Sonstige' && formData.reasonDetails.trim() === '');

    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <header className="modal-header">
                    <h3>Eintrag bearbeiten</h3>
                    <button className="close-button" onClick={onClose}>&times;</button>
                </header>
                <form onSubmit={handleSave}>
                    <div className="modal-body">
                        <div className="form-group">
                            <label htmlFor="startTime">Startzeit</label>
                            <input type="datetime-local" id="startTime" value={formData.startTime} onChange={handleInputChange} />
                        </div>
                        <div className="form-group">
                            <label htmlFor="stopTime">Stoppzeit</label>
                            <input type="datetime-local" id="stopTime" value={formData.stopTime} onChange={handleInputChange} />
                        </div>
                        <div className="form-group">
                            <label htmlFor="reason">Änderungsgrund</label>
                            <select id="reason" value={formData.reason} onChange={handleInputChange} required>
                                <option value="" disabled>Bitte auswählen...</option>
                                {changeReasons.map(r => <option key={r} value={r}>{r}</option>)}
                            </select>
                        </div>
                        {formData.reason === 'Sonstige' && (
                             <div className="form-group">
                                <label htmlFor="reasonDetails">Details für "Sonstige"</label>
                                <textarea id="reasonDetails" value={formData.reasonDetails} onChange={handleInputChange} rows={3} required></textarea>
                            </div>
                        )}
                    </div>
                    <footer className="modal-footer">
                        <button type="button" className="nav-button" onClick={onClose}>Abbrechen</button>
                        <button type="submit" className="action-button" disabled={isSaveDisabled}>Änderung beantragen</button>
                    </footer>
                </form>
            </div>
        </div>
    );
};
