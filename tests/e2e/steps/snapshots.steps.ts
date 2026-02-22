import { expect } from '@playwright/test';
import { Then, When, spin } from '../common/bdd';

let snapshotUserAggregateId: string | null = null;

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

When('I create a user aggregate with 5 events', async ({ request }) => {
    const email = `snapshot-user-${crypto.randomUUID()}@test.com`;

    const createResponse = await request.post('/api/users', {
        headers: {
            'Content-Type': 'application/ld+json',
            Accept: 'application/ld+json'
        },
        data: {
            id: crypto.randomUUID(),
            name: 'Snapshot User 1',
            email
        }
    });

    expect(createResponse.status()).toBe(202);
    snapshotUserAggregateId = await extractUserId(createResponse);
    expect(snapshotUserAggregateId).not.toBeNull();

    // Poll until the user is available in the read model
    await expect(async () => {
        const checkResponse = await request.get(`/api/users/${snapshotUserAggregateId}`);
        expect(checkResponse.status()).toBe(200);
    }).toPass({ timeout: 15000 });

    for (let i = 0; i < 4; i++) {
        const updateResponse = await request.fetch(`/api/users/${snapshotUserAggregateId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/merge-patch+json',
                Accept: 'application/ld+json'
            },
            data: {
                name: `Snapshot User ${i + 2}`,
                email
            }
        });

        expect(updateResponse.status()).toBe(202);
    }
});

Then('the snapshot count should be {int}', async ({ request }, expected: number) => {
    const response = await request.get('/api/snapshots');
    expect(response.ok()).toBeTruthy();

    const payload = (await response.json()) as { 'hydra:totalItems'?: number };
    expect(payload['hydra:totalItems']).toBe(expected);
});

Then('the user aggregate snapshot count should be {int}', async ({ request }, expected: number) => {
    expect(snapshotUserAggregateId).not.toBeNull();

    await spin(
        async () => {
            const response = await request.get('/api/snapshots');
            expect(response.ok()).toBeTruthy();

            const payload = (await response.json()) as {
                'hydra:member'?: Array<{ aggregateId?: string }>;
            };

            const members = payload['hydra:member'] ?? [];
            const count = members.filter((s) => s.aggregateId === snapshotUserAggregateId).length;
            expect(count).toBe(expected);
        },
        { timeout: 20000 }
    );
});

async function extractUserId(response: {
    json: () => Promise<Record<string, unknown>>;
}): Promise<string | null> {
    const payload = await response.json();

    if (typeof payload.id === 'string' && payload.id.length > 0) {
        return payload.id;
    }

    if (typeof payload['@id'] === 'string') {
        const iri = payload['@id'];
        const segments = iri.split('/');
        return segments[segments.length - 1] || null;
    }

    return null;
}
