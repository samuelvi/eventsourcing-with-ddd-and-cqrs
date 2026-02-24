import { expect, APIResponse } from '@playwright/test';
import { Then, When, spin } from '../common/bdd';

type HydraCollection = {
    'hydra:member'?: Array<Record<string, unknown>>;
};

let lastUserApiResponseStatus: number | null = null;
let currentUserId: string | null = null;

When(
    'I create a user via API with name {string} and email {string}',
    async ({ request }, name: string, email: string) => {
        const id = crypto.randomUUID();
        const response = await request.post('/api/users', {
            headers: {
                'Content-Type': 'application/ld+json',
                Accept: 'application/ld+json'
            },
            data: { id, name, email }
        });

        await storeResponse(response);
        currentUserId = await extractUserId(response);
        expect(currentUserId).toBe(id);

        // Wait for the projection to be updated
        await spin(() =>
            request.get(`/api/users/${currentUserId}`).then((res) => expect(res.status()).toBe(200))
        );
    }
);

When(
    'I create a user via API with id {string} name {string} and email {string}',
    async ({ request }, id: string, name: string, email: string) => {
        const response = await request.post('/api/users', {
            headers: {
                'Content-Type': 'application/ld+json',
                Accept: 'application/ld+json'
            },
            data: { id, name, email }
        });

        await storeResponse(response);
        currentUserId = await extractUserId(response);
        expect(currentUserId).toBe(id);

        // Wait for the projection to be updated
        await spin(() =>
            request.get(`/api/users/${currentUserId}`).then((res) => expect(res.status()).toBe(200))
        );
    }
);

When(
    'I update that user via API with name {string} and email {string}',
    async ({ request }, name: string, email: string) => {
        expect(currentUserId).not.toBeNull();

        // Poll until the user is available before trying to update
        await spin(
            async () => {
                const checkResponse = await request.get(`/api/users/${currentUserId}`);
                expect(checkResponse.status()).toBe(200);
            },
            { timeout: 15000 }
        );

        const response = await request.fetch(`/api/users/${currentUserId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/merge-patch+json',
                Accept: 'application/ld+json'
            },
            data: { name, email }
        });

        await storeResponse(response);
    }
);

When('I delete that user via API', async ({ request }) => {
    expect(currentUserId).not.toBeNull();

    // Poll until the user is available before trying to delete
    await spin(
        async () => {
            const checkResponse = await request.get(`/api/users/${currentUserId}`);
            expect(checkResponse.status()).toBe(200);
        },
        { timeout: 15000 }
    );

    const response = await request.fetch(`/api/users/${currentUserId}`, {
        method: 'DELETE',
        headers: {
            Accept: 'application/ld+json'
        }
    });

    await storeResponse(response);
});

When('I clear transactional projections', async ({ request }) => {
    const response = await request.post('/api/demo/clear-transactional');
    await storeResponse(response);
});

When('I rebuild projections from mongo history', async ({ request }) => {
    const response = await request.post('/api/demo/rebuild-from-mongo');
    await storeResponse(response);
});

When('I wait {int} second', async ({ page }, seconds: number) => {
    const timeout = Math.max(1000, seconds * 1000);

    // Prefer readiness-based waits over fixed sleeps; bound by requested timeout.
    try {
        await page.waitForLoadState('networkidle', { timeout });
        return;
    } catch {
        // Fall through to a weaker readiness signal.
    }

    try {
        await page.waitForLoadState('load', { timeout });
    } catch {
        // Keep this step non-fatal: callers should assert concrete outcomes next.
    }
});

Then(
    'the user API response status should be {int}',
    async ({ request }, expectedStatus: number) => {
        void request;
        expect(lastUserApiResponseStatus).toBe(expectedStatus);
    }
);

Then(
    'the users projection should include that user with name {string} and email {string}',
    async ({ request }, expectedName: string, expectedEmail: string) => {
        expect(currentUserId).not.toBeNull();

        await spin(
            async () => {
                const response = await request.get('/api/users');
                expect(response.ok()).toBeTruthy();

                const payload = (await response.json()) as HydraCollection;
                const rows = payload['hydra:member'] ?? [];

                const row = rows.find((member) => normalizeId(member) === currentUserId);
                expect(row).toBeDefined();
                expect(row?.name).toBe(expectedName);
                expect(row?.email).toBe(expectedEmail);
            },
            { timeout: 30_000, interval: 300 }
        );
    }
);

Then('the users projection should not include that user', async ({ request }) => {
    expect(currentUserId).not.toBeNull();

    await spin(
        async () => {
            const response = await request.get('/api/users');
            expect(response.ok()).toBeTruthy();

            const payload = (await response.json()) as HydraCollection;
            const rows = payload['hydra:member'] ?? [];
            const row = rows.find((member) => normalizeId(member) === currentUserId);
            expect(row).toBeUndefined();
        },
        { timeout: 30_000, interval: 300 }
    );
});

Then(
    'the event store should contain an event of type {string} for that user',
    async ({ request }, eventType: string) => {
        expect(currentUserId).not.toBeNull();
        const normalizedEventType = eventType.replace(/\\\\/g, '\\');

        await spin(
            async () => {
                const response = await request.get('/api/event-store');
                expect(response.ok()).toBeTruthy();

                const payload = (await response.json()) as HydraCollection;
                const events = payload['hydra:member'] ?? [];

                const hasExpectedEvent = events.some(
                    (event) =>
                        String(event.eventType) === normalizedEventType &&
                        String(event.aggregateId) === currentUserId
                );

                expect(hasExpectedEvent).toBeTruthy();
            },
            { timeout: 30_000, interval: 300 }
        );
    }
);

Then(
    'I remember user id by email {string} from users projection',
    async ({ request }, email: string) => {
        const response = await request.get('/api/users');
        expect(response.ok()).toBeTruthy();

        const payload = (await response.json()) as HydraCollection;
        const rows = payload['hydra:member'] ?? [];
        const row = rows.find((member) => String(member.email ?? '') === email);

        expect(row).toBeDefined();
        currentUserId = row ? normalizeId(row) : null;
        expect(currentUserId).not.toBeNull();
    }
);

Then(
    'the snapshot store should contain a snapshot for that user with name {string} and email {string}',
    async ({ request }, expectedName: string, expectedEmail: string) => {
        expect(currentUserId).not.toBeNull();

        await spin(
            async () => {
                const response = await request.get('/api/snapshots');
                expect(response.ok()).toBeTruthy();

                const payload = (await response.json()) as HydraCollection;
                const snapshots = payload['hydra:member'] ?? [];

                const userSnapshots = snapshots.filter(
                    (snapshot) => String(snapshot.aggregateId) === currentUserId
                );
                expect(userSnapshots.length).toBeGreaterThan(0);

                const latest = userSnapshots
                    .slice()
                    .sort((a, b) => Number(b.version ?? 0) - Number(a.version ?? 0))[0];
                const latestState = (latest.state ?? {}) as Record<string, unknown>;

                expect(String(latestState.name ?? '')).toBe(expectedName);
                expect(String(latestState.email ?? '')).toBe(expectedEmail);
            },
            { timeout: 30_000, interval: 300 }
        );
    }
);

async function storeResponse(response: APIResponse): Promise<void> {
    lastUserApiResponseStatus = response.status();
}

async function extractUserId(response: APIResponse): Promise<string | null> {
    let payload: Record<string, unknown> | null = null;
    try {
        payload = (await response.json()) as Record<string, unknown>;
    } catch {
        return null;
    }

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

function normalizeId(member: Record<string, unknown>): string | null {
    if (typeof member.id === 'string') {
        return member.id;
    }

    if (typeof member['@id'] === 'string') {
        const iri = member['@id'];
        const segments = iri.split('/');
        return segments[segments.length - 1] || null;
    }

    return null;
}
