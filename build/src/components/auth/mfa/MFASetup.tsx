import React, { useState, useEffect } from 'react';
import { Box, Button, TextField, Typography, Paper, Alert, Chip } from '@mui/material';
import { QrCode2, ContentCopy, Security } from '@mui/icons-material';
import QRCode from 'qrcode';
import { api } from '../../../../api';

interface MFASetupProps {
  userId: number;
  onComplete: () => void;
  onCancel: () => void;
}

export const MFASetup: React.FC<MFASetupProps> = ({ userId, onComplete, onCancel }) => {
  const [qrCodeUrl, setQrCodeUrl] = useState<string>('');
  const [secret, setSecret] = useState<string>('');
  const [backupCodes, setBackupCodes] = useState<string[]>([]);
  const [verificationCode, setVerificationCode] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string>('');
  const [step, setStep] = useState<'setup' | 'verify'>('setup');
  const [copied, setCopied] = useState<boolean>(false);

  useEffect(() => {
    initializeMFA();
  }, [userId]);

  const initializeMFA = async () => {
    try {
      setLoading(true);
      const response = await api.setupMFA(userId);
      
      // Generate QR Code
      const qrUrl = await QRCode.toDataURL(response.qrCodeUrl);
      setQrCodeUrl(qrUrl);
      setSecret(response.secret);
      setBackupCodes(response.backupCodes);
      setError('');
    } catch (err) {
      setError('Fehler beim Einrichten der Zwei-Faktor-Authentifizierung');
      console.error('MFA setup error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleVerify = async () => {
    if (verificationCode.length !== 6) {
      setError('Bitte geben Sie einen 6-stelligen Code ein');
      return;
    }

    try {
      setLoading(true);
      await api.verifyMFA(userId, verificationCode);
      onComplete();
    } catch (err) {
      setError('Ungültiger Code. Bitte versuchen Sie es erneut.');
    } finally {
      setLoading(false);
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const copyBackupCodes = () => {
    const codesText = backupCodes.join('\n');
    copyToClipboard(codesText);
  };

  if (loading && step === 'setup') {
    return <Typography>Zwei-Faktor-Authentifizierung wird eingerichtet...</Typography>;
  }

  return (
    <Paper elevation={3} sx={{ p: 4, maxWidth: 600, mx: 'auto' }}>
      <Box sx={{ display: 'flex', alignItems: 'center', mb: 3 }}>
        <Security sx={{ mr: 2, color: 'primary.main' }} />
        <Typography variant="h5">Zwei-Faktor-Authentifizierung einrichten</Typography>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {step === 'setup' && (
        <>
          <Typography variant="body1" paragraph>
            Scannen Sie den QR-Code mit Ihrer Authenticator-App (z.B. Google Authenticator, Microsoft Authenticator):
          </Typography>

          <Box sx={{ textAlign: 'center', my: 3 }}>
            {qrCodeUrl && (
              <img src={qrCodeUrl} alt="MFA QR Code" style={{ maxWidth: 256 }} />
            )}
          </Box>

          <Typography variant="body2" sx={{ mb: 2 }}>
            Oder geben Sie diesen Code manuell ein:
          </Typography>

          <Box sx={{ display: 'flex', alignItems: 'center', mb: 3 }}>
            <TextField
              value={secret}
              InputProps={{ readOnly: true }}
              size="small"
              fullWidth
              sx={{ mr: 1 }}
            />
            <Button
              startIcon={<ContentCopy />}
              onClick={() => copyToClipboard(secret)}
              variant="outlined"
              size="small"
            >
              {copied ? 'Kopiert!' : 'Kopieren'}
            </Button>
          </Box>

          <Alert severity="warning" sx={{ mb: 3 }}>
            <Typography variant="subtitle2" gutterBottom>
              Wichtig: Speichern Sie diese Backup-Codes sicher!
            </Typography>
            <Typography variant="body2" paragraph>
              Sie können diese Codes verwenden, falls Sie keinen Zugriff auf Ihre Authenticator-App haben:
            </Typography>
            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 2 }}>
              {backupCodes.map((code, index) => (
                <Chip key={index} label={code} size="small" />
              ))}
            </Box>
            <Button
              size="small"
              startIcon={<ContentCopy />}
              onClick={copyBackupCodes}
              variant="outlined"
            >
              Alle Codes kopieren
            </Button>
          </Alert>

          <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
            <Button onClick={onCancel} variant="outlined">
              Abbrechen
            </Button>
            <Button
              onClick={() => setStep('verify')}
              variant="contained"
              color="primary"
            >
              Weiter zur Verifizierung
            </Button>
          </Box>
        </>
      )}

      {step === 'verify' && (
        <>
          <Typography variant="body1" paragraph>
            Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein:
          </Typography>

          <TextField
            value={verificationCode}
            onChange={(e) => setVerificationCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
            placeholder="000000"
            inputProps={{
              maxLength: 6,
              style: { textAlign: 'center', fontSize: '1.5rem', letterSpacing: '0.5rem' }
            }}
            fullWidth
            sx={{ mb: 3 }}
            autoFocus
          />

          <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
            <Button onClick={() => setStep('setup')} variant="outlined">
              Zurück
            </Button>
            <Button
              onClick={handleVerify}
              variant="contained"
              color="primary"
              disabled={verificationCode.length !== 6 || loading}
            >
              {loading ? 'Wird überprüft...' : 'Verifizieren'}
            </Button>
          </Box>
        </>
      )}
    </Paper>
  );
};