/**
 * Titel: SupervisorNotificationModal Komponente
 * Version: 2.0
 * Letzte Aktualisierung: 23.10.2025
 * Autor: MP-IT
 * Datei: /src/components/modals/SupervisorNotificationModal.tsx
 */
import React from 'react';
import { SupervisorNotification, PendingOnboardingUser } from '../../types';

export const SupervisorNotificationModal: React.FC<{
    notifications: SupervisorNotification[];
    pendingOnboardingUsers?: PendingOnboardingUser[];
    onClose: () => void;
}> = ({ notifications, pendingOnboardingUsers = [], onClose }) => {
    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <header className="modal-header">
                    <h3>Wichtige Benachrichtigungen</h3>
                    <button className="close-button" onClick={onClose}>&times;</button>
                </header>
                <div className="modal-body">
                    {pendingOnboardingUsers.length > 0 && (
                        <div style={{marginBottom: '1.5rem'}}>
                            <h4 style={{color: 'var(--accent-color)', marginBottom: '0.5rem'}}>
                                🆕 Neue Mitarbeiter warten auf Stammdaten-Eingabe
                            </h4>
                            <p>Die folgenden Mitarbeiter haben das Onboarding abgeschlossen und benötigen Ihre Stammdaten-Eingabe:</p>
                            <ul>
                                {pendingOnboardingUsers.map((user) => (
                                    <li key={user.id}>
                                        <strong>{user.name}</strong>
                                        <span style={{color: 'var(--secondary-text-color)'}}>
                                            {' '}({user.homeLocation}) - seit {user.daysPending} Tag{user.daysPending !== 1 ? 'en' : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p style={{marginTop: '1rem', padding: '0.75rem', backgroundColor: 'var(--tertiary-bg-color)', borderRadius: '4px', fontSize: '0.9rem'}}>
                                💡 <strong>Hinweis:</strong> Gehen Sie zu <strong>Stammdaten</strong>, um die Arbeitszeiteinstellungen für diese Mitarbeiter zu vervollständigen.
                            </p>
                        </div>
                    )}

                    {notifications.length > 0 && (
                        <div>
                            <h4 style={{color: 'var(--accent-color)', marginBottom: '0.5rem'}}>
                                ⏰ Zeitabweichungen letzte Woche
                            </h4>
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
                    )}
                </div>
                <footer className="modal-footer">
                    <button type="button" className="action-button" onClick={onClose}>Verstanden</button>
                </footer>
            </div>
        </div>
    );
};
