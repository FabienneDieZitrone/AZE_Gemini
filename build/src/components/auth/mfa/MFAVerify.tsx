import React, { useState, useEffect } from 'react';
import {
  Box,
  Button,
  TextField,
  Typography,
  Paper,
  Alert,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  CircularProgress,
  Divider,
  Link,
  InputAdornment,
  IconButton
} from '@mui/material';
import {
  Security,
  VpnKey,
  Refresh,
  Help,
  Warning,
  CheckCircle,
  Clear
} from '@mui/icons-material';

interface MFAVerifyProps {
  userId: number;
  onVerify: (success: boolean) => void;
  onCancel: () => void;
  userName?: string;
  userEmail?: string;
  isRequired?: boolean;
  allowBackupCodes?: boolean;
}

interface MFAVerifyState {
  code: string;
  useBackupCode: boolean;
  isVerifying: boolean;
  error: string;
  attemptsRemaining: number;
  lockedUntil: string | null;
  backupCodesRemaining: number;
  showHelpDialog: boolean;
}

export const MFAVerify: React.FC<MFAVerifyProps> = ({
  userId,
  onVerify,
  onCancel,
  userName = '',
  userEmail = '',
  isRequired = false,
  allowBackupCodes = true
}) => {
  const [state, setState] = useState<MFAVerifyState>({
    code: '',
    useBackupCode: false,
    isVerifying: false,
    error: '',
    attemptsRemaining: 5,
    lockedUntil: null,
    backupCodesRemaining: 0,
    showHelpDialog: false
  });

  const [countdown, setCountdown] = useState<number>(0);

  useEffect(() => {
    // Check if user is locked out
    checkLockoutStatus();
  }, [userId]);

  useEffect(() => {
    // Countdown timer for lockout
    let timer: NodeJS.Timeout;
    if (countdown > 0) {
      timer = setInterval(() => {
        setCountdown(prev => {
          if (prev <= 1) {
            checkLockoutStatus();
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    }
    return () => clearInterval(timer);
  }, [countdown]);

  const checkLockoutStatus = async () => {
    try {
      // This would typically be an API call to check MFA status
      // For now, we'll assume no lockout initially
    } catch (error) {
      console.error('Failed to check lockout status:', error);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!state.code.trim()) {
      setState(prev => ({ ...prev, error: 'Bitte geben Sie einen Code ein' }));
      return;
    }

    if (state.useBackupCode && state.code.length !== 8) {
      setState(prev => ({ ...prev, error: 'Backup-Code muss 8 Zeichen lang sein' }));
      return;
    }

    if (!state.useBackupCode && state.code.length !== 6) {
      setState(prev => ({ ...prev, error: 'TOTP-Code muss 6 Ziffern lang sein' }));
      return;
    }

    setState(prev => ({ ...prev, isVerifying: true, error: '' }));

    try {
      const response = await fetch('/api/mfa-verify.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          userId,
          code: state.code,
          useBackup: state.useBackupCode,
          isSetupVerification: false
        })
      });

      const result = await response.json();

      if (response.ok && result.success) {
        setState(prev => ({ 
          ...prev, 
          error: '', 
          backupCodesRemaining: result.backupCodesRemaining || prev.backupCodesRemaining 
        }));
        onVerify(true);
      } else {
        setState(prev => ({
          ...prev,
          error: result.message || 'Ungültiger Code',
          attemptsRemaining: Math.max(0, (result.failed_attempts ? 5 - result.failed_attempts : prev.attemptsRemaining - 1)),
          lockedUntil: result.locked_until || null
        }));

        if (result.locked_until) {
          const lockoutEnd = new Date(result.locked_until).getTime();
          const now = Date.now();
          setCountdown(Math.max(0, Math.ceil((lockoutEnd - now) / 1000)));
        }

        // Clear the input for security
        setState(prev => ({ ...prev, code: '' }));
      }
    } catch (error) {
      setState(prev => ({ 
        ...prev, 
        error: 'Verbindungsfehler. Bitte versuchen Sie es erneut.' 
      }));
      console.error('MFA verification error:', error);
    } finally {
      setState(prev => ({ ...prev, isVerifying: false }));
    }
  };

  const toggleBackupMode = () => {
    setState(prev => ({
      ...prev,
      useBackupCode: !prev.useBackupCode,
      code: '',
      error: ''
    }));
  };

  const formatCountdown = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  };

  const isLocked = state.lockedUntil && new Date(state.lockedUntil).getTime() > Date.now();

  return (
    <>
      <Dialog
        open={true}
        onClose={isRequired ? undefined : onCancel}
        maxWidth="sm"
        fullWidth
        disableEscapeKeyDown={isRequired}
      >
        <DialogTitle>
          <Box sx={{ display: 'flex', alignItems: 'center' }}>
            <Security sx={{ mr: 2, color: 'primary.main' }} />
            <Typography variant="h6" component="div" sx={{ flexGrow: 1 }}>
              Zwei-Faktor-Authentifizierung
            </Typography>
            {!isRequired && (
              <IconButton
                edge="end"
                onClick={onCancel}
                disabled={state.isVerifying}
              >
                <Clear />
              </IconButton>
            )}
          </Box>
        </DialogTitle>

        <DialogContent>
          {userName && (
            <Typography variant="body2" color="textSecondary" sx={{ mb: 2 }}>
              Angemeldet als: <strong>{userName}</strong>
              {userEmail && ` (${userEmail})`}
            </Typography>
          )}

          {isLocked && countdown > 0 ? (
            <Alert severity="error" sx={{ mb: 3 }}>
              <Typography variant="subtitle2" gutterBottom>
                Konto temporär gesperrt
              </Typography>
              <Typography variant="body2">
                Zu viele fehlgeschlagene Versuche. Versuchen Sie es in {formatCountdown(countdown)} erneut.
              </Typography>
            </Alert>
          ) : (
            <form onSubmit={handleSubmit}>
              <Typography variant="body1" paragraph>
                {state.useBackupCode
                  ? 'Geben Sie einen 8-stelligen Backup-Code ein:'
                  : 'Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein:'
                }
              </Typography>

              <TextField
                fullWidth
                value={state.code}
                onChange={(e) => setState(prev => ({
                  ...prev,
                  code: state.useBackupCode 
                    ? e.target.value.toUpperCase().replace(/[^A-F0-9]/g, '').slice(0, 8)
                    : e.target.value.replace(/\D/g, '').slice(0, 6)
                }))}
                placeholder={state.useBackupCode ? 'XXXXXXXX' : '000000'}
                autoComplete="one-time-code"
                disabled={state.isVerifying}
                inputProps={{
                  maxLength: state.useBackupCode ? 8 : 6,
                  style: {
                    textAlign: 'center',
                    fontSize: '1.5rem',
                    letterSpacing: state.useBackupCode ? '0.2rem' : '0.5rem',
                    fontFamily: 'monospace'
                  }
                }}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      {state.useBackupCode ? <VpnKey /> : <Security />}
                    </InputAdornment>
                  )
                }}
                sx={{ mb: 2 }}
                autoFocus
                required
              />

              {state.error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                  {state.error}
                  {state.attemptsRemaining > 0 && state.attemptsRemaining < 5 && (
                    <Typography variant="body2" sx={{ mt: 1 }}>
                      Noch {state.attemptsRemaining} Versuche verbleibend
                    </Typography>
                  )}
                </Alert>
              )}

              {!state.useBackupCode && state.backupCodesRemaining > 0 && (
                <Typography variant="body2" color="textSecondary" sx={{ mb: 2 }}>
                  {state.backupCodesRemaining} Backup-Code(s) verfügbar
                </Typography>
              )}
            </form>
          )}

          <Divider sx={{ my: 2 }} />

          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            {allowBackupCodes && !isLocked && (
              <Button
                variant="text"
                size="small"
                onClick={toggleBackupMode}
                disabled={state.isVerifying}
              >
                {state.useBackupCode ? 'Authenticator-Code verwenden' : 'Backup-Code verwenden'}
              </Button>
            )}

            <Button
              variant="text"
              size="small"
              startIcon={<Help />}
              onClick={() => setState(prev => ({ ...prev, showHelpDialog: true }))}
            >
              Hilfe
            </Button>
          </Box>
        </DialogContent>

        <DialogActions>
          {!isRequired && (
            <Button
              onClick={onCancel}
              disabled={state.isVerifying}
            >
              Abbrechen
            </Button>
          )}
          <Button
            type="submit"
            variant="contained"
            onClick={handleSubmit}
            disabled={
              state.isVerifying ||
              isLocked ||
              !state.code ||
              (state.useBackupCode ? state.code.length !== 8 : state.code.length !== 6)
            }
          >
            {state.isVerifying ? (
              <>
                <CircularProgress size={20} sx={{ mr: 1 }} />
                Überprüfung...
              </>
            ) : (
              'Verifizieren'
            )}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Help Dialog */}
      <Dialog
        open={state.showHelpDialog}
        onClose={() => setState(prev => ({ ...prev, showHelpDialog: false }))}
        maxWidth="md"
      >
        <DialogTitle>
          <Box sx={{ display: 'flex', alignItems: 'center' }}>
            <Help sx={{ mr: 2 }} />
            Hilfe zur Zwei-Faktor-Authentifizierung
          </Box>
        </DialogTitle>
        <DialogContent>
          <Typography variant="h6" gutterBottom>
            Authenticator-App Code
          </Typography>
          <Typography paragraph>
            • Öffnen Sie Ihre Authenticator-App (Google Authenticator, Microsoft Authenticator, etc.)
          </Typography>
          <Typography paragraph>
            • Suchen Sie den Eintrag für "AZE Zeiterfassung"
          </Typography>
          <Typography paragraph>
            • Geben Sie den aktuell angezeigten 6-stelligen Code ein
          </Typography>
          <Typography paragraph>
            • Der Code ändert sich alle 30 Sekunden
          </Typography>

          <Divider sx={{ my: 2 }} />

          <Typography variant="h6" gutterBottom>
            Backup-Codes
          </Typography>
          <Typography paragraph>
            • Verwenden Sie einen Backup-Code, wenn Sie keinen Zugriff auf Ihre Authenticator-App haben
          </Typography>
          <Typography paragraph>
            • Jeder Backup-Code kann nur einmal verwendet werden
          </Typography>
          <Typography paragraph>
            • Backup-Codes sind 8-stellige Kombinationen aus Buchstaben und Zahlen
          </Typography>

          <Divider sx={{ my: 2 }} />

          <Typography variant="h6" gutterBottom>
            Probleme?
          </Typography>
          <Typography paragraph>
            • Stellen Sie sicher, dass die Zeit auf Ihrem Gerät korrekt ist
          </Typography>
          <Typography paragraph>
            • Bei wiederholten Fehlern wird Ihr Konto temporär gesperrt
          </Typography>
          <Typography paragraph>
            • Kontaktieren Sie den Administrator, wenn Sie keinen Zugriff mehr haben
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button
            onClick={() => setState(prev => ({ ...prev, showHelpDialog: false }))}
            variant="contained"
          >
            Verstanden
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default MFAVerify;