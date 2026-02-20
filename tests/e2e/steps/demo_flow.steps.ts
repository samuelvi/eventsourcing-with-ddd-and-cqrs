import { APIRequestContext, expect } from '@playwright/test';
import { Then, When, spin } from '../common/bdd';

type DemoStats = {
    events: number;
    users: number;
    bookings: number;
};

let baselineStats: DemoStats | null = null;

When('I set the message bus status to {string}', async ({ page }, target: string) => {
    const expected = target.trim().toUpperCase();
    const toggle = page
        .getByText('Message Bus Status', { exact: true })
        .locator('xpath=ancestor::div[1]')
        .locator('button')
        .first();

    const currentLabel = (await toggle.textContent())?.toUpperCase() ?? '';
    if (!currentLabel.includes(expected)) {
        await toggle.click();
    }

    await expect(toggle).toContainText(expected);
});

Then('the message bus status should be {string}', async ({ page }, expected: string) => {
    const state = expected.trim().toUpperCase();
    const toggle = page
        .getByText('Message Bus Status', { exact: true })
        .locator('xpath=ancestor::div[1]')
        .locator('button')
        .first();

    await expect(toggle).toContainText(state);
});

When('I set the user projection status to {string}', async ({ page }, target: string) => {
    const expected = target.trim().toUpperCase();
    const toggle = page.getByRole('button', { name: /User Projection/i });
    const currentLabel = (await toggle.textContent())?.toUpperCase() ?? '';

    if (!currentLabel.includes(expected)) {
        await toggle.click();
    }

    await expect(toggle).toContainText(expected);
});

When('I set the booking projection status to {string}', async ({ page }, target: string) => {
    const expected = target.trim().toUpperCase();
    const toggle = page.getByRole('button', { name: /Booking Projection/i });
    const currentLabel = (await toggle.textContent())?.toUpperCase() ?? '';

    if (!currentLabel.includes(expected)) {
        await toggle.click();
    }

    await expect(toggle).toContainText(expected);
});

When('I remember the current demo stats', async ({ request }) => {
    baselineStats = await fetchDemoStats(request);
});

Then('the events count should increase by {int}', async ({ request }, delta: number) => {
    expect(baselineStats).not.toBeNull();

    await spin(
        async () => {
            const current = await fetchDemoStats(request);
            expect(current.events).toBe((baselineStats as DemoStats).events + delta);
        },
        { timeout: 30_000, interval: 300 }
    );
});

Then('the users count should increase by {int}', async ({ request }, delta: number) => {
    expect(baselineStats).not.toBeNull();

    await spin(
        async () => {
            const current = await fetchDemoStats(request);
            expect(current.users).toBe((baselineStats as DemoStats).users + delta);
        },
        { timeout: 30_000, interval: 300 }
    );
});

Then('the bookings count should increase by {int}', async ({ request }, delta: number) => {
    expect(baselineStats).not.toBeNull();

    await spin(
        async () => {
            const current = await fetchDemoStats(request);
            expect(current.bookings).toBe((baselineStats as DemoStats).bookings + delta);
        },
        { timeout: 30_000, interval: 300 }
    );
});

Then('the {string} button should not be visible', async ({ page }, name: string) => {
    await expect(page.getByRole('button', { name: new RegExp(name, 'i') })).toHaveCount(0);
});

Then('the {string} count should be {int}', async ({ request }, key: string, expected: number) => {
    const current = await fetchDemoStats(request);
    expect(getCountByKey(current, key)).toBe(expected);
});

Then(
    'the {string} count should be greater than {int}',
    async ({ request }, key: string, threshold: number) => {
        const current = await fetchDemoStats(request);
        expect(getCountByKey(current, key)).toBeGreaterThan(threshold);
    }
);

Then('the users count should match remembered baseline', async ({ request }) => {
    expect(baselineStats).not.toBeNull();

    await spin(
        async () => {
            const current = await fetchDemoStats(request);
            expect(current.users).toBe((baselineStats as DemoStats).users);
        },
        { timeout: 30_000, interval: 300 }
    );
});

Then('the bookings count should match remembered baseline', async ({ request }) => {
    expect(baselineStats).not.toBeNull();

    await spin(
        async () => {
            const current = await fetchDemoStats(request);
            expect(current.bookings).toBe((baselineStats as DemoStats).bookings);
        },
        { timeout: 30_000, interval: 300 }
    );
});

async function fetchDemoStats(request: APIRequestContext): Promise<DemoStats> {
    const response = await request.get('/api/demo/stats');
    expect(response.ok()).toBeTruthy();
    const payload = (await response.json()) as Record<string, unknown>;

    return {
        events: Number(payload.events ?? 0),
        users: Number(payload.users ?? 0),
        bookings: Number(payload.bookings ?? 0)
    };
}

function getCountByKey(stats: DemoStats, rawKey: string): number {
    const key = rawKey.trim().toLowerCase();
    if (key === 'events') return stats.events;
    if (key === 'users') return stats.users;
    if (key === 'bookings') return stats.bookings;
    throw new Error(`Unsupported count key: ${rawKey}`);
}
