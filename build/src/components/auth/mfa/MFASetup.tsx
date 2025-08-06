import React, { useState, useEffect } from 'react';
import {
  Box,
  Button,
  TextField,
  Typography,
  Paper,
  Alert,
  Chip,
  Grid,
  Divider,
  Card,
  CardContent,
  IconButton,
  Tooltip,
  Stepper,
  Step,
  StepLabel,
  StepContent,
  CircularProgress
} from '@mui/material';
import {
  QrCode2,
  ContentCopy,
  Security,
  CheckCircle,
  Warning,
  Visibility,
  VisibilityOff,
  Download,
  Print
} from '@mui/icons-material';
import QRCode from 'qrcode';

interface MFASetupProps {
  userId: number;
  onComplete: (success: boolean) => void;
  onCancel: () => void;
  userEmail?: string;
  userName?: string;
}

interface MFASetupData {
  qrCodeUrl: string;
  secret: string;
  backupCodes: string[];
  issuer: string;
  accountName: string;
}

export const MFASetup: React.FC<MFASetupProps> = ({ 
  userId, 
  onComplete, 
  onCancel,
  userEmail = '',
  userName = ''
}) => {
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string>('');
  const [setupData, setSetupData] = useState<MFASetupData | null>(null);
  const [qrCodeDataUrl, setQrCodeDataUrl] = useState<string>('');
  const [verificationCode, setVerificationCode] = useState<string>('');
  const [activeStep, setActiveStep] = useState<number>(0);
  const [showSecret, setShowSecret] = useState<boolean>(false);
  const [copied, setCopied] = useState<{ [key: string]: boolean }>({});
  const [verifying, setVerifying] = useState<boolean>(false);
  const [backupCodesSaved, setBackupCodesSaved] = useState<boolean>(false);

  const steps = [
    'MFA einrichten',
    'Authenticator konfigurieren',
    'Backup-Codes speichern',
    'Verifizierung'
  ];

  useEffect(() => {
    initializeMFA();
  }, [userId]);

  const initializeMFA = async () => {
    try {
      setLoading(true);
      setError('');

      const response = await fetch('/api/mfa-setup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ userId })
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Setup failed');
      }

      const data = await response.json();
      setSetupData(data);

      // Generate QR Code as data URL
      const qrUrl = await QRCode.toDataURL(data.qrCodeUrl, {
        errorCorrectionLevel: 'M',
        type: 'image/png',
        quality: 0.92,
        margin: 1,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        },
        width: 256
      });
      setQrCodeDataUrl(qrUrl);

      setActiveStep(1);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to initialize MFA setup');
      console.error('MFA setup initialization error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleVerify = async () => {
    if (verificationCode.length !== 6) {
      setError('Please enter a 6-digit verification code');
      return;
    }

    try {
      setVerifying(true);
      setError('');

      const response = await fetch('/api/mfa-verify.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          userId,
          code: verificationCode,
          useBackup: false,
          isSetupVerification: true
        })
      });

      const result = await response.json();

      if (response.ok && result.success) {
        setActiveStep(4);
        setTimeout(() => onComplete(true), 2000);
      } else {
        setError(result.message || 'Invalid verification code. Please try again.');
      }
    } catch (err) {
      setError('Verification failed. Please check your connection and try again.');
      console.error('MFA verification error:', err);
    } finally {
      setVerifying(false);
    }
  };

  const copyToClipboard = async (text: string, key: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied({ ...copied, [key]: true });
      setTimeout(() => {
        setCopied(prev => ({ ...prev, [key]: false }));
      }, 2000);
    } catch (err) {
      console.error('Failed to copy to clipboard:', err);
    }
  };

  const downloadBackupCodes = () => {
    if (!setupData) return;

    const codesText = [
      'AZE Zeiterfassung - MFA Backup Codes',
      `Account: ${setupData.accountName}`,
      `Generated: ${new Date().toLocaleString()}`,
      '',
      'WICHTIG: Bewahren Sie diese Codes sicher auf!',
      'Jeder Code kann nur einmal verwendet werden.',
      '',
      'Backup Codes:',
      '================',
      ...setupData.backupCodes.map((code, index) => `${index + 1}. ${code}`),
      '',
      'Diese Codes können verwendet werden, wenn Sie keinen Zugriff auf Ihre Authenticator-App haben.'
    ].join('\n');

    const blob = new Blob([codesText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `aze-mfa-backup-codes-${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    setBackupCodesSaved(true);
  };

  const printBackupCodes = () => {
    if (!setupData) return;

    const printContent = `
      <html>
        <head>
          <title>AZE MFA Backup Codes</title>
          <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
            .codes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
            .code { padding: 10px; border: 1px solid #ccc; text-align: center; font-weight: bold; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; }
          </style>
        </head>
        <body>
          <div class="header">
            <h1>AZE Zeiterfassung</h1>
            <h2>MFA Backup Codes</h2>
            <p>Account: ${setupData.accountName}</p>
            <p>Generated: ${new Date().toLocaleString()}</p>
          </div>
          <div class="warning">
            <strong>WICHTIG:</strong> Bewahren Sie diese Codes sicher auf! Jeder Code kann nur einmal verwendet werden.
          </div>
          <div class="codes">
            ${setupData.backupCodes.map((code, index) => `
              <div class="code">${index + 1}. ${code}</div>
            `).join('')}
          </div>
          <p><small>Diese Codes können verwendet werden, wenn Sie keinen Zugriff auf Ihre Authenticator-App haben.</small></p>
        </body>
      </html>
    `;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(printContent);
      printWindow.document.close();
      printWindow.print();
      printWindow.close();
    }
    setBackupCodesSaved(true);
  };

  if (loading) {
    return (
      <Paper elevation={3} sx={{ p: 4, maxWidth: 600, mx: 'auto', textAlign: 'center' }}>
        <CircularProgress sx={{ mb: 2 }} />
        <Typography>MFA-Setup wird initialisiert...</Typography>
      </Paper>
    );
  }

  return (
    <Paper elevation={3} sx={{ p: 4, maxWidth: 800, mx: 'auto' }}>
      <Box sx={{ display: 'flex', alignItems: 'center', mb: 3 }}>
        <Security sx={{ mr: 2, color: 'primary.main', fontSize: 32 }} />
        <Typography variant="h4" component="h1">
          Zwei-Faktor-Authentifizierung einrichten
        </Typography>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}

      <Stepper activeStep={activeStep} orientation="vertical">
        <Step>
          <StepLabel>MFA einrichten</StepLabel>
          <StepContent>
            <Typography paragraph>
              Sie richten die Zwei-Faktor-Authentifizierung für Ihr Konto ein. 
              Dies erhöht die Sicherheit Ihres Zugangs erheblich.
            </Typography>
            <Button 
              variant="contained" 
              onClick={() => setActiveStep(1)}
              disabled={!setupData}
            >
              Weiter
            </Button>
          </StepContent>
        </Step>

        <Step>
          <StepLabel>Authenticator-App konfigurieren</StepLabel>
          <StepContent>
            <Grid container spacing={3}>
              <Grid item xs={12} md={6}>
                <Typography variant="h6" gutterBottom>
                  1. QR-Code scannen
                </Typography>
                <Typography paragraph variant="body2">
                  Öffnen Sie Ihre Authenticator-App (z.B. Google Authenticator, 
                  Microsoft Authenticator) und scannen Sie diesen QR-Code:
                </Typography>
                {qrCodeDataUrl && (
                  <Box sx={{ textAlign: 'center', mb: 2 }}>
                    <img 
                      src={qrCodeDataUrl} 
                      alt="MFA QR Code" 
                      style={{ maxWidth: '100%', height: 'auto' }} 
                    />
                  </Box>
                )}
              </Grid>

              <Grid item xs={12} md={6}>
                <Typography variant="h6" gutterBottom>
                  2. Oder manuell eingeben
                </Typography>
                <Typography paragraph variant="body2">
                  Falls der QR-Code nicht funktioniert, geben Sie diesen Code manuell ein:
                </Typography>
                
                <Card variant="outlined" sx={{ mb: 2 }}>
                  <CardContent>
                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                      <Typography 
                        variant="body1" 
                        sx={{ 
                          fontFamily: 'monospace', 
                          wordBreak: 'break-all',
                          filter: showSecret ? 'none' : 'blur(4px)',
                          transition: 'filter 0.2s'
                        }}
                      >
                        {setupData?.secret}
                      </Typography>
                      <Box>
                        <Tooltip title={showSecret ? 'Secret verbergen' : 'Secret anzeigen'}>
                          <IconButton
                            size="small"
                            onClick={() => setShowSecret(!showSecret)}
                          >
                            {showSecret ? <VisibilityOff /> : <Visibility />}
                          </IconButton>
                        </Tooltip>
                        <Tooltip title="In Zwischenablage kopieren">
                          <IconButton
                            size="small"
                            onClick={() => copyToClipboard(setupData?.secret || '', 'secret')}
                          >
                            <ContentCopy />
                          </IconButton>
                        </Tooltip>
                      </Box>
                    </Box>
                    {copied.secret && (
                      <Typography variant="caption" color="success.main">
                        In Zwischenablage kopiert!
                      </Typography>
                    )}
                  </CardContent>
                </Card>
              </Grid>
            </Grid>

            <Box sx={{ mt: 2 }}>
              <Button 
                variant="contained" 
                onClick={() => setActiveStep(2)}
                sx={{ mr: 1 }}
              >
                Weiter
              </Button>
              <Button onClick={() => setActiveStep(0)}>
                Zurück
              </Button>
            </Box>
          </StepContent>
        </Step>

        <Step>
          <StepLabel>Backup-Codes speichern</StepLabel>
          <StepContent>
            <Alert severity="warning" sx={{ mb: 3 }}>
              <Typography variant="subtitle2" gutterBottom>
                Wichtig: Speichern Sie diese Backup-Codes sicher!
              </Typography>
              <Typography variant="body2">
                Diese Codes können verwendet werden, wenn Sie keinen Zugriff auf Ihre 
                Authenticator-App haben. Jeder Code kann nur einmal verwendet werden.
              </Typography>
            </Alert>

            <Grid container spacing={2} sx={{ mb: 3 }}>
              {setupData?.backupCodes.map((code, index) => (
                <Grid item xs={6} sm={4} md={3} key={index}>
                  <Card variant="outlined">
                    <CardContent sx={{ textAlign: 'center', py: 1 }}>
                      <Typography variant="body2" sx={{ fontFamily: 'monospace' }}>
                        {code}
                      </Typography>
                    </CardContent>
                  </Card>
                </Grid>
              ))}
            </Grid>

            <Box sx={{ mb: 3 }}>
              <Button
                variant="outlined"
                startIcon={<Download />}
                onClick={downloadBackupCodes}
                sx={{ mr: 1 }}
              >
                Als Datei speichern
              </Button>
              <Button
                variant="outlined"
                startIcon={<Print />}
                onClick={printBackupCodes}
              >
                Drucken
              </Button>
            </Box>

            {backupCodesSaved && (
              <Alert severity="success" sx={{ mb: 2 }}>
                <Typography variant="body2">
                  Backup-Codes wurden gespeichert. Sie können nun mit der Verifizierung fortfahren.
                </Typography>
              </Alert>
            )}

            <Box>
              <Button 
                variant="contained" 
                onClick={() => setActiveStep(3)}
                disabled={!backupCodesSaved}
                sx={{ mr: 1 }}
              >
                Zur Verifizierung
              </Button>
              <Button onClick={() => setActiveStep(1)}>
                Zurück
              </Button>
            </Box>
          </StepContent>
        </Step>

        <Step>
          <StepLabel>Verifizierung</StepLabel>
          <StepContent>
            <Typography paragraph>
              Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein, 
              um die Einrichtung abzuschließen:
            </Typography>

            <TextField
              value={verificationCode}
              onChange={(e) => setVerificationCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="000000"
              inputProps={{
                maxLength: 6,
                style: { 
                  textAlign: 'center', 
                  fontSize: '1.5rem', 
                  letterSpacing: '0.5rem',
                  fontFamily: 'monospace'
                }
              }}
              fullWidth
              sx={{ mb: 3 }}
              autoFocus
              disabled={verifying}
            />

            <Box>
              <Button
                variant="contained"
                onClick={handleVerify}
                disabled={verificationCode.length !== 6 || verifying}
                sx={{ mr: 1 }}
              >
                {verifying ? (
                  <>
                    <CircularProgress size={20} sx={{ mr: 1 }} />
                    Wird überprüft...
                  </>
                ) : (
                  'Verifizieren & Aktivieren'
                )}
              </Button>
              <Button onClick={() => setActiveStep(2)} disabled={verifying}>
                Zurück
              </Button>
            </Box>
          </StepContent>
        </Step>

        <Step>
          <StepLabel>Abgeschlossen</StepLabel>
          <StepContent>
            <Alert severity="success" sx={{ mb: 2 }}>
              <Box sx={{ display: 'flex', alignItems: 'center' }}>
                <CheckCircle sx={{ mr: 1 }} />
                <Typography>
                  Zwei-Faktor-Authentifizierung wurde erfolgreich eingerichtet!
                </Typography>
              </Box>
            </Alert>
            <Typography paragraph>
              Ihr Konto ist jetzt mit MFA geschützt. Bei der nächsten Anmeldung 
              werden Sie nach dem zusätzlichen Sicherheitscode gefragt.
            </Typography>
          </StepContent>
        </Step>
      </Stepper>

      <Divider sx={{ my: 3 }} />

      <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
        <Button onClick={onCancel} variant="outlined" disabled={verifying || activeStep === 4}>
          Abbrechen
        </Button>
        {activeStep === 4 && (
          <Button onClick={() => onComplete(true)} variant="contained">
            Fertig
          </Button>
        )}
      </Box>
    </Paper>
  );
};

export default MFASetup;