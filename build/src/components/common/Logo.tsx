/**
 * Titel: Logo Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/common/Logo.tsx
 */

export const Logo = () => (
    <svg width="50" height="50" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" className="app-logo-svg">
        <rect width="50" height="50" rx="8" className="logo-bg"/>
        <text x="50%" y="50%" dominantBaseline="middle" textAnchor="middle" fontFamily="Arial, sans-serif" fontSize="24" fontWeight="bold" className="logo-mp-text">
            MP
        </text>
    </svg>
);