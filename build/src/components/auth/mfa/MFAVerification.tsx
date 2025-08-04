import React, { useState } from 'react';

interface MFAVerificationProps {
  onVerify: (code: string, useBackup?: boolean) => Promise<boolean>;
  onCancel: () => void;
  allowBackupCodes?: boolean;
}

export const MFAVerification: React.FC<MFAVerificationProps> = ({
  onVerify,
  onCancel,
  allowBackupCodes = true
}) => {
  const [code, setCode] = useState('');
  const [isBackupMode, setIsBackupMode] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsVerifying(true);
    setError('');

    try {
      const success = await onVerify(code, isBackupMode);
      if (!success) {
        setError(isBackupMode ? 'Ungültiger Backup-Code' : 'Ungültiger Authentifizierungscode');
      }
    } catch (err) {
      setError('Fehler bei der Verifizierung. Bitte versuchen Sie es erneut.');
    } finally {
      setIsVerifying(false);
    }
  };

  const toggleBackupMode = () => {
    setIsBackupMode(!isBackupMode);
    setCode('');
    setError('');
  };

  return (
    <div className="mfa-verification">
      <div className="mfa-modal">
        <h3>Zwei-Faktor-Authentifizierung</h3>
        
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>
              {isBackupMode ? 'Backup-Code eingeben:' : 'Authentifizierungscode eingeben:'}
            </label>
            <input
              type="text"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              maxLength={isBackupMode ? 8 : 6}
              placeholder={isBackupMode ? 'XXXXXXXX' : '000000'}
              autoComplete="one-time-code"
              disabled={isVerifying}
              required
            />
          </div>

          {error && <div className="error-message">{error}</div>}

          <div className="button-group">
            <button
              type="submit"
              disabled={isVerifying || code.length < (isBackupMode ? 8 : 6)}
            >
              {isVerifying ? 'Verifiziere...' : 'Verifizieren'}
            </button>
            <button type="button" onClick={onCancel} disabled={isVerifying}>
              Abbrechen
            </button>
          </div>
        </form>

        {allowBackupCodes && (
          <div className="backup-option">
            <button
              type="button"
              onClick={toggleBackupMode}
              className="link-button"
              disabled={isVerifying}
            >
              {isBackupMode ? 'Authenticator-Code verwenden' : 'Backup-Code verwenden'}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};