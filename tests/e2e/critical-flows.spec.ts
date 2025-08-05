import { test, expect } from '@playwright/test';

test.describe('Critical User Flows', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('https://aze.mikropartner.de');
  });

  test('Login Flow', async ({ page }) => {
    // Click Microsoft login
    await page.click('text=Mit Microsoft anmelden');
    
    // Handle OAuth flow (mocked in test env)
    await page.waitForURL('**/dashboard');
    
    // Verify logged in
    await expect(page.locator('.user-name')).toBeVisible();
  });

  test('Timer Start/Stop Flow', async ({ page }) => {
    // Login first
    await loginUser(page);
    
    // Start timer
    await page.click('button:has-text("Start")');
    await expect(page.locator('.timer-display')).toBeVisible();
    
    // Wait 3 seconds
    await page.waitForTimeout(3000);
    
    // Stop timer
    await page.click('button:has-text("Stop")');
    
    // Verify entry created
    await expect(page.locator('.time-entry').first()).toContainText('00:00:0');
  });

  test('Supervisor Approval Flow', async ({ page }) => {
    // Login as supervisor
    await loginUser(page, 'supervisor');
    
    // Navigate to approvals
    await page.click('text=Genehmigungen');
    
    // Approve first entry
    await page.click('.approval-item button:has-text("Genehmigen")');
    
    // Verify success
    await expect(page.locator('.notification')).toContainText('Erfolgreich genehmigt');
  });

  test('Data Export Flow', async ({ page }) => {
    // Login and navigate to timesheet
    await loginUser(page);
    await page.click('text=Zeiterfassung');
    
    // Download PDF
    const downloadPromise = page.waitForEvent('download');
    await page.click('button:has-text("PDF Export")');
    const download = await downloadPromise;
    
    // Verify download
    expect(download.suggestedFilename()).toContain('Zeiterfassung');
  });

  test('Error Handling', async ({ page }) => {
    // Simulate network error
    await page.route('**/api/time-entries.php', route => route.abort());
    
    // Try to load timesheet
    await loginUser(page);
    await page.click('text=Zeiterfassung');
    
    // Verify error boundary
    await expect(page.locator('.error-boundary')).toBeVisible();
    await expect(page.locator('text=Oops! Something went wrong')).toBeVisible();
  });
});

async function loginUser(page: any, role: string = 'user') {
  // Mock login for tests
  await page.evaluate((userRole) => {
    window.localStorage.setItem('user', JSON.stringify({
      id: 1,
      name: 'Test User',
      role: userRole === 'supervisor' ? 'Admin' : 'Honorarkraft'
    }));
  }, role);
  
  await page.reload();
}