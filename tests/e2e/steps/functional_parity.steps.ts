import { expect } from '@playwright/test';
import { Then, When, spin } from '../common/bdd';

type HydraCollection = {
    'hydra:totalItems'?: number;
};

let lastResponseStatus: number | null = null;

When('I submit a valid booking through the API', async ({ request }) => {
    const bookingId = crypto.randomUUID();
    const response = await request.post('/api/booking-wizard', {
        data: {
            bookingId,
            pax: 2,
            budget: 120,
            clientName: 'Wizard Happy Path',
            clientEmail: `wizard-happy-path-${bookingId}@test.com`
        }
    });

    lastResponseStatus = response.status();
});

Then('the last API response status should be {int}', async (_, expectedStatus: number) => {
    expect(lastResponseStatus).toBe(expectedStatus);
});

Then('the event store total items should be {int}', async ({ request }, expected: number) => {
    await spin(async () => {
        const response = await request.get('/api/event-store');
        expect(response.ok()).toBeTruthy();

        const payload = (await response.json()) as HydraCollection;
        expect(payload['hydra:totalItems']).toBe(expected);
    });
});

Then('the page source should contain {string}', async ({ page }, fragment: string) => {
    await expect(page.locator('html')).toContainText(fragment);
});

Then('the page source should not contain {string}', async ({ page }, fragment: string) => {
    await expect(page.locator('html')).not.toContainText(fragment);
});
