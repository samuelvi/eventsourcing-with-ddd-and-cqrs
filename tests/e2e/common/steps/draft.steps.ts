import { expect } from '@playwright/test';
import { When, Then } from '../bdd';

// =============================================================================
// Common Draft Steps (shared across all draft features)
// =============================================================================

When('I wait for potential auto-save', async ({ page }) => {
    // Note: Using a fixed timeout is generally discouraged by the skill guide,
    // but for auto-save mechanisms without clear UI indicators, a short wait
    // may be necessary.
    await page.waitForTimeout(6000);
});

Then('I should see the draft alert', async ({ page }) => {
    await expect(page.getByRole('alert').filter({ hasText: /draft|borrador/i })).toBeVisible({
        timeout: 15000
    });
});

Then('I should not see the draft alert', async ({ page }) => {
    await expect(page.getByRole('alert').filter({ hasText: /draft|borrador/i })).not.toBeVisible();
});
