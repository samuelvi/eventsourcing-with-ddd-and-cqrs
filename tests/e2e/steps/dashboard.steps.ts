import { expect } from '@playwright/test';
import { Then } from '../common/bdd';

Then(
    'I should see {string} in the {string} counter',
    async ({ page }, value: string, label: string) => {
        if (label === 'Historical Facts') {
            // Look for the div that has exactly "Historical Facts" and then its sibling or child number
            const labelLocator = page.getByText(label, { exact: true });
            const card = page
                .locator('div')
                .filter({ has: labelLocator })
                .filter({ has: page.locator('div', { hasText: /^\d+$/ }) })
                .last();
            const valueLocator = card.locator('div').filter({ hasText: /^\d+$/ });
            await expect(valueLocator).toHaveText(value, { timeout: 15000 });
        } else {
            // For User Records: or Booking Records:
            const labelLocator = page.getByText(label, { exact: false });
            const row = page.locator('div').filter({ has: labelLocator }).last();
            const valueLocator = row.locator('span').last();
            await expect(valueLocator).toHaveText(value, { timeout: 15000 });
        }
    }
);

Then(
    'I should see {string} in the {string} table',
    async ({ page }, text: string, tableTitle: string) => {
        // Find the table container by its title
        const container = page
            .locator('div')
            .filter({ has: page.locator('div', { hasText: tableTitle }) })
            .last();
        await expect(container.locator('table')).toContainText(text, { timeout: 15000 });
    }
);

Then(
    'I should not see {string} in the {string} table',
    async ({ page }, text: string, tableTitle: string) => {
        const container = page
            .locator('div')
            .filter({ has: page.locator('div', { hasText: tableTitle }) })
            .last();
        await expect(container.locator('table')).not.toContainText(text);
    }
);
