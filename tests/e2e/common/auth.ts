/**
 * E2E Test Helpers - Authentication
 */

import { Page, BrowserContext, expect } from '@playwright/test';
import { AUTH_CREDENTIALS } from './constants';

/**
 * Login as admin user bypassing the UI form for speed and stability.
 * Note: Assumes a JWT-based authentication system with a /login_check endpoint.
 */
export async function loginAsAdmin(page: Page, context: BrowserContext): Promise<void> {
    // 1. Get token via API using context request
    const loginResponse = await context.request.post('/api/login_check', {
        data: {
            username: AUTH_CREDENTIALS.username,
            password: AUTH_CREDENTIALS.password
        }
    });

    if (!loginResponse.ok()) {
        throw new Error(`Login API failed with status ${loginResponse.status()}`);
    }

    const { token } = await loginResponse.json();

    // 2. Inject token and locale into EVERY page in this context
    await context.addInitScript(
        ({ jwt }) => {
            localStorage.setItem('token', jwt);
            localStorage.setItem('app_locale', 'es');
        },
        { jwt: token }
    );

    // 3. Navigate directly to dashboard
    await page.goto('/dashboard');
    await page.waitForURL('**/dashboard', { timeout: 30000 });
    await expect(page.locator('body')).toContainText(/Bienvenido|Welcome/i, { timeout: 30000 });
    await page.waitForLoadState('networkidle');
}
