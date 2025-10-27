/**
 * Titel: Anmelde-Seite (BFF-Architektur)
 * Version: 2.0
 * Letzte Aktualisierung: 18.07.2025
 * Autor: MP-IT
 * Datei: /src/views/SignInPage.tsx
 * Beschreibung: Komponente zur Anzeige der Anmeldeseite. Der Login-Button leitet nun zu einem serverseitigen PHP-Endpunkt um, der den OAuth-Flow startet.
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
        setIsLoginInProgress(true);
        // Leite den Benutzer zum Backend-Endpunkt, der den OAuth-Flow mit Microsoft startet.
        // Das Backend kümmert sich um den Rest.
        window.location.href = '/api/auth-start.php';
    };

    return (
        <div className="login-content">
            <Logo />
            <h2>Willkommen zur Arbeitszeiterfassung</h2>
            <p style={{ lineHeight: '1.8' }}>
                Bitte melden Sie sich mit Ihrer
                <br />
                <img
                    src="/logos/Logo_mp_farbig3.png"
                    alt="MIKRO PARTNER"
                    style={{
                        height: '25px',
                        width: 'auto',
                        verticalAlign: 'middle',
                        display: 'inline-block',
                        margin: '0.25rem'
                    }}
                />{' '}
                <span title="Lotte Musterfrau --> lmusterfrau@mikropartner.de" style={{ cursor: 'help', textDecoration: 'underline dotted' }}>
                    Emailadresse
                </span>
                <br />
                und Ihrem{' '}
                <span title="Nutzen Sie bitte das selbe Passwort, dass Sie auch für die Windows Anmeldung, für Outlook, Teams, den Jobrouter und das Ticketsystem verwenden." style={{ cursor: 'help', textDecoration: 'underline dotted', whiteSpace: 'nowrap' }}>
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