/**
 * Titel: Genehmigungs-Ansicht
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/ApprovalView.tsx
 */
import React from 'react';
import { ApprovalRequest } from '../types';

export const ApprovalView: React.FC<{
    onBack: () => void;
    requests: ApprovalRequest[];
    onApprove: (requestId: string) => void;
    onReject: (requestId: string) => void;
}> = ({ onBack, requests, onApprove, onReject }) => {
    return (
        <div className="view-container">
            <header className="view-header">
                <h2>Ausstehende Genehmigungen</h2>
            </header>
            <div className="table-wrapper">
                <table className="data-table approval-table">
                    <thead>
                        <tr>
                            <th>Antragsteller</th>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th>Details</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        {requests.length === 0 && (
                            <tr><td colSpan={5} className="cell-center">Keine ausstehenden Anträge.</td></tr>
                        )}
                        {requests.map(req => (
                            <tr key={req.id}>
                                <td className="text-left">{req.requestedBy}</td>
                                <td className="cell-center">{new Date(req.entry.date + "T00:00:00").toLocaleDateString('de-DE')}</td>
                                <td className="cell-center">{req.type === 'edit' ? 'Änderung' : 'Löschung'}</td>
                                <td className="text-left">
                                    {req.type === 'edit' && req.newData && (
                                        <>
                                            <p><strong>Alt:</strong> {req.entry.startTime} - {req.entry.stopTime}</p>
                                            <p><strong>Neu:</strong> {req.newData.startTime} - {req.newData.stopTime}</p>
                                            <p><strong>Grund:</strong> {req.reasonData?.reason} {req.reasonData?.reason === 'Sonstige' ? `(${req.reasonData.details})` : ''}</p>
                                        </>
                                    )}
                                     {req.type === 'delete' && (
                                        <p>Eintrag vom {req.entry.startTime} bis {req.entry.stopTime}</p>
                                    )}
                                </td>
                                <td className="action-cell">
                                    <button className="action-button start-button" onClick={() => onApprove(req.id)}>Genehmigen</button>
                                    <button className="action-button stop-button" onClick={() => onReject(req.id)}>Ablehnen</button>
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
