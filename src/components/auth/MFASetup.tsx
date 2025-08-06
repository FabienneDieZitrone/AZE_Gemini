import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Typography,
  Box,
  Stepper,
  Step,
  StepLabel,
  Alert,
  CircularProgress,
  Paper,
  Grid,
  IconButton,
  Chip
} from '@mui/material';
import {
  QrCode2,
  ContentCopy,
  Download,
  CheckCircle,
  Warning,
  Security
} from '@mui/icons-material';
import QRCode from 'qrcode';
import { api } from '../../services/api';

interface MFASetupProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  required?: boolean;
}

interface BackupCode {
  code: string;
  used?: boolean;
}

const MFASetup: React.FC<MFASetupProps> = ({ open, onClose, onSuccess, required = false }) => {
  const [activeStep, setActiveStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // Step 1: Generate secret
  const [qrCodeUrl, setQrCodeUrl] = useState<string>('');
  const [secretKey, setSecretKey] = useState<string>('');
  const [manualKey, setManualKey] = useState<string>('');
  
  // Step 2: Verify code
  const [verificationCode, setVerificationCode] = useState<string>('');
  
  // Step 3: Backup codes
  const [backupCodes, setBackupCodes] = useState<string[]>([]);
  const [backupCodesViewed, setBackupCodesViewed] = useState(false);

  const steps = ['Generate Secret', 'Verify Setup', 'Backup Codes'];

  useEffect(() => {
    if (open && activeStep === 0) {
      generateSecret();
    }
  }, [open]);

  const generateSecret = async () => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await api.post('/api/mfa-setup.php', { action: 'generate' });
      
      if (response.data.success) {
        const { secret, qr_code, manual_entry_key } = response.data;
        
        // Generate QR code image
        const qrUrl = await QRCode.toDataURL(qr_code, {
          width: 256,
          margin: 2,
          color: {
            dark: '#000000',
            light: '#FFFFFF'
          }
        });
        
        setQrCodeUrl(qrUrl);
        setSecretKey(secret);
        setManualKey(manual_entry_key);
      } else {
        setError(response.data.error || 'Failed to generate secret');
      }
    } catch (err) {
      setError('Failed to generate MFA secret');
      console.error('MFA setup error:', err);
    } finally {
      setLoading(false);
    }
  };

  const verifyCode = async () => {
    if (verificationCode.length !== 6) {
      setError('Please enter a 6-digit code');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await api.post('/api/mfa-setup.php', {
        action: 'verify_setup',
        code: verificationCode
      });

      if (response.data.success) {
        setBackupCodes(response.data.backup_codes || []);
        setActiveStep(2);
      } else {
        setError(response.data.error || 'Invalid verification code');
      }
    } catch (err) {
      setError('Failed to verify code');
      console.error('Verification error:', err);
    } finally {
      setLoading(false);
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    // Could add a toast notification here
  };

  const downloadBackupCodes = () => {
    const content = `AZE Gemini - MFA Backup Codes
Generated: ${new Date().toLocaleString()}

IMPORTANT: Store these codes in a safe place.
Each code can only be used once.

${backupCodes.map((code, index) => `${index + 1}. ${code}`).join('\n')}

If you lose access to your authenticator app, you can use
one of these codes to sign in to your account.
`;

    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'aze-gemini-mfa-backup-codes.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    setBackupCodesViewed(true);
  };

  const handleComplete = () => {
    if (!backupCodesViewed) {
      setError('Please download or view your backup codes before continuing');
      return;
    }
    
    onSuccess();
  };

  const renderStepContent = () => {
    switch (activeStep) {
      case 0:
        return (
          <Box>
            <Typography variant="body1" gutterBottom>
              Scan this QR code with your authenticator app:
            </Typography>
            
            {loading ? (
              <Box display="flex" justifyContent="center" p={4}>
                <CircularProgress />
              </Box>
            ) : (
              <>
                <Box display="flex" justifyContent="center" my={3}>
                  <Paper elevation={3} sx={{ p: 2 }}>
                    {qrCodeUrl && (
                      <img src={qrCodeUrl} alt="MFA QR Code" style={{ display: 'block' }} />
                    )}
                  </Paper>
                </Box>

                <Typography variant="body2" color="textSecondary" gutterBottom>
                  Or enter this key manually:
                </Typography>
                
                <Paper variant="outlined" sx={{ p: 2, my: 2 }}>
                  <Box display="flex" alignItems="center" justifyContent="space-between">
                    <Typography variant="mono" sx={{ fontFamily: 'monospace' }}>
                      {manualKey}
                    </Typography>
                    <IconButton onClick={() => copyToClipboard(secretKey)} size="small">
                      <ContentCopy />
                    </IconButton>
                  </Box>
                </Paper>

                <Alert severity="info" sx={{ mt: 2 }}>
                  Supported apps: Google Authenticator, Microsoft Authenticator, Authy, 1Password
                </Alert>
              </>
            )}
          </Box>
        );

      case 1:
        return (
          <Box>
            <Typography variant="body1" gutterBottom>
              Enter the 6-digit code from your authenticator app:
            </Typography>
            
            <TextField
              fullWidth
              value={verificationCode}
              onChange={(e) => {
                const value = e.target.value.replace(/\D/g, '').slice(0, 6);
                setVerificationCode(value);
              }}
              placeholder="000000"
              inputProps={{
                maxLength: 6,
                style: { fontSize: '24px', letterSpacing: '8px', textAlign: 'center' }
              }}
              sx={{ my: 3 }}
              autoFocus
            />
            
            <Alert severity="warning">
              Make sure your device's time is synchronized correctly
            </Alert>
          </Box>
        );

      case 2:
        return (
          <Box>
            <Alert severity="success" sx={{ mb: 2 }}>
              <Typography variant="body2">
                MFA has been successfully enabled for your account!
              </Typography>
            </Alert>
            
            <Typography variant="body1" gutterBottom>
              Save these backup codes in a secure location:
            </Typography>
            
            <Paper variant="outlined" sx={{ p: 2, my: 2 }}>
              <Grid container spacing={2}>
                {backupCodes.map((code, index) => (
                  <Grid item xs={6} key={index}>
                    <Chip
                      label={`${index + 1}. ${code}`}
                      variant="outlined"
                      sx={{ fontFamily: 'monospace', width: '100%' }}
                    />
                  </Grid>
                ))}
              </Grid>
            </Paper>
            
            <Box display="flex" gap={2} justifyContent="center" my={2}>
              <Button
                variant="contained"
                startIcon={<Download />}
                onClick={downloadBackupCodes}
              >
                Download Codes
              </Button>
              <Button
                variant="outlined"
                startIcon={<ContentCopy />}
                onClick={() => copyToClipboard(backupCodes.join('\n'))}
              >
                Copy All
              </Button>
            </Box>
            
            <Alert severity="warning" sx={{ mt: 2 }}>
              Each backup code can only be used once. Store them securely!
            </Alert>
          </Box>
        );

      default:
        return null;
    }
  };

  return (
    <Dialog
      open={open}
      onClose={required ? undefined : onClose}
      maxWidth="sm"
      fullWidth
      disableEscapeKeyDown={required}
    >
      <DialogTitle>
        <Box display="flex" alignItems="center" gap={1}>
          <Security color="primary" />
          <Typography variant="h6">Set Up Two-Factor Authentication</Typography>
        </Box>
      </DialogTitle>
      
      <DialogContent>
        <Stepper activeStep={activeStep} sx={{ mb: 3 }}>
          {steps.map((label) => (
            <Step key={label}>
              <StepLabel>{label}</StepLabel>
            </Step>
          ))}
        </Stepper>
        
        {error && (
          <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
            {error}
          </Alert>
        )}
        
        {renderStepContent()}
      </DialogContent>
      
      <DialogActions>
        {!required && activeStep === 0 && (
          <Button onClick={onClose}>Cancel</Button>
        )}
        
        {activeStep === 0 && (
          <Button
            variant="contained"
            onClick={() => setActiveStep(1)}
            disabled={!secretKey}
          >
            Next
          </Button>
        )}
        
        {activeStep === 1 && (
          <>
            <Button onClick={() => setActiveStep(0)}>Back</Button>
            <Button
              variant="contained"
              onClick={verifyCode}
              disabled={loading || verificationCode.length !== 6}
            >
              Verify
            </Button>
          </>
        )}
        
        {activeStep === 2 && (
          <Button
            variant="contained"
            onClick={handleComplete}
            disabled={!backupCodesViewed}
            startIcon={<CheckCircle />}
          >
            Complete Setup
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
};

export default MFASetup;