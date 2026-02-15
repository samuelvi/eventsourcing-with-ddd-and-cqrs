# Hooks and Test Isolation

Database initialization, fixtures, and test isolation patterns for Playwright BDD.

## Core Principle

**Initialize before each test, NEVER cleanup after**

### Why This Matters:

✅ **Clean Slate Every Time**

- Each test starts with a known, empty database
- No data pollution from previous tests
- Tests can run in any order

✅ **Exclusive Test Data**

- Each test creates its OWN specific data
- No shared fixtures between tests
- Prevents test interdependencies

✅ **Debugging Failed Tests**

- Data remains after failure
- Can inspect exact DB state when test failed
- Reproduce issues easily

✅ **Prevents Cascading Failures**

- If cleanup fails, doesn't affect next test
- Next test truncates anyway
- More reliable CI/CD

❌ **Never Do Cleanup After Tests:**

```typescript
// ❌ BAD - Data lost, can't debug failures
test.afterEach(async () => {
    await db.raw('TRUNCATE TABLE users');
});
```

✅ **Always Do Initialization Before Tests:**

```typescript
// ✅ GOOD - Clean start, data preserved
test.beforeEach(async () => {
    await db.raw('TRUNCATE TABLE users');
    await db.migrate.latest();
});
```

## Database Initialization Fixture

### Production-Ready Implementation with Tags

Complete fixture implementation with @reset/@no-reset tag support:

```typescript
// fixtures/database.fixture.ts
import { test as base, TestInfo } from 'playwright-bdd';
import { db } from '../support/database';

// Track which features have been reset in each worker
const workerResetTracker = new Map<string, Set<string>>();

interface DatabaseResetOptions {
    readonly featureName: string;
    readonly workerIndex: number;
    readonly tags: readonly string[];
    readonly isCI: boolean;
}

export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use, testInfo, workerInfo) => {
            const options = buildResetOptions(testInfo, workerInfo);

            if (shouldResetDatabase(options)) {
                await truncateDatabase();
                trackFeatureReset(options.workerIndex, options.featureName);
                console.log(
                    `🔄 [Worker ${options.workerIndex}] Database reset for: ${options.featureName}`
                );
            } else {
                console.log(
                    `⏭️  [Worker ${options.workerIndex}] Reusing data for: ${options.featureName}`
                );
            }

            await use();

            // NEVER cleanup after test - preserve data for debugging
        },
        { scope: 'test', auto: true }
    ]
});

export { expect } from '@playwright/test';

// ============================================================================
// Helper Functions with Early Returns
// ============================================================================

function buildResetOptions(testInfo: TestInfo, workerInfo: any): DatabaseResetOptions {
    return {
        featureName: extractFeatureName(testInfo),
        workerIndex: workerInfo.workerIndex,
        tags: testInfo.tags || [],
        isCI: process.env.CI === 'true' || process.env.TEST_MODE === 'ci'
    };
}

function shouldResetDatabase(options: DatabaseResetOptions): boolean {
    // CI mode: ALWAYS reset (test independence)
    if (options.isCI) {
        return true;
    }

    // Explicit @reset tag: ALWAYS reset
    if (options.tags.includes('@reset')) {
        return true;
    }

    // Explicit @no-reset tag: NEVER reset
    if (options.tags.includes('@no-reset')) {
        return false;
    }

    // Default: Reset only for first scenario of feature in this worker
    return isFirstScenarioOfFeatureInWorker(options.workerIndex, options.featureName);
}

function extractFeatureName(testInfo: TestInfo): string {
    // Extract feature name from test title path
    // Example: ["Invoice Management", "Create invoice"] -> "Invoice Management"
    const titlePath = testInfo.titlePath || [];
    return titlePath[0] || 'unknown-feature';
}

function isFirstScenarioOfFeatureInWorker(workerIndex: number, featureName: string): boolean {
    const workerFeatures = workerResetTracker.get(workerKey(workerIndex));

    if (!workerFeatures) {
        return true;
    }

    return !workerFeatures.has(featureName);
}

function trackFeatureReset(workerIndex: number, featureName: string): void {
    const key = workerKey(workerIndex);
    const features = workerResetTracker.get(key) || new Set<string>();

    features.add(featureName);
    workerResetTracker.set(key, features);
}

function workerKey(index: number): string {
    return `worker-${index}`;
}

// ============================================================================
// Database Truncation
// ============================================================================

async function truncateDatabase(): Promise<void> {
    try {
        // Step 1: Disable foreign key checks
        await db.raw('SET FOREIGN_KEY_CHECKS = 0');

        // Step 2: Get all tables except migration tables
        const tables = await db('information_schema.tables')
            .where('table_schema', db.raw('DATABASE()'))
            .whereNotIn('table_name', ['migrations', 'migrations_lock'])
            .select('table_name');

        // Step 3: Truncate each table
        for (const { table_name } of tables) {
            await db.raw(`TRUNCATE TABLE ${table_name}`);
        }

        // Step 4: Re-enable foreign key checks
        await db.raw('SET FOREIGN_KEY_CHECKS = 1');

        // Step 5: Ensure migrations are up-to-date
        await db.migrate.latest();
    } catch (error) {
        console.error('❌ Error truncating database:', error);
        throw error;
    }
}
```

### Alternative: Full Database Reset (slower, more thorough)

```typescript
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use) => {
            // Complete reset - drop and recreate database
            const testDbName = 'test_db';
            await db.raw(`DROP DATABASE IF EXISTS ${testDbName}`);
            await db.raw(`CREATE DATABASE ${testDbName}`);
            await db.raw(`USE ${testDbName}`);
            await db.migrate.latest();

            await use();

            // NO cleanup - keep data for debugging
        },
        { scope: 'test', auto: true }
    ]
});
```

### Usage in Steps

```typescript
// steps/products/products.steps.ts
import { createBdd } from 'playwright-bdd';
import { test } from '../../fixtures/database.fixture';

const { Given } = createBdd(test); // Use test with DB fixture

Given('products exist', async ({ dbReset }) => {
    // DB is already clean and migrated
    await db('products').insert(productFactory.buildList(5));
});
```

## Worker-Scoped vs Test-Scoped

### Test-Scoped (Per Test)

```typescript
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use) => {
            await db.migrate.rollback();
            await db.migrate.latest();
            await use();
        },
        { scope: 'test', auto: true }
    ]
});
```

### Worker-Scoped (Per Worker Process)

```typescript
export const test = base.extend<{}, { workerDb: void }>({
    workerDb: [
        async ({}, use, workerInfo) => {
            // Create isolated database per worker
            const dbName = `test_worker_${workerInfo.workerIndex}`;
            await db.raw(`CREATE DATABASE IF NOT EXISTS ${dbName}`);
            await db.raw(`USE ${dbName}`);
            await db.migrate.latest();

            await use();

            // Cleanup after all tests in worker
            await db.raw(`DROP DATABASE IF EXISTS ${dbName}`);
        },
        { scope: 'worker', auto: true }
    ]
});
```

## Parallel Test Isolation

### Database per Worker

```typescript
// support/database.ts
import { test } from '@playwright/test';
import knex from 'knex';

const getWorkerDatabase = () => {
    const workerIndex = test.info().parallelIndex;

    return knex({
        client: 'mysql2',
        connection: {
            host: 'localhost',
            database: `test_db_${workerIndex}`,
            user: 'test_user',
            password: 'test_password'
        }
    });
};

export const db = getWorkerDatabase();
```

### Isolated Database per Worker (Preferred)

```typescript
// fixtures/database.fixture.ts
export const test = base.extend<{}, { dbIsolation: void }>({
    dbIsolation: [
        async ({}, use, testInfo) => {
            const dbName = `test_${testInfo.workerIndex}`;

            // Setup isolated database
            await db.raw(`DROP DATABASE IF EXISTS ${dbName}`);
            await db.raw(`CREATE DATABASE ${dbName}`);
            await db.raw(`USE ${dbName}`);
            await db.migrate.latest();

            await use();
        },
        { scope: 'test', auto: true }
    ]
});
```

## Seeding Fixtures

### Reusable Seed Functions

```typescript
// fixtures/seeds.ts
import { userFactory } from '../factories/userFactory';
import { productFactory } from '../factories/productFactory';

export const seedUsers = async (count = 5) => {
    const users = userFactory.buildList(count);
    return db('users').insert(users).returning('*');
};

export const seedProducts = async (count = 10) => {
    const products = productFactory.buildList(count);
    return db('products').insert(products).returning('*');
};

export const seedFullDatabase = async () => {
    const users = await seedUsers(10);
    const products = await seedProducts(50);

    // Create orders with relationships
    for (const user of users.slice(0, 5)) {
        const order = orderFactory.build({ userId: user.id });
        await db('orders').insert(order);
    }

    return { users, products };
};
```

### Fixture with Seeds

```typescript
// fixtures/database.fixture.ts
export const test = base.extend<{ seededDb: { users: User[]; products: Product[] } }>({
    seededDb: async ({ dbReset }, use) => {
        // DB already clean from dbReset
        const data = await seedFullDatabase();
        await use(data);
    }
});

// Usage
Given('I have a seeded database', async ({ seededDb }) => {
    // Access seeded data
    const firstUser = seededDb.users[0];
});
```

## Authentication Fixtures

### Logged In User Fixture

```typescript
// fixtures/auth.fixture.ts
export const test = base.extend<{ loggedInUser: User }>({
    loggedInUser: async ({ page, dbReset }, use) => {
        // Create user
        const user = await userFactory.create();

        // Perform login
        await page.goto('/login');
        await page.getByLabel('Email').fill(user.email);
        await page.getByLabel('Password').fill('testPassword123');
        await page.getByRole('button', { name: 'Log in' }).click();

        await use(user);
    }
});

// Usage
Given('I am logged in', async ({ loggedInUser }) => {
    // User is already logged in
    // Access user data: loggedInUser.email
});
```

### Role-Based Fixtures

```typescript
// fixtures/auth.fixture.ts
export const test = base.extend<{
    loggedInAsAdmin: User;
    loggedInAsCustomer: User;
}>({
    loggedInAsAdmin: async ({ page }, use) => {
        const admin = await adminUserFactory.create();
        await loginAs(page, admin);
        await use(admin);
    },

    loggedInAsCustomer: async ({ page }, use) => {
        const customer = await userFactory.create();
        await loginAs(page, customer);
        await use(customer);
    }
});
```

## BeforeEach/AfterEach Hooks

### Global Hooks (All Tests)

```typescript
// playwright.config.ts
import { defineConfig } from '@playwright/test';

export default defineConfig({
    use: {
        // Global beforeEach via fixture
        baseURL: 'http://localhost:3000',

        // Take screenshot on failure
        screenshot: 'only-on-failure',

        // Record video on failure
        video: 'retain-on-failure'
    }
});
```

### Test File Hooks

```typescript
// tests/features/products/products.spec.ts
import { test } from '../../fixtures/database.fixture';

test.beforeEach(async ({ page }) => {
    // Runs before each test in this file
    await page.goto('/products');
});

test.afterEach(async ({ page }, testInfo) => {
    // Runs after each test
    if (testInfo.status !== 'passed') {
        // Take extra screenshot on failure
        await page.screenshot({
            path: `failures/${testInfo.title}.png`,
            fullPage: true
        });
    }
});
```

### Describe Block Hooks

```typescript
test.describe('Product Management', () => {
    test.beforeEach(async ({ page, loggedInAsAdmin }) => {
        // Runs before each test in this describe block
        await page.goto('/admin/products');
    });

    test('should create product', async ({ page }) => {
        // Test implementation
    });
});
```

## Custom Fixtures

### API Client Fixture

```typescript
// fixtures/api.fixture.ts
import { test as base } from 'playwright-bdd';
import { APIRequestContext } from '@playwright/test';

export const test = base.extend<{ api: APIRequestContext }>({
    api: async ({ playwright }, use) => {
        const context = await playwright.request.newContext({
            baseURL: 'http://localhost:3000/api',
            extraHTTPHeaders: {
                Accept: 'application/json'
            }
        });

        await use(context);
        await context.dispose();
    }
});

// Usage
Given('an API product exists', async ({ api }) => {
    const product = productFactory.build();
    await api.post('/products', { data: product });
});
```

### Storage State Fixture

```typescript
// fixtures/storage.fixture.ts
export const test = base.extend<{ savedLogin: void }>({
    savedLogin: async ({ page }, use) => {
        // Login once
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@example.com');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();

        // Save authenticated state
        await page.context().storageState({ path: 'auth.json' });

        await use();
    }
});

// Reuse in multiple tests
test.use({ storageState: 'auth.json' });
```

## Error Handling

### Graceful Failure

```typescript
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use, testInfo) => {
            try {
                await db.migrate.rollback();
                await db.migrate.latest();
            } catch (error) {
                console.error('DB setup failed:', error);
                testInfo.skip(); // Skip test if setup fails
            }

            await use();
        },
        { scope: 'test', auto: true }
    ]
});
```

### Retry Configuration

```typescript
// playwright.config.ts
export default defineConfig({
    retries: process.env.CI ? 2 : 0,

    projects: [
        {
            name: 'setup-db',
            testMatch: /setup\.ts/,
            teardown: 'cleanup-db'
        },
        {
            name: 'tests',
            dependencies: ['setup-db']
        },
        {
            name: 'cleanup-db',
            testMatch: /cleanup\.ts/
        }
    ]
});
```

## Testing Hooks

```typescript
// fixtures/__tests__/database.fixture.test.ts
import { test, expect } from '@playwright/test';
import { db } from '../../support/database';

test.describe('Database Fixture', () => {
    test('should reset database', async () => {
        // Insert data
        await db('users').insert({ email: 'test@example.com' });

        // Manually trigger reset
        await db.migrate.rollback();
        await db.migrate.latest();

        // Verify clean state
        const users = await db('users').select();
        expect(users).toHaveLength(0);
    });
});
```

## Performance Optimization

### Reuse Connections

```typescript
// support/database.ts
let dbInstance: Knex | null = null;

export const getDb = () => {
    if (!dbInstance) {
        dbInstance = knex({
            client: 'mysql2',
            connection: process.env.DATABASE_URL,
            pool: { min: 2, max: 10 }
        });
    }
    return dbInstance;
};

export const db = getDb();
```

### Parallel-Safe Seeds

```typescript
export const test = base.extend<{}, { parallelSafeSeed: void }>({
    parallelSafeSeed: [
        async ({}, use, testInfo) => {
            const lockName = `seed_${testInfo.workerIndex}`;

            // Use named lock to prevent race conditions
            await db.raw('SELECT GET_LOCK(?, 10)', [lockName]);

            try {
                await seedFullDatabase();
                await use();
            } finally {
                await db.raw('SELECT RELEASE_LOCK(?)', [lockName]);
            }
        },
        { scope: 'worker', auto: true }
    ]
});
```
