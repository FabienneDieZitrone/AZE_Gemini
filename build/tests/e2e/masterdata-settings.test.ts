/**
 * E2E Tests for MasterData UI and APIs (non-auth checks + bundle presence)
 */
import { test, expect } from '@playwright/test';

const BASE_URL = 'https://aze.mikropartner.de';

test.describe('MasterData endpoints (unauthenticated)', () => {
  test('CSRF token endpoint returns a token', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/api/csrf-token.php`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.csrfToken).toBeTruthy();
  });

  test('Masterdata update requires auth', async ({ request }) => {
    const res = await request.put(`${BASE_URL}/api/masterdata.php`, {
      data: { userId: 1, weeklyHours: 40, workdays: ['Mo','Di'] }
    });
    expect([401, 403]).toContain(res.status());
  });

  test('User role update requires auth', async ({ request }) => {
    const res = await request.patch(`${BASE_URL}/api/users.php`, {
      data: { userId: 1, newRole: 'Mitarbeiter' }
    });
    expect([401, 403]).toContain(res.status());
  });
});

test.describe('MasterData UI strings present in bundle', () => {
  test('Main bundle contains new MasterData labels', async ({ request }) => {
    // Fetch index to discover current asset hash
    const indexRes = await request.get(`${BASE_URL}/index.php`);
    expect(indexRes.ok()).toBeTruthy();
    const html = await indexRes.text();
    const match = html.match(/<script[^>]+src="(\/assets\/index-[^"]+\.js)"/i);
    test.skip(!match, 'No hashed index bundle found');
    if (!match) return;
    const bundlePath = match[1];
    const jsRes = await request.get(`${BASE_URL}${bundlePath}`);
    expect(jsRes.ok()).toBeTruthy();
    const js = await jsRes.text();
    // Check for labels we added in MasterDataView
    expect(js).toContain('Flexibel');
    expect(js).toContain('Tägliche Stunden je ausgewähltem Tag');
    expect(js).toContain('Zugeordnete Standorte');
  });
});

