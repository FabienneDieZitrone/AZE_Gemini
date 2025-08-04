import { useState, useCallback } from 'react';

interface MFAState {
  isEnabled: boolean;
  isSetup: boolean;
  backupCodesRemaining: number;
}

interface UseMFAReturn {
  mfaState: MFAState;
  setupMFA: () => Promise<void>;
  verifyMFA: (code: string, useBackup?: boolean) => Promise<boolean>;
  disableMFA: () => Promise<void>;
  regenerateBackupCodes: () => Promise<string[]>;
}

export const useMFA = (): UseMFAReturn => {
  const [mfaState, setMFAState] = useState<MFAState>({
    isEnabled: false,
    isSetup: false,
    backupCodesRemaining: 0
  });

  const setupMFA = useCallback(async () => {
    try {
      // Call your API to setup MFA
      const response = await fetch('/api/mfa/setup', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (response.ok) {
        setMFAState(prev => ({
          ...prev,
          isEnabled: true,
          isSetup: true,
          backupCodesRemaining: 10
        }));
      }
    } catch (error) {
      console.error('MFA setup failed:', error);
      throw error;
    }
  }, []);

  const verifyMFA = useCallback(async (code: string, useBackup = false): Promise<boolean> => {
    try {
      const response = await fetch('/api/mfa/verify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({
          code,
          useBackup
        })
      });

      if (response.ok) {
        const result = await response.json();
        if (useBackup && result.success) {
          setMFAState(prev => ({
            ...prev,
            backupCodesRemaining: prev.backupCodesRemaining - 1
          }));
        }
        return result.success;
      }

      return false;
    } catch (error) {
      console.error('MFA verification failed:', error);
      return false;
    }
  }, []);

  const disableMFA = useCallback(async () => {
    try {
      const response = await fetch('/api/mfa/disable', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (response.ok) {
        setMFAState({
          isEnabled: false,
          isSetup: false,
          backupCodesRemaining: 0
        });
      }
    } catch (error) {
      console.error('MFA disable failed:', error);
      throw error;
    }
  }, []);

  const regenerateBackupCodes = useCallback(async (): Promise<string[]> => {
    try {
      const response = await fetch('/api/mfa/regenerate-backup-codes', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (response.ok) {
        const result = await response.json();
        setMFAState(prev => ({
          ...prev,
          backupCodesRemaining: 10
        }));
        return result.backupCodes;
      }

      throw new Error('Failed to regenerate backup codes');
    } catch (error) {
      console.error('Backup codes regeneration failed:', error);
      throw error;
    }
  }, []);

  return {
    mfaState,
    setupMFA,
    verifyMFA,
    disableMFA,
    regenerateBackupCodes
  };
};