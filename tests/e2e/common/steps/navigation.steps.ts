import { expect } from '@playwright/test';
import { Given, When, Then } from '../bdd';

// =============================================================================
// Navigation Steps
// =============================================================================

Given('I am on the {string} page', async ({ page }, path: string) => {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
});

Given('I am on the login page', async ({ page }) => {
    await page.addInitScript(() => {
        localStorage.setItem('app_locale', 'es');
    });
    await page.goto('/login');
});

When('I navigate to {string}', async ({ page }, path: string) => {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
});

When('I reload the page', async ({ page }) => {
    await page.reload();
    await page.waitForLoadState('networkidle');
});

When('I go back', async ({ page }) => {
    await page.goBack();
});

When('I go forward', async ({ page }) => {
    await page.goForward();
});

// =============================================================================
// URL Assertions
// =============================================================================

Then('I should be on {string}', async ({ page }, path: string) => {
    await expect(page).toHaveURL(new RegExp(path));
});

Then('I should be redirected to {string}', async ({ page }, path: string) => {
    await page.waitForURL(`**${path}`, { timeout: 30000 });
});

Then('the URL should contain {string}', async ({ page }, text: string) => {
    await expect(page).toHaveURL(new RegExp(text));
});

When('I wait for navigation to {string}', async ({ page }, path: string) => {
    await expect(page).toHaveURL(new RegExp(path.replace('/', '\\/')));
});
