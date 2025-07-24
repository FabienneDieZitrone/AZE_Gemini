/**
 * Titel: ThemeToggle Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/common/ThemeToggle.tsx
 */
import React from 'react';
import { Theme } from '../../types';

export const ThemeToggle: React.FC<{ theme: Theme; toggleTheme: () => void }> = ({ theme, toggleTheme }) => (
    <div className="theme-toggle">
        <label htmlFor="theme-switch">
             <span className="sr-only">Dark Mode umschalten</span>
            <div className="toggle-switch-background">
                <input type="checkbox" id="theme-switch" checked={theme === 'dark'} onChange={toggleTheme} />
                <span className="slider round"></span>
            </div>
             <span>Dark Mode</span>
        </label>
    </div>
);
