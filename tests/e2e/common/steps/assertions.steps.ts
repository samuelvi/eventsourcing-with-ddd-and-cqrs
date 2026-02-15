import { expect } from '@playwright/test';
import { Then } from '../bdd';

// =============================================================================
// Text Visibility Assertions
// =============================================================================

Then('I should see {string}', async ({ page }, text: string) => {
    await expect(page.getByText(text, { exact: false }).first()).toBeVisible();
});

Then('I should not see {string}', async ({ page }, text: string) => {
    await expect(page.getByText(text, { exact: false })).not.toBeVisible();
});

Then('I should see text matching {string}', async ({ page }, pattern: string) => {
    await expect(page.locator('body')).toContainText(new RegExp(pattern, 'i'));
});

// =============================================================================
// Element Visibility Assertions
// =============================================================================

Then('the {string} button should be visible', async ({ page }, name: string) => {
    await expect(page.getByRole('button', { name: new RegExp(name, 'i') })).toBeVisible();
});

Then('the {string} button should be disabled', async ({ page }, name: string) => {
    await expect(page.getByRole('button', { name: new RegExp(name, 'i') })).toBeDisabled();
});

Then('the {string} button should be enabled', async ({ page }, name: string) => {
    await expect(page.getByRole('button', { name: new RegExp(name, 'i') })).toBeEnabled();
});

Then('the {string} link should be visible', async ({ page }, name: string) => {
    await expect(page.getByRole('link', { name: new RegExp(name, 'i') })).toBeVisible();
});

// =============================================================================
// Table Assertions
// =============================================================================

Then('I should see {int} rows in the table', async ({ page }, count: number) => {
    await expect(page.getByRole('row')).toHaveCount(count + 1, { timeout: 10000 });
});

Then('the table should contain {string}', async ({ page }, text: string) => {
    await expect(page.getByRole('table')).toContainText(text);
});

Then('the table should be empty', async ({ page }) => {
    await expect(page.getByRole('row')).toHaveCount(1);
});

// =============================================================================
// Page State Assertions
// =============================================================================

Then('the page title should be {string}', async ({ page }, title: string) => {
    await expect(page).toHaveTitle(new RegExp(title, 'i'));
});

Then('I should see a loading indicator', async ({ page }) => {
    await expect(page.getByText(/loading|cargando/i)).toBeVisible();
});

Then('I should not see a loading indicator', async ({ page }) => {
    await expect(page.getByText(/loading|cargando/i)).not.toBeVisible();
});

// =============================================================================
// Alert/Message Assertions
// =============================================================================

Then('I should see an error message', async ({ page }) => {
    await expect(page.getByRole('alert')).toBeVisible();
});

Then('I should see a success message', async ({ page }) => {
    await expect(page.getByRole('alert')).toBeVisible();
});

Then('I should see an error message containing {string}', async ({ page }, text: string) => {
    await expect(page.getByRole('alert')).toContainText(new RegExp(text, 'i'));
});
