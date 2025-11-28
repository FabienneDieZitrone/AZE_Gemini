#!/usr/bin/env python3
"""
Webspace Cleanup Script
Entfernt alte/nicht benötigte Dateien vom Webspace
"""

from ftplib import FTP_TLS
import sys

# FTP Credentials - aus Umgebungsvariablen laden
import os
FTP_HOST = os.environ.get('FTP_HOST', 'wp10454681.server-he.de')
FTP_USER = os.environ.get('FTP_USER', '')
FTP_PASS = os.environ.get('FTP_PASS', '')

# Dateien die DEFINITIV gelöscht werden sollen (Test/Debug/Backup)
API_FILES_TO_DELETE = [
    # Test-Dateien
    'test-approval-validation.php',
    'test-auth.php',
    'test-create-session.php',
    'test-csrf-protection.php',
    'test-db-schema.php',
    'test-production-schema.php',
    'test-rate-limiting.php',
    'test-running-timer.php',
    'test-security-suite.php',
    'test-session-flow.php',
    'test-simple.php',
    'test-stop.php',
    'test-timer-issue29.php',
    'test-timer-start.php',
    'test-validation.php',
    'test_db_simple.php',
    'test_new_db.php',
    'api_test.php',
    'timer-test.php',
    'simple-test.php',

    # Debug-Dateien
    'debug-500-error.php',
    'debug-login-500.php',
    'debug-oauth-callback.php',
    'debug-session.php',
    'debug-timer.php',
    'debug_error.php',
    'simple-debug.php',

    # Emergency-Dateien
    'emergency-session-check.php',
    'emergency-test.php',
    'emergency_health.php',
    'kill-all-sessions.php',

    # Login-Varianten (nur login.php ist aktiv)
    'login-backup.php',
    'login-current-backup.php',
    'login-debug-verbose.php',
    'login-debug.php',
    'login-fixed-final.php',
    'login-fixed.php',
    'login-health-based.php',
    'login-minimal.php',
    'login-next-stable.php',
    'login-next.php',
    'login-original.php',
    'login-production-ready.php',
    'login-simple.php',
    'login-step-probe.php',
    'login-test.php',
    'login-ultra-simple.php',
    'login-working.php',
    'login_emergency_fix.php',
    'login_min_direct.php',
    'login_stable_enhanced.php',

    # Alte/Temporäre Dateien
    'schwarm_analyze.php',
    'schwarm_analyze_apis.php',
    'schwarm_fix.php',
    'diagnose-500.php',
    'server-diagnostic.php',
    'session-check.php',
    'session-clear.php',
    'session-test.php',
    'quick-session-fix.php',
    'fix-user-id-mapping.php',
    'fix_role.php',
    'check-db-schema.php',
    'check-test-mode.php',
    'check_table.php',
    'clear-session.php',
    'compare-files.php',
    'create-oauth-user.php',
    'create-test-user.php',
    'create-user-direct.php',
    'create_time_entries.php',
    'db-init.php',
    'db_test.php',
    'db_universal.php',
    'force-logout.php',
    'list-users.php',
    'auth-callback-safe.php',
    'verify-migration-success.php',
    'migrate-stop-time-nullable.php',
    'logs_emergency_fix.php',
    'health-login.php',
    'performance-monitor.php',
    'query-logger.php',
    'validation-safe.php',
    'mfa-setup.php',
    'mfa-verify.php',
    'login-with-mfa.php',
    'session_handler.php',
    'api.php',
    'projects.php',
    'user-info.php',
    'logout.php',  # auth-logout.php wird verwendet
]

# Root-Dateien die gelöscht werden sollen
ROOT_FILES_TO_DELETE = [
    # TypeScript/React Quelldateien (gehören nicht auf den Server!)
    'MFASetup.tsx',
    'LazyRoutes.tsx',
    'ErrorMessageService.ts',
    'NotificationService.ts',
    'ErrorMessageService.test.ts',
    'ConfirmDeleteModal.tsx',
    'EditEntryModal.tsx',
    'RoleAssignmentModal.tsx',
    'EditEntryModal.test.tsx',
    'SupervisorNotificationModal.tsx',
    'MFAVerification.tsx',
    'MFASetup.test.tsx',
    'ErrorBoundary.test.tsx',
    'ErrorBoundary.tsx',
    'Logo.tsx',
    'ErrorDisplay.tsx',
    'LoadingSpinner.tsx',
    'ThemeToggle.tsx',
    'ErrorBoundary.css',
    'Logo.test.tsx',
    'ErrorDisplay.css',
    'ThemeToggle.test.tsx',
    'ErrorDisplay.test.tsx',
    'LoadingSpinner.test.tsx',
    'TimerService.tsx',
    'types.ts',
    'api.test.ts',
    'utils.tsx',
    'setup.ts',
    'time.test.ts',
    'constants.ts',
    'export.ts',
    'aggregate.ts',
    'validation.ts',
    'time.ts',
    'TimeSheetView.tsx',
    'ChangeHistoryView.tsx',
    'DashboardView.tsx',
    'MasterDataView.tsx',
    'DayDetailView.tsx',
    'SignInPage.test.tsx',
    'GlobalSettingsView.tsx',
    'SignInPage.tsx',
    'MainAppView.tsx',
    'ApprovalView.tsx',
    'useMFA.ts',
    'useResponsive.ts',
    'useSupervisorNotifications.test.ts',
    'useTimer.ts',
    'useSupervisorNotifications.ts',
    'index.tsx',
    'index.ts',
    'App.test.tsx',
    'App.tsx',

    # Test/Config Dateien
    'vite.config.ts',
    'tsconfig.node.json',
    'playwright.config.ts',
    'phpunit.xml',
    'package.json',
    'authConfig.ts',
    'api.ts',
    'deploy.yml',
    'run-swarm.js',
    'swarm-init.js',
    'test-executor.js',

    # Test HTML/PHP
    'test-deployment.html',
    'oauth-test.html',
    'test-assets.html',
    'test-login-flow.html',
    'test-login-with-real-session.html',
    'status.html',
    'test-db-connection.php',
    'test-oauth-config.php',
    'test-upload-location.php',
    'test-env-check.php',
    'test_actual_password.php',
    'check-deployment.php',
    'check_env_file.php',
    'minimal_mfa_test.php',

    # Backup-Dateien
    'time-entries.php.backup_20250805_171112',
    'users.php.backup_20250805_171112',
    'approvals.php.backup_20250805_171112',
    'history.php.backup_20250805_171113',
    'time-entries.php.backup_20250810_115700',
    'users.php.backup_20250810_115701',
    'approvals.php.backup_20250810_115701',
    'history.php.backup_20250810_115701',
    'config.php.backup_20250810',
    'index.backup.html',
    'index.bak.rollback',
    'index.html.bak_20251007_173133',

    # Alte JS-Bundles im Root
    'index-DsjfTLkB.js',
    'index-Jq3KfgsT.css',
    'index.es-jywvPI1i.js',

    # Alte PHP-Dateien im Root (sollten in /api/ sein)
    'login.php',  # Duplikat - echte ist in /api/
    'masterdata.php',  # Duplikat
    'settings.php',  # Duplikat
    'auth-status.php',  # Duplikat
    'auth-logout.php',  # Duplikat
    'auth-start.php',  # Duplikat
    'auth-callback.php',  # Duplikat
    'auth-oauth-client.php',  # Duplikat
    'auth_helpers.php',  # Duplikat
    'db.php',  # Duplikat
    'constants.php',  # Duplikat
    'validation.php',  # Duplikat
    'health.php',  # Duplikat
    'monitoring.php',  # Duplikat
    'error-handler.php',  # Duplikat
    'structured-logger.php',  # Duplikat
    'security-headers.php',  # Duplikat
    'security-middleware.php',  # Duplikat
    'csrf-protection.php',  # Duplikat
    'db-wrapper.php',  # Duplikat
    'logs.php',  # Duplikat
    'ApiErrorHandler.php',  # Duplikat
    'auth-status-v2.php',  # Duplikat
    'validation-safe.php',  # Duplikat
    'direct_migration_test.php',
    'verify_migration.php',
    'migration_test.php',
    'complete_mfa_migration.php',
    'find_working_db.php',
    'verify.php',
    'setup.php',
    '001_stop_time_nullable.php',
    '001_stop_time_rollback.php',
    'analyze_stop_time.php',
    'fix-permissions.php',
    'bootstrap.php',
    'ApiEndpointsTest.php',
    'AuthHelpersTest.php',
    'users.test.ts',

    # Test-Reports
    'test-report-1753895718625.json',
    'test-report-1753892079446.json',
]

def cleanup_webspace(dry_run=True):
    """Bereinigt den Webspace von alten/nicht benötigten Dateien"""

    print(f"{'[DRY RUN] ' if dry_run else ''}Starte Webspace-Bereinigung...")
    print("=" * 60)

    try:
        ftp = FTP_TLS()
        ftp.connect(FTP_HOST, 21)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()
        print("✅ FTP-Verbindung erfolgreich")

        deleted_count = 0
        error_count = 0
        skipped_count = 0

        # 1. API-Dateien löschen
        print("\n--- Lösche API-Dateien ---")
        ftp.cwd('/api')
        api_files_on_server = set(ftp.nlst())
        print(f"  Gefunden: {len(api_files_on_server)} Dateien in /api/")

        for filename in API_FILES_TO_DELETE:
            if filename in api_files_on_server:
                try:
                    if dry_run:
                        print(f"  [würde löschen] api/{filename}")
                        deleted_count += 1
                    else:
                        ftp.delete(filename)
                        print(f"  ✓ Gelöscht: api/{filename}")
                        deleted_count += 1
                except Exception as e:
                    print(f"  ✗ Fehler bei api/{filename}: {e}")
                    error_count += 1
            else:
                skipped_count += 1

        # 2. Root-Dateien löschen
        print("\n--- Lösche Root-Dateien ---")
        ftp.cwd('/')
        root_files_on_server = set(ftp.nlst())
        print(f"  Gefunden: {len(root_files_on_server)} Dateien im Root")

        for filename in ROOT_FILES_TO_DELETE:
            if filename in root_files_on_server:
                try:
                    if dry_run:
                        print(f"  [würde löschen] {filename}")
                        deleted_count += 1
                    else:
                        ftp.delete(filename)
                        print(f"  ✓ Gelöscht: {filename}")
                        deleted_count += 1
                except Exception as e:
                    print(f"  ✗ Fehler bei {filename}: {e}")
                    error_count += 1
            else:
                skipped_count += 1

        ftp.quit()

        print("\n" + "=" * 60)
        print(f"{'[DRY RUN] ' if dry_run else ''}Zusammenfassung:")
        print(f"  Gelöscht/Würde löschen: {deleted_count} Dateien")
        print(f"  Übersprungen (nicht gefunden): {skipped_count}")
        print(f"  Fehler: {error_count}")

        return deleted_count, error_count

    except Exception as e:
        print(f"❌ Verbindungsfehler: {e}")
        import traceback
        traceback.print_exc()
        return 0, 1

if __name__ == "__main__":
    dry_run = "--execute" not in sys.argv

    if dry_run:
        print("=" * 60)
        print("DRY RUN MODUS - Keine Dateien werden gelöscht!")
        print("Führe mit --execute aus um tatsächlich zu löschen")
        print("=" * 60)

    cleanup_webspace(dry_run=dry_run)
