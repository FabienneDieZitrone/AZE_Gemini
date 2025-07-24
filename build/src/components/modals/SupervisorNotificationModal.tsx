/**
 * Titel: SupervisorNotificationModal Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/modals/SupervisorNotificationModal.tsx
 */
import React from 'react';
import { SupervisorNotification } from '../../types';

export const SupervisorNotificationModal: React.FC<{
    notifications: SupervisorNotification[];
    onClose: () => void;
}> = ({ notifications, onClose }) => {
    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <header className="modal-header">
                    <h3>Wichtige Benachrichtigung</h3>
                    <button className="close-button" onClick={onClose}>&times;</button>
                </header>
                <div className="modal-body">
                    <p>Folgende Mitarbeiter hatten in der letzten Woche signifikante Zeitabweichungen:</p>
                    <ul>
                        {notifications.map((n, i) => (
                            <li key={i}>
                                <strong>{n.employeeName}:</strong> 
                                <span style={{color: n.deviationHours < 0 ? 'var(--red-color)' : 'var(--green-color)'}}>
                                    {n.deviationHours.toFixed(2)} Stunden
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
                <footer className="modal-footer">
                    <button type="button" className="action-button" onClick={onClose}>Verstanden</button>
                </footer>
            </div>
        </div>
    );
};
