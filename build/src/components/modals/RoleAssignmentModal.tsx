/**
 * Titel: RoleAssignmentModal Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/modals/RoleAssignmentModal.tsx
 */
import React, { useState, useMemo } from 'react';
import { User, Role } from '../../types';

export const RoleAssignmentModal: React.FC<{
    user: User;
    currentUser: User;
    onClose: () => void;
    onSave: (userId: number, newRole: Role) => void;
}> = ({ user, currentUser, onClose, onSave }) => {
    const [selectedRole, setSelectedRole] = useState<Role>(user.role);

    const assignableRoles: Role[] = useMemo(() => {
        if (currentUser.role === 'Admin') {
            return ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'];
        }
        if (currentUser.role === 'Bereichsleiter') {
            return ['Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'];
        }
        if (currentUser.role === 'Standortleiter') {
            return ['Mitarbeiter', 'Honorarkraft'];
        }
        return [];
    }, [currentUser.role]);

    const handleSave = () => {
        onSave(user.id, selectedRole);
        onClose();
    };

    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <header className="modal-header">
                    <h3>Rolle vergeben für {user.name}</h3>
                    <button className="close-button" onClick={onClose}>&times;</button>
                </header>
                <div className="modal-body">
                    <div className="form-group">
                        <label htmlFor="role-select">Rolle</label>
                        <select
                            id="role-select"
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value as Role)}
                        >
                            {assignableRoles.map(role => (
                                <option key={role} value={role}>{role}</option>
                            ))}
                        </select>
                    </div>
                </div>
                <footer className="modal-footer">
                    <button type="button" className="nav-button" onClick={onClose}>Abbrechen</button>
                    <button type="button" className="action-button" onClick={handleSave}>Speichern</button>
                </footer>
            </div>
        </div>
    );
};
