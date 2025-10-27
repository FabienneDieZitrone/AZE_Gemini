/**
 * Titel: Logo Komponente
 * Version: 4.0
 * Letzte Aktualisierung: 27.10.2024
 * Autor: MP-IT
 * Datei: /src/components/common/Logo.tsx
 * Änderungen: Logo als Link zu mikropartner.de
 */

export const Logo = () => (
    <a
        href="https://www.mikropartner.de"
        target="_blank"
        rel="noopener noreferrer"
        style={{ display: 'inline-block', textDecoration: 'none' }}
        title="Zur MIKRO PARTNER Website"
    >
        <img
            src="/logos/Logo_mp_farbig3.png"
            alt="MIKRO PARTNER Logo"
            className="app-logo-img"
            style={{ height: '50px', width: 'auto', cursor: 'pointer' }}
        />
    </a>
);