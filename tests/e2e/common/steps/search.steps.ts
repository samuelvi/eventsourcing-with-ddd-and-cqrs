import { expect } from '@playwright/test';
import { When, Then } from '../bdd';

// =============================================================================
// Common Search Steps
// =============================================================================

When('I click the clear button', async ({ page }) => {
    await page.getByRole('button', { name: /Limpiar|Clear/i }).click();
    await page.waitForLoadState('networkidle');
});

Then('the search field should be empty', async ({ page }) => {
    await expect(page.getByRole('textbox').first()).toHaveValue('');
});
