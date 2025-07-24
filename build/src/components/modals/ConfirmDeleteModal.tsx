/**
 * Titel: ConfirmDeleteModal Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/modals/ConfirmDeleteModal.tsx
 */
import React from 'react';

export const ConfirmDeleteModal: React.FC<{
    onConfirm: () => void;
    onCancel: () => void;
}> = ({ onConfirm, onCancel }) => {
    return (
        <div className="modal-backdrop" onClick={onCancel}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <header className="modal-header">
                    <h3>Löschen bestätigen</h3>
                    <button className="close-button" onClick={onCancel}>&times;</button>
                </header>
                <div className="modal-body">
                    <p>Sind Sie sicher, dass Sie diesen Eintrag zum Löschen vormerken möchten? Die endgültige Löschung erfolgt nach Genehmigung durch einen Vorgesetzten.</p>
                </div>
                <footer className="modal-footer">
                    <button type="button" className="nav-button" onClick={onCancel}>Abbrechen</button>
                    <button type="button" className="action-button stop-button" onClick={onConfirm}>Ja, zum Löschen vormerken</button>
                </footer>
            </div>
        </div>
    );
};
