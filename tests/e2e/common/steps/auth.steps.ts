import { Given } from '../bdd';
import { loginAsAdmin } from '../auth';

// =============================================================================
// Authentication Steps (shared across all features)
// =============================================================================

Given('I am logged in as an administrator', async ({ page, context }) => {
    await loginAsAdmin(page, context);
});
