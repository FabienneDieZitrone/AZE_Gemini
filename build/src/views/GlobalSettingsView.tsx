/**
 * Titel: Globale Einstellungen-Ansicht
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/GlobalSettingsView.tsx
 */
import React, { useState, useEffect } from 'react';
import { GlobalSettings } from '../types';

export const GlobalSettingsView: React.FC<{
    onBack: () => void;
    settings: GlobalSettings;
    onSave: (newSettings: GlobalSettings) => void;
}> = ({ onBack, settings, onSave }) => {
    const [formData, setFormData] = useState(settings);
    const [newLocation, setNewLocation] = useState('');

    useEffect(() => {
        setFormData(settings);
    }, [settings]);

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        onSave(formData);
    };

    const handleAddLocation = () => {
        if (newLocation.trim() && !formData.locations.includes(newLocation.trim())) {
            setFormData(prev => ({...prev, locations: [...prev.locations, newLocation.trim()]}));
            setNewLocation('');
        }
    };
    
    const handleRemoveLocation = (locationToRemove: string) => {
        setFormData(prev => ({...prev, locations: prev.locations.filter(loc => loc !== locationToRemove)}));
    };

    return (
        <div className="view-container">
             <header className="view-header">
                <h2>Globale Einstellungen</h2>
            </header>
            <form className="master-data-form" onSubmit={handleSave}>
                <div className="form-grid">
                    <div className="form-group">
                        <label htmlFor="overtimeThreshold">Toleranzschwelle für Überstunden (Stunden)</label>
                        <input
                            type="number"
                            id="overtimeThreshold"
                            value={formData.overtimeThreshold}
                            onChange={(e) => setFormData(prev => ({ ...prev, overtimeThreshold: parseFloat(e.target.value) || 0 }))}
                            step="0.5"
                        />
                    </div>
                    <div className="form-group">
                         <label htmlFor="changeReasons">Liste der Änderungsgründe</label>
                         <textarea
                            id="changeReasons"
                            value={formData.changeReasons.join('\n')}
                            onChange={(e) => setFormData(prev => ({ ...prev, changeReasons: e.target.value.split('\n') }))}
                            rows={6}
                            placeholder="Ein Grund pro Zeile"
                         />
                    </div>
                    <div className="form-group location-manager">
                        <label>Standorte verwalten</label>
                        <ul className="location-list">
                            {formData.locations.map(loc => (
                                <li key={loc}>
                                    <span>{loc}</span>
                                    <button type="button" onClick={() => handleRemoveLocation(loc)}>&times;</button>
                                </li>
                            ))}
                        </ul>
                        <div className="add-location-group">
                            <input
                                type="text"
                                value={newLocation}
                                onChange={e => setNewLocation(e.target.value)}
                                placeholder="Neuer Standort"
                            />
                            <button type="button" className="action-button" onClick={handleAddLocation}>Hinzufügen</button>
                        </div>
                    </div>
                </div>
                 <div className="master-data-actions">
                     <button type="submit" className="action-button">Einstellungen speichern</button>
                </div>
            </form>

            <footer className="view-footer">
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
