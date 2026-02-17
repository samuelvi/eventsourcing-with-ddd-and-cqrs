import { expect } from '@playwright/test';
import { Then, When } from '../common/bdd';

When('I submit {int} booking requests', async ({ page }, count: number) => {
    for (let i = 0; i < count; i++) {
        if (i > 0) {
            await page.getByRole('button', { name: /Create Another Booking/i }).click();
        }

        await page.getByLabel(/Client Name/i).fill(`Snapshot User ${i + 1}`);
        await page.getByLabel(/Email Address/i).fill(`snapshot${i + 1}@test.com`);
        await page.getByLabel(/People \(Pax\)/i).fill('2');
        await page.getByLabel(/Budget/i).fill('100');
        await page.getByRole('button', { name: /Submit Booking/i }).click();

        await expect(page.getByText('Request Received')).toBeVisible({ timeout: 15000 });
    }
});

Then('the snapshot count should be {int}', async ({ request }, expected: number) => {
    const response = await request.get('/api/snapshots');
    expect(response.ok()).toBeTruthy();

    const payload = (await response.json()) as { 'hydra:totalItems'?: number };
    expect(payload['hydra:totalItems']).toBe(expected);
});
