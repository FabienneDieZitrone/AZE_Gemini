/**
 * E2E Test Suite für Timer-Funktionalität
 * Verifiziert die deployten Änderungen
 */

import { test, expect } from '@playwright/test';

test.describe('Timer Functionality E2E Tests', () => {
  const BASE_URL = 'https://aze.mikropartner.de';

  test.beforeEach(async ({ page }) => {
    // Navigate to app
    await page.goto(BASE_URL);
  });

  test('Health endpoint should be accessible', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/health.php`);
    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.status).toBe('healthy');
    expect(data.checks.database.status).toBe('healthy');
  });

  test('New timer-control API should require authentication', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/api/timer-control.php`, {
      data: { action: 'start', location: 'Office' }
    });
    
    expect(response.status()).toBe(401);
    const data = await response.json();
    expect(data.message).toContain('Unauthorized');
  });

  test('Old timer endpoints should not exist', async ({ request }) => {
    // These should return 404 after cleanup
    const oldEndpoints = [
      '/api/timer-start.php',
      '/api/timer-stop.php'
    ];

    for (const endpoint of oldEndpoints) {
      const response = await request.get(`${BASE_URL}${endpoint}`);
      expect(response.status()).toBe(404);
    }
  });

  test('Constants file should be loaded', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/constants.php`);
    expect(response.ok()).toBeTruthy();
  });

  test('Security headers should be present', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/api/health.php`);
    
    // Check critical security headers
    expect(response.headers()['x-frame-options']).toBe('DENY');
    expect(response.headers()['x-content-type-options']).toBe('nosniff');
    expect(response.headers()['strict-transport-security']).toContain('max-age=31536000');
    expect(response.headers()['content-security-policy']).toBeDefined();
  });

  test('Login page should load without errors', async ({ page }) => {
    // Check for console errors
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto(BASE_URL);
    await page.waitForTimeout(2000); // Wait for any async errors

    // No critical errors should occur
    const criticalErrors = consoleErrors.filter(error => 
      !error.includes('favicon') && // Ignore favicon 404s
      !error.includes('Failed to load resource') // Ignore resource loading in test env
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('Timer service components should be present in bundle', async ({ request }) => {
    // Check if main JS bundle contains our new components
    const response = await request.get(`${BASE_URL}/dist/assets/index-DsjfTLkB.js`);
    const jsContent = await response.text();
    
    // Verify new timer components are in the bundle
    expect(jsContent).toContain('useTimer');
    expect(jsContent).toContain('TimerService');
    expect(jsContent).toContain('TIME.SECONDS_PER_HOUR');
  });
});

test.describe('Performance Tests', () => {
  test('Health endpoint should respond quickly', async ({ request }) => {
    const start = Date.now();
    const response = await request.get('https://aze.mikropartner.de/api/health.php');
    const duration = Date.now() - start;
    
    expect(response.ok()).toBeTruthy();
    expect(duration).toBeLessThan(1000); // Should respond within 1 second
  });

  test('Static assets should be cached', async ({ request }) => {
    const response = await request.get('https://aze.mikropartner.de/index.css');
    
    // Check for cache headers
    const cacheControl = response.headers()['cache-control'];
    expect(cacheControl).toBeDefined();
  });
});