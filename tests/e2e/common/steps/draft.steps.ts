import { expect } from '@playwright/test';
import { When, Then } from '../bdd';

// =============================================================================
// Common Draft Steps (shared across all draft features)
// =============================================================================

When('I wait for potential auto-save', async ({ page }) => {
    // Prefer condition-based waiting to reduce flaky fixed sleeps.
    try {
        await expect(page.getByRole('alert').filter({ hasText: /draft|borrador/i })).toBeVisible({
            timeout: 6000
        });
    } catch {
        // No draft alert yet; continue without hard-failing this helper wait step.
    }
});

Then('I should see the draft alert', async ({ page }) => {
    await expect(page.getByRole('alert').filter({ hasText: /draft|borrador/i })).toBeVisible({
        timeout: 15000
    });
});

Then('I should not see the draft alert', async ({ page }) => {
    await expect(page.getByRole('alert').filter({ hasText: /draft|borrador/i })).not.toBeVisible();
});
