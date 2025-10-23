/**
 * OnboardingView - Erstanmeldung neuer Mitarbeiter
 *
 * Zeigt Standort-Auswahl für neue Mitarbeiter bei der Erstanmeldung
 */
import React, { useState } from 'react';
import { GlobalSettings } from '../types';
import { Logo } from '../components/common/Logo';
import './OnboardingView.css';

interface OnboardingViewProps {
  globalSettings: GlobalSettings;
  userName: string;
  onComplete: (homeLocation: string) => Promise<void>;
}

export const OnboardingView: React.FC<OnboardingViewProps> = ({
  globalSettings,
  userName,
  onComplete
}) => {
  const [selectedLocation, setSelectedLocation] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedLocation) {
      setError('Bitte wählen Sie einen Standort aus.');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await onComplete(selectedLocation);
    } catch (err) {
      const errorMsg = err instanceof Error ? err.message : 'Fehler beim Speichern';
      setError(errorMsg);
      setIsSubmitting(false);
    }
  };

  return (
    <div className="onboarding-container">
      <div className="onboarding-card">
        <div className="onboarding-header">
          <Logo />
          <h1 className="onboarding-title">Willkommen bei MP Arbeitszeiterfassung</h1>
          <p className="onboarding-subtitle">Hallo {userName}!</p>
        </div>

        <div className="onboarding-content">
          <div className="onboarding-welcome-message">
            <h2>🎉 Willkommen im Team!</h2>
            <p>
              Bevor Sie starten können, benötigen wir noch eine Information von Ihnen:
            </p>
          </div>

          <form onSubmit={handleSubmit} className="onboarding-form">
            <div className="form-group">
              <label htmlFor="home-location" className="form-label">
                <strong>Ihr Heimat-/Stammstandort</strong>
              </label>
              <p className="form-help-text">
                Wählen Sie bitte den Standort aus, an dem Sie hauptsächlich arbeiten werden.
                Ihr Standortleiter wird Ihre Stammdaten anschließend vervollständigen.
              </p>
              <select
                id="home-location"
                value={selectedLocation}
                onChange={(e) => setSelectedLocation(e.target.value)}
                className="form-select"
                disabled={isSubmitting}
                required
              >
                <option value="">-- Bitte wählen --</option>
                {globalSettings.locations.map(loc => (
                  <option key={loc} value={loc}>{loc}</option>
                ))}
              </select>
            </div>

            {error && (
              <div className="onboarding-error" role="alert">
                ⚠️ {error}
              </div>
            )}

            <div className="onboarding-info-box">
              <h3>ℹ️ Was passiert als nächstes?</h3>
              <ul>
                <li>Sie können sofort mit der Zeiterfassung beginnen</li>
                <li>Ihr Standortleiter wird bei seiner nächsten Anmeldung benachrichtigt</li>
                <li>Der Standortleiter vervollständigt Ihre Stammdaten (Arbeitszeiten, Arbeitstage, etc.)</li>
                <li>Danach haben Sie Zugriff auf alle Funktionen</li>
              </ul>
            </div>

            <div className="onboarding-actions">
              <button
                type="submit"
                className="onboarding-submit-button"
                disabled={!selectedLocation || isSubmitting}
              >
                {isSubmitting ? 'Wird gespeichert...' : 'Weiter zur Zeiterfassung →'}
              </button>
            </div>
          </form>
        </div>

        <div className="onboarding-footer">
          <p className="onboarding-footer-text">
            Bei Fragen wenden Sie sich bitte an Ihren Standortleiter
          </p>
        </div>
      </div>
    </div>
  );
};
