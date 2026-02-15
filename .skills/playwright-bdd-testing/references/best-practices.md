# Best Practices Guide

Comprehensive testing patterns, anti-patterns, and decision frameworks.

## Test Design Principles

### 1. Write Tests as Documentation

Tests should read like specifications:

```gherkin
# ✅ Good - Business readable
Feature: Shopping Cart
  Scenario: Apply discount code
    Given I have "Premium Headphones" in my cart
    When I apply discount code "SAVE20"
    Then my cart total should show a 20% discount

# ❌ Bad - Implementation focused
Feature: Cart API
  Scenario: POST discount endpoint
    Given cart_id is "abc123"
    When I POST to "/api/cart/abc123/discount" with code "SAVE20"
    Then response status is 200
```

### 2. Test Behavior, Not Implementation

```typescript
// ✅ Good - Tests behavior
Then('I should see my order confirmation', async ({ page }) => {
    await expect(page.getByText(/order #\d+/i)).toBeVisible();
    await expect(page.getByText(/thank you/i)).toBeVisible();
});

// ❌ Bad - Tests implementation
Then('order confirmation component renders', async ({ page }) => {
    await expect(page.locator('[data-component="OrderConfirmation"]')).toBeVisible();
    await expect(page.locator('.confirmation-header')).toHaveClass('active');
});
```

### 3. Maintain Test Independence

```typescript
// ✅ Good - Each test stands alone
test.describe('Product Reviews', () => {
    test.beforeEach(async ({ page }) => {
        const product = await productFactory.create();
        await page.goto(`/products/${product.slug}`);
    });

    test('should submit review', async ({ page }) => {
        // Test implementation
    });

    test('should validate rating', async ({ page }) => {
        // Test implementation
    });
});

// ❌ Bad - Tests depend on execution order
test.describe('Product Reviews', () => {
    let productId: string;

    test('should create product', async () => {
        productId = await createProduct();
    });

    test('should submit review', async ({ page }) => {
        await page.goto(`/products/${productId}`); // Breaks if first test fails
    });
});
```

## Selector Strategy

### Priority Order

1. **User-facing text/labels** (highest priority)
2. **Accessible roles with names**
3. **Test IDs** (encapsulated in custom steps)
4. **CSS selectors** (last resort, avoid)

### Examples

```typescript
// 1. User-facing text (best)
await page.getByText('Add to Cart').click();
await page.getByLabel('Email Address').fill('user@example.com');

// 2. Accessible roles
await page.getByRole('button', { name: 'Submit' }).click();
await page.getByRole('navigation').getByRole('link', { name: 'Products' }).click();

// 3. Test IDs (when needed, encapsulate)
// In custom step, not exposed in Gherkin
const sidebar = page.getByTestId('product-filters');
await sidebar.getByRole('checkbox', { name: 'In Stock' }).check();

// 4. CSS selectors (avoid)
// Only for complex layouts where nothing else works
const modal = page.locator('[data-modal-id="checkout"]');
```

## Data Management

### Factory Over Fixtures

```typescript
// ✅ Good - Dynamic, flexible
const user = await userFactory.create({
    role: 'admin',
    permissions: ['read', 'write']
});

// ❌ Bad - Static, inflexible
const user = await db('users').insert({
    id: 1, // Hardcoded ID!
    email: 'admin@test.com', // Same every time
    role: 'admin'
});
```

### Realistic Test Data

```typescript
// ✅ Good - Realistic variations
export const productFactory = Factory.define<Product>(() => ({
    name: faker.commerce.productName(),
    price: parseFloat(faker.commerce.price()),
    description: faker.commerce.productDescription(),
    stock: faker.number.int({ min: 0, max: 100 })
}));

// ❌ Bad - Unrealistic patterns
export const productFactory = Factory.define<Product>(() => ({
    name: 'Product 1',
    price: 9.99,
    description: 'Test product',
    stock: 10
}));
```

## Assertions

### Explicit Over Implicit

```typescript
// ✅ Good - Clear expectation
Then('order should be marked as shipped', async ({ page }) => {
    await expect(page.getByText('Status: Shipped')).toBeVisible();
    await expect(page.getByText(/tracking: \d+/i)).toBeVisible();
});

// ❌ Bad - Vague assertion
Then('order page should be correct', async ({ page }) => {
    await expect(page.locator('.status')).toBeVisible();
});
```

### Multiple Small Assertions

```typescript
// ✅ Good - Specific failures
Then('product details should be displayed', async ({ page }) => {
    await expect(page.getByRole('heading')).toContainText('Premium Headphones');
    await expect(page.getByText('$199.99')).toBeVisible();
    await expect(page.getByText('In Stock')).toBeVisible();
});

// ❌ Bad - Single broad assertion
Then('product details should be displayed', async ({ page }) => {
    await expect(page.locator('.product-details')).toBeVisible();
});
```

## Error Handling

### Meaningful Error Messages

```typescript
// ✅ Good - Descriptive
await expect(
    page.getByRole('alert'),
    'Expected success message after form submission'
).toContainText('Profile updated');

// ❌ Bad - Generic
await expect(page.locator('.alert')).toBeVisible();
```

### Soft Assertions for Multiple Checks

```typescript
Then('all form validations should appear', async ({ page }) => {
    await expect.soft(page.getByText('Email is required')).toBeVisible();
    await expect.soft(page.getByText('Password is required')).toBeVisible();
    await expect.soft(page.getByText('Name is required')).toBeVisible();
    // All assertions run even if one fails
});
```

## Performance

### Parallel Execution

```typescript
// playwright.config.ts
export default defineConfig({
    fullyParallel: true,
    workers: process.env.CI ? 2 : undefined,

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] }
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] }
        }
    ]
});
```

### Optimize Database Operations

```typescript
// ✅ Good - Batch operations
Given('100 products exist', async () => {
    const products = productFactory.buildList(100);
    await db.batchInsert('products', products, 50);
});

// ❌ Bad - Individual inserts
Given('100 products exist', async () => {
    for (let i = 0; i < 100; i++) {
        await productFactory.create(); // 100 separate queries!
    }
});
```

### API Setup Over UI

```typescript
// ✅ Good - Fast API setup
Given('I have products in my cart', async ({ api }) => {
    const cart = await api.post('/cart', {
        data: { items: productFactory.buildList(3) }
    });
    // Then navigate to cart page
});

// ❌ Bad - Slow UI navigation
Given('I have products in my cart', async ({ page }) => {
    await page.goto('/products');
    await page.getByText('Product 1').click();
    await page.getByRole('button', { name: 'Add to Cart' }).click();
    // Repeat for each product...
});
```

## Code Organization

### Feature File Structure

```
features/
├── auth/
│   ├── login.feature
│   ├── registration.feature
│   └── password-reset.feature
├── products/
│   ├── search.feature
│   ├── filters.feature
│   └── details.feature
└── checkout/
    ├── cart.feature
    ├── payment.feature
    └── confirmation.feature
```

### Step Definition Structure

```
steps/
├── common/
│   ├── navigation.steps.ts
│   ├── forms.steps.ts
│   ├── assertions.steps.ts
│   └── tables.steps.ts
├── auth/
│   └── auth.steps.ts
├── products/
│   └── products.steps.ts
└── checkout/
    └── checkout.steps.ts
```

## Debugging

### Leave Data for Investigation

```typescript
// ✅ Good - Keep data after test
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use) => {
            await db.migrate.latest();
            await use();
            // NO cleanup - data stays for debugging
        },
        { scope: 'test', auto: true }
    ]
});

// ❌ Bad - Clean up after test
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use) => {
            await db.migrate.latest();
            await use();
            await db.raw('TRUNCATE TABLE users CASCADE'); // Data lost!
        },
        { scope: 'test', auto: true }
    ]
});
```

### Debug Mode

```typescript
// steps/debug.steps.ts
When('I pause for debugging', async ({ page }) => {
    await page.pause(); // Opens Playwright Inspector
});

When('I take a screenshot', async ({ page }, world) => {
    await page.screenshot({
        path: `debug/${world.scenario.name}.png`,
        fullPage: true
    });
});
```

## Common Anti-Patterns

### ❌ 1. Explicit Timeouts (MOST CRITICAL)

This is the #1 anti-pattern to avoid. **NEVER use `waitForTimeout` unless implementing a custom retry/polling mechanism.**

```typescript
// ❌ BAD - Arbitrary waits
await page.waitForTimeout(5000);
await page.click('button');
await page.waitForTimeout(2000); // "Waiting for things to happen"

// ❌ BAD - Even with comments
await page.waitForTimeout(3000); // Wait for API call
// API calls have variable latency - this will be flaky!

// ❌ BAD - Sleep between actions
await page.fill('input', 'value');
await page.waitForTimeout(500);
await page.click('button');
```

**Why is this bad?**

- Tests become flaky (sometimes too short, sometimes unnecessarily long)
- Wastes time in CI/CD (waits even when not needed)
- Masks real issues (like missing loading states)
- Makes tests harder to maintain

```typescript
// ✅ GOOD - Condition-based waits
await page.getByText('Loading complete').waitFor();
await page.waitForLoadState('networkidle');
await expect(page.getByText('Success')).toBeVisible();

// ✅ GOOD - Playwright auto-waits
await page.getByRole('button', { name: 'Submit' }).click();
// No manual wait needed!

// ✅ GOOD - Wait for specific state
await page.getByTestId('spinner').waitFor({ state: 'hidden' });

// ✅ GOOD - Network-based waits
await page.waitForResponse((res) => res.url().includes('/api/save') && res.ok());

// ✅ ACCEPTABLE - Only within custom retry helper
async function waitForCondition(checkFn, timeout = 10000) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
        if (await checkFn()) return;
        await page.waitForTimeout(500); // OK here - it's a polling interval
    }
    throw new Error('Timeout');
}
```

### ❌ 2. Hardcoded Waits in CI/CD

```typescript
// Bad - CI is slower, needs longer waits
if (process.env.CI) {
    await page.waitForTimeout(10000);
} else {
    await page.waitForTimeout(3000);
}

// Good - Condition works in any environment
await page.getByText('Data loaded').waitFor({ timeout: 30000 });
```

### ❌ 3. Over-Mocking

```typescript
// Bad - Mocks everything
test.beforeEach(async ({ page }) => {
    await page.route('**/*', (route) => route.fulfill({ status: 200 }));
});

// Good - Mock only what's necessary
test.beforeEach(async ({ page }) => {
    await page.route('**/api/analytics', (route) => route.abort());
});
```

### ❌ 4. God Objects

```typescript
// Bad - One fixture does everything
export const test = base.extend<{ everything: MegaHelper }>({
    everything: async ({}, use) => {
        const helper = {
            db: getDb(),
            api: getApi(),
            auth: getAuth()
            // 50 more methods...
        };
        await use(helper);
    }
});

// Good - Separate concerns
export const test = base.extend<{
    db: Database;
    api: APIClient;
    auth: AuthHelper;
}>({
    db: async ({}, use) => await use(getDb()),
    api: async ({}, use) => await use(getApi()),
    auth: async ({}, use) => await use(getAuth())
});
```

### ❌ 5. Brittle Selectors

```typescript
// Bad - CSS classes
await page.locator('.btn.btn-primary.submit-btn').click();

// Bad - Complex XPath
await page.locator('//div[@class="form"]//button[contains(text(),"Submit")]').click();

// Good - Semantic selectors
await page.getByRole('button', { name: 'Submit' }).click();
```

### ❌ 6. Fragile Comments

Comments with file paths or implementation details become stale and add technical debt:

```typescript
// ❌ BAD - Path reference (will become stale)
Given('the database is empty', async () => {
    // Handled automatically by the dbReset fixture defined in fixtures/bdd.ts
});

// ❌ BAD - Implementation details that will change
When('I submit the form', async ({ page }) => {
    // Uses the FormValidator class from src/validators/form.ts
    await page.getByRole('button', { name: 'Submit' }).click();
});

// ✅ GOOD - Brief, no paths
Given('the database is empty', async () => {
    // No-op: handled by dbReset fixture
});

// ✅ GOOD - Self-documenting code, no comment needed
When('I submit the form', async ({ page }) => {
    await page.getByRole('button', { name: 'Submit' }).click();
});
```

**Rule**: If a comment references a file path, it will become stale. Either remove it or make it generic.

### ❌ 7. Test Interdependence

```typescript
// Bad - Tests affect each other
test.describe.serial('User Journey', () => {
    let userId: string;

    test('create user', async () => {
        userId = await createUser();
    });

    test('update user', async () => {
        await updateUser(userId); // Breaks if previous test fails
    });
});

// Good - Independent tests
test.describe('User Journey', () => {
    test('create and update user', async () => {
        const userId = await createUser();
        await updateUser(userId);
    });
});
```

### ❌ 8. Deeply Nested Conditionals

Use early returns to flatten decision logic:

```typescript
// ❌ BAD - Nested if/else, hard to follow
function shouldResetDatabase(context) {
    let shouldReset = false;

    if (context.isCI) {
        shouldReset = true;
    } else if (context.tags.includes('@reset')) {
        shouldReset = true;
    } else if (context.tags.includes('@no-reset')) {
        shouldReset = false;
    } else if (!resetFeatures.has(context.featureTitle)) {
        shouldReset = true;
    }

    return shouldReset;
}

// ✅ GOOD - Early returns, each condition is independent
function shouldResetDatabase({ tags, featureTitle, isCI }: ResetContext): boolean {
    if (isCI) return true;
    if (tags.includes('@reset')) return true;
    if (tags.includes('@no-reset')) return false;
    return !resetFeatures.has(featureTitle);
}
```

Same principle applies to async fixture code:

```typescript
// ❌ BAD - Nested branches
async ({ request }, use, testInfo) => {
    if (shouldReset) {
        // reset logic...
        await use();
    } else {
        console.log('Reusing...');
        await use();
    }
};

// ✅ GOOD - Early return for skip case
async ({ request }, use, testInfo) => {
    if (!shouldResetDatabase(context)) {
        console.log('Reusing...');
        await use();
        return;
    }

    // reset logic...
    await use();
};
```

## Parallelism Strategy

### Feature-Level Parallelism (Industry Standard)

**Recommended approach** matching Behat/Cucumber/SpecFlow:

```
✅ GOOD: Features in parallel, Scenarios sequential

Worker 1: invoices.feature
  Scenario 1: Create invoice     ┐
  Scenario 2: Verify totals      ├─ Sequential (share state)
  Scenario 3: Update invoice     ┘

Worker 2: users.feature (runs at same time)
  Scenario 1: Register user      ┐
  Scenario 2: Login              ├─ Sequential (share state)
  Scenario 3: Update profile     ┘

Worker 3: products.feature (runs at same time)
  Scenario 1: Create product     ┐
  Scenario 2: List products      ├─ Sequential (share state)
  Scenario 3: Search products    ┘
```

**Configuration**:

```typescript
// playwright.config.ts
export default defineConfig({
    fullyParallel: true, // Features in parallel
    workers: process.env.CI ? 4 : 2
});
```

**Advantages**:

- ✅ Natural user journey modeling
- ✅ Less repetitive setup code
- ✅ Matches Behat/Cucumber standard
- ✅ Readable for non-technical stakeholders
- ✅ Fast enough (features still parallel)

**Trade-offs**:

- ⚠️ Cannot run single scenario in isolation (must run feature)
- ⚠️ If scenario 2 fails, scenario 3 might fail too
- ⚠️ Debugging requires running from beginning

### Scenario-Level Parallelism (Alternative)

**When to use**: When every scenario MUST be independent

```
❌ SLOWER: Every scenario in parallel (recreates setup)

Worker 1: invoices.feature - Scenario 1
Worker 2: invoices.feature - Scenario 2
Worker 3: invoices.feature - Scenario 3
Worker 4: users.feature - Scenario 1
...
```

**Configuration**:

```typescript
export default defineConfig({
    fullyParallel: true,
    workers: 10 // Can use many workers

    // Every scenario resets DB
    // Ignores @no-reset tags
});
```

**Advantages**:

- ✅ Complete scenario independence
- ✅ Can run any scenario alone
- ✅ Easier debugging (isolate failure)
- ✅ Maximum parallelism

**Disadvantages**:

- ❌ Repetitive Given steps
- ❌ Slower (recreate data N times)
- ❌ Less readable (verbose setups)
- ❌ Not how users actually use the system

### Recommendation

**Use Feature-Level** unless you have:

1. Very short scenarios (< 10 steps each)
2. Completely unrelated scenarios in same feature
3. Extremely long-running features (> 5 minutes)

## Testing Pyramid

```
    /\     E2E Tests (Playwright BDD)
   /  \    - Critical user journeys
  /----\   - Smoke tests
 /      \  Integration Tests
/________\ Unit Tests
```

**E2E Test Focus:**

- Happy paths for critical features
- Complete user journeys (registration → usage → deletion)
- Cross-browser compatibility
- User workflows end-to-end

**Avoid Testing at E2E Level:**

- Validation logic (test in unit tests)
- Error handling details (test in integration tests)
- Every edge case (test at lower levels when possible)
- Business logic calculations (test in unit tests)

## CI/CD Integration

```yaml
# .github/workflows/e2e.yml
name: E2E Tests
on: [push, pull_request]

jobs:
    test:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v3
            - uses: actions/setup-node@v3

            - name: Install dependencies
              run: npm ci

            - name: Install Playwright
              run: npx playwright install --with-deps

            - name: Run tests
              run: npm test

            - uses: actions/upload-artifact@v3
              if: always()
              with:
                  name: playwright-report
                  path: playwright-report/
```

## Review Checklist

Before merging tests:

### Gherkin & Structure

- [ ] Feature files read like business requirements (non-technical language)
- [ ] Scenarios follow Given/When/Then structure
- [ ] Features model complete user journeys (not isolated actions)
- [ ] Features are independent (can run in any order)
- [ ] Tests pass in parallel mode (features in parallel)

### Parallelism & Flow

- [ ] Features run in parallel (configured correctly)
- [ ] Scenarios within feature are sequential (model journeys)
- [ ] Tags used appropriately (@reset/@no-reset)
- [ ] First scenario of feature sets up initial state
- [ ] Subsequent scenarios build on previous (logical flow)
- [ ] @reset used when starting independent flow within feature
- [ ] @no-reset used explicitly when verification depends on previous

### Selectors & Actions

- [ ] **NO CSS/ID selectors** (use getByRole, getByText, getByLabel)
- [ ] Steps use visual content selectors (text-based)
- [ ] Complex selectors encapsulated in custom steps

### Waiting & Timing

- [ ] **NO explicit timeouts** (`waitForTimeout` forbidden)
- [ ] Use condition-based waits (waitFor, waitForLoadState)
- [ ] Use retry/polling patterns for dynamic content
- [ ] Leverage Playwright's auto-waiting

### Database & Data

- [ ] Database truncates at start of feature (first scenario)
- [ ] **NO cleanup after tests** (data persists for debugging)
- [ ] Scenarios share state within feature (natural flow)
- [ ] Use factories (Fishery + Faker) for test data
- [ ] No hardcoded IDs in factories or steps
- [ ] No SQL dumps (use structured factories)
- [ ] Tag logic implemented correctly in fixture

### Step Definitions

- [ ] Steps are atomic (one action/assertion per step)
- [ ] Steps are reusable across features
- [ ] Meaningful assertion messages
- [ ] Steps organized by domain (common/, auth/, etc.)

### Code Quality

- [ ] Realistic test data from factories
- [ ] No hardcoded test data
- [ ] TypeScript types used correctly
- [ ] Early returns used (no deeply nested if/else)
- [ ] No getters/setters (use property access)
- [ ] Code follows project conventions
- [ ] **No path references in comments** (become stale, add tech debt)

### CI/CD Compatibility

- [ ] Tests work in both dev and CI modes
- [ ] CI=true makes all scenarios independent
- [ ] @no-reset tags ignored in CI (verified)
- [ ] Tests don't rely on specific execution order
