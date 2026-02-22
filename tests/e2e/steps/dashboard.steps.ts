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
        } else if (label === 'Snapshots') {
            const labelLocator = page.getByText('Snapshots', { exact: true });
            const container = page.locator('div').filter({ has: labelLocator }).last();
            const valueLocator = container.locator('div').filter({ hasText: /^\d+$/ }).first();
            await expect(valueLocator).toHaveText(value, { timeout: 15000 });
        } else {
            // For User/Booking records counters across UI variants.
            const aliases: Record<string, RegExp> = {
                'User Records': /User Records|Users:/i,
                'Booking Records': /Booking Records|Bookings:/i
            };
            const labelLocator = aliases[label]
                ? page.getByText(aliases[label])
                : page.getByText(label, { exact: false });
            const row = page.locator('div').filter({ has: labelLocator }).last();
            const valueLocator = row.locator('span').last();

            if (aliases[label]) {
                await expect(valueLocator).toHaveText(new RegExp(`^${value}(\\s*/\\s*\\d+)?$`), {
                    timeout: 15000
                });
                return;
            }

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
