/**
 * Titel: Anmelde-Seite (BFF-Architektur)
 * Version: 2.1
 * Letzte Aktualisierung: 27.10.2025
 * Autor: MP-IT
 * Datei: /src/views/SignInPage.tsx
 * Beschreibung: Komponente zur Anzeige der Anmeldeseite mit Accessibility-Features und Tooltips. Der Login-Button leitet zu einem serverseitigen PHP-Endpunkt um, der den OAuth-Flow startet.
 */
import React, { useState } from 'react';
import { Logo } from '../components/common/Logo';
import { LoadingSpinner } from '../components/common/LoadingSpinner';

export const SignInPage: React.FC = () => {
    const [isLoginInProgress, setIsLoginInProgress] = useState(false);

    const handleLogin = () => {
        if (isLoginInProgress) {
            return;
        }

        try {
            setIsLoginInProgress(true);
            // Leite den Benutzer zum Backend-Endpunkt, der den OAuth-Flow mit Microsoft startet.
            // Das Backend kümmert sich um den Rest.
            window.location.href = '/api/auth-start.php';
        } catch (error) {
            console.error('Login navigation failed:', error);
            setIsLoginInProgress(false);
            // Bei Fehler: Button wieder aktivieren, damit Benutzer erneut versuchen kann
        }
    };

    return (
        <div className="login-content">
            <Logo />
            <h2>Willkommen zur Arbeitszeiterfassung</h2>
            <p>
                Bitte melden Sie sich mit Ihrer
                <br />
                <img
                    src="/logos/Logo_mp_farbig3.png"
                    alt="MIKRO PARTNER"
                    className="inline-logo"
                />{' '}
                <span
                    id="email-tooltip"
                    className="tooltip-hint"
                    title="Lotte Musterfrau → lmusterfrau@mikropartner.de"
                    aria-label="Beispiel: Lotte Musterfrau wird zu lmusterfrau@mikropartner.de"
                    tabIndex={0}
                >
                    Emailadresse
                </span>
                <br />
                und Ihrem{' '}
                <span
                    id="password-tooltip"
                    className="tooltip-hint"
                    title="Nutzen Sie bitte dasselbe Passwort, das Sie auch für die Windows Anmeldung, für Outlook, Teams, den Jobrouter und das Ticketsystem verwenden."
                    aria-label="Verwenden Sie Ihr Windows-Passwort"
                    style={{ whiteSpace: 'nowrap' }}
                    tabIndex={0}
                >
                    Windows Passwort
                </span>{' '}
                an, um fortzufahren.
            </p>
            <button onClick={handleLogin} className="action-button login-button" disabled={isLoginInProgress}>
                {isLoginInProgress ? <LoadingSpinner /> : 'Mit Ihrer Emailadresse anmelden'}
            </button>
        </div>
    );
};