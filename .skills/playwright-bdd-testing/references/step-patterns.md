# Reusable Step Definitions Library

Comprehensive collection of composable, visual-content-based step definitions.

## Organization Principles

1. **Group by domain** - navigation, forms, assertions, tables, etc.
2. **Use visual content** - roles, labels, text over IDs
3. **Make composable** - small, single-purpose steps
4. **Parameterize flexibly** - use Gherkin expressions

## Common Step Library

### Navigation Steps

```typescript
// steps/common/navigation.steps.ts
import { createBdd } from 'playwright-bdd';

const { Given, When } = createBdd();

Given('I am on the home page', async ({ page }) => {
    await page.goto('/');
});

When('I navigate to {string}', async ({ page }, path: string) => {
    await page.goto(path);
});

When('I click the {string} link', async ({ page }, linkText: string) => {
    await page.getByRole('link', { name: linkText }).click();
});

When('I click the {string} button', async ({ page }, buttonText: string) => {
    await page.getByRole('button', { name: buttonText }).click();
});

When('I go back', async ({ page }) => {
    await page.goBack();
});

When('I refresh the page', async ({ page }) => {
    await page.reload();
});
```

### Form Steps

```typescript
// steps/common/forms.steps.ts
const { When } = createBdd();

When('I fill {string} with {string}', async ({ page }, label: string, value: string) => {
    await page.getByLabel(label).fill(value);
});

When('I fill in:', async ({ page }, dataTable) => {
    for (const row of dataTable.hashes()) {
        await page.getByLabel(row.field).fill(row.value);
    }
});

When('I select {string} from {string}', async ({ page }, option: string, label: string) => {
    await page.getByLabel(label).selectOption(option);
});

When('I check {string}', async ({ page }, label: string) => {
    await page.getByLabel(label).check();
});

When('I uncheck {string}', async ({ page }, label: string) => {
    await page.getByLabel(label).uncheck();
});

When('I upload {string} to {string}', async ({ page }, filename: string, label: string) => {
    const filePath = path.join(__dirname, '../../fixtures/files', filename);
    await page.getByLabel(label).setInputFiles(filePath);
});

When('I submit the form', async ({ page }) => {
    await page.getByRole('button', { name: /submit|save|send/i }).click();
});
```

### Assertion Steps

```typescript
// steps/common/assertions.steps.ts
const { Then } = createBdd();

Then('I should see {string}', async ({ page }, text: string) => {
    await expect(page.getByText(text)).toBeVisible();
});

Then('I should not see {string}', async ({ page }, text: string) => {
    await expect(page.getByText(text)).not.toBeVisible();
});

Then('I should see the heading {string}', async ({ page }, heading: string) => {
    await expect(page.getByRole('heading', { name: heading })).toBeVisible();
});

Then('I should be on {string}', async ({ page }, path: string) => {
    await expect(page).toHaveURL(new RegExp(path));
});

Then('the page title should be {string}', async ({ page }, title: string) => {
    await expect(page).toHaveTitle(title);
});

Then('I should see a success message', async ({ page }) => {
    await expect(
        page.getByRole('alert').filter({ hasText: /success|saved|updated/i })
    ).toBeVisible();
});

Then('I should see an error message', async ({ page }) => {
    await expect(
        page.getByRole('alert').filter({ hasText: /error|failed|invalid/i })
    ).toBeVisible();
});

Then(
    'the {string} field should contain {string}',
    async ({ page }, label: string, value: string) => {
        await expect(page.getByLabel(label)).toHaveValue(value);
    }
);

Then('the {string} field should be empty', async ({ page }, label: string) => {
    await expect(page.getByLabel(label)).toBeEmpty();
});
```

### Table/List Steps

```typescript
// steps/common/tables.steps.ts
const { Then, When } = createBdd();

// Basic table assertions
Then('I should see {int} rows in the table', async ({ page }, count: number) => {
    await expect(page.getByRole('row')).toHaveCount(count + 1); // +1 for header
});

Then('I should see {string} in the table', async ({ page }, text: string) => {
    await expect(page.getByRole('row').filter({ hasText: text })).toBeVisible();
});

Then('I should not see {string} in the table', async ({ page }, text: string) => {
    await expect(page.getByRole('row').filter({ hasText: text })).not.toBeVisible();
});

// Row-based actions (text-based selection - PREFERRED)
When('I click {string} in the {string} row', async ({ page }, action: string, rowText: string) => {
    const row = page.getByRole('row').filter({ hasText: rowText });
    await row.getByRole('button', { name: action }).click();
});

When('I click {string} for {string}', async ({ page }, action: string, identifier: string) => {
    // Find row by any visible text, then click the action button
    const row = page.getByRole('row').filter({ hasText: identifier });
    await row.getByRole('button', { name: action }).click();
});

// Advanced: Position-based selection (for complex cases)
When(
    'I click the {int}st/nd/rd/th {string} in the table',
    async ({ page }, position: number, action: string) => {
        // Get all rows (excluding header), then select by position
        const rows = page.getByRole('row').filter({ hasNotText: /^(?!.*th)/ });
        const targetRow = rows.nth(position - 1);
        await targetRow.getByRole('button', { name: action }).click();
    }
);

// Column-specific operations
Then(
    'the {int}st/nd/rd/th row in column {string} should contain {string}',
    async ({ page }, rowIndex: number, columnName: string, expectedText: string) => {
        // Find column index by header text
        const headers = page.getByRole('columnheader');
        const headerCount = await headers.count();
        let columnIndex = -1;

        for (let i = 0; i < headerCount; i++) {
            const headerText = await headers.nth(i).textContent();
            if (headerText?.trim() === columnName) {
                columnIndex = i;
                break;
            }
        }

        if (columnIndex === -1) throw new Error(`Column "${columnName}" not found`);

        // Get the cell at the specified row and column
        const rows = page.getByRole('row').filter({ hasNotText: /^(?!.*th)/ });
        const targetRow = rows.nth(rowIndex - 1);
        const cells = targetRow.getByRole('cell');
        const targetCell = cells.nth(columnIndex);

        await expect(targetCell).toContainText(expectedText);
    }
);

// Encapsulated complex table operations
When('I edit the second product in the list', async ({ page }) => {
    // Encapsulate complex selector logic in custom steps
    const productRows = page.getByRole('row').filter({ hasText: /product/i });
    const secondRow = productRows.nth(1);
    await secondRow.getByRole('button', { name: /edit/i }).click();
});

// Data table assertions
Then('the table should contain:', async ({ page }, dataTable) => {
    for (const row of dataTable.hashes()) {
        const tableRow = page.getByRole('row').filter({ hasText: row.name });
        for (const [key, value] of Object.entries(row)) {
            if (key !== 'name') {
                await expect(tableRow).toContainText(value);
            }
        }
    }
});
```

### Wait/State Steps

```typescript
// steps/common/waits.steps.ts
const { When, Then } = createBdd();

// ✅ Condition-based waits (PREFERRED)
When('I wait for {string} to appear', async ({ page }, text: string) => {
    await page.getByText(text).waitFor({ state: 'visible' });
});

When('I wait for {string} to disappear', async ({ page }, text: string) => {
    await page.getByText(text).waitFor({ state: 'hidden' });
});

When('I wait for the page to load', async ({ page }) => {
    await page.waitForLoadState('networkidle');
});

When('I wait for navigation', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
});

Then('the page should be loading', async ({ page }) => {
    await expect(page.getByRole('status', { name: /loading/i })).toBeVisible();
});

Then('the page should stop loading', async ({ page }) => {
    await expect(page.getByRole('status', { name: /loading/i })).not.toBeVisible();
});

// ⚠️ AVOID: Explicit timeouts (use only as last resort)
// When('I wait {int} seconds', async ({ page }, seconds: number) => {
//   await page.waitForTimeout(seconds * 1000); // ANTI-PATTERN!
// });
```

### Modal/Dialog Steps

```typescript
// steps/common/modals.steps.ts
const { When, Then } = createBdd();

Then('I should see a modal with {string}', async ({ page }, text: string) => {
    await expect(page.getByRole('dialog').getByText(text)).toBeVisible();
});

When('I close the modal', async ({ page }) => {
    await page.getByRole('button', { name: /close|cancel|×/i }).click();
});

When('I confirm the modal', async ({ page }) => {
    await page.getByRole('button', { name: /confirm|ok|yes|continue/i }).click();
});

When('I accept the alert', async ({ page }) => {
    page.on('dialog', (dialog) => dialog.accept());
});

When('I dismiss the alert', async ({ page }) => {
    page.on('dialog', (dialog) => dialog.dismiss());
});
```

## Custom Steps for ID-Based Selection

When visual content is insufficient, encapsulate ID-based logic:

```typescript
// steps/custom/products.steps.ts
const { When } = createBdd();

// Encapsulate the ID lookup
When('I view the featured product', async ({ page }) => {
    // ID is hidden inside the step, not in the feature file
    const featuredSection = page.getByTestId('featured-products');
    await featuredSection.getByRole('link').first().click();
});

When('I add the first search result to cart', async ({ page }) => {
    const results = page.getByTestId('search-results');
    await results
        .getByRole('button', { name: /add to cart/i })
        .first()
        .click();
});

// Better: Use data attributes for dynamic content
When('I select the {string} tab', async ({ page }, tabName: string) => {
    // Use semantic data attribute, not numeric ID
    await page.getByRole('tab', { name: tabName }).click();
});
```

## Domain-Specific Step Examples

### Authentication

```typescript
// steps/auth/auth.steps.ts
const { Given, When } = createBdd();

Given('I am logged in as {string}', async ({ page }, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('testPassword123');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByText('Welcome')).toBeVisible();
});

Given('I am logged out', async ({ page, context }) => {
    await context.clearCookies();
});

When('I log in with:', async ({ page }, dataTable) => {
    const credentials = dataTable.hashes()[0];
    await page.goto('/login');
    await page.getByLabel('Email').fill(credentials.email);
    await page.getByLabel('Password').fill(credentials.password);
    await page.getByRole('button', { name: 'Log in' }).click();
});
```

### E-commerce

```typescript
// steps/ecommerce/cart.steps.ts
const { When, Then } = createBdd();

When('I add {string} to cart', async ({ page }, productName: string) => {
    await page
        .getByRole('article')
        .filter({ hasText: productName })
        .getByRole('button', { name: /add to cart/i })
        .click();
});

When('I remove {string} from cart', async ({ page }, productName: string) => {
    const cartItem = page.getByRole('listitem').filter({ hasText: productName });
    await cartItem.getByRole('button', { name: /remove/i }).click();
});

Then('my cart should contain {int} items', async ({ page }, count: number) => {
    await expect(page.getByTestId('cart-count')).toHaveText(count.toString());
});

Then('the cart total should be {string}', async ({ page }, amount: string) => {
    await expect(page.getByTestId('cart-total')).toHaveText(amount);
});
```

### Search & Filters

```typescript
// steps/search/search.steps.ts
const { When, Then } = createBdd();

When('I search for {string}', async ({ page }, query: string) => {
    await page.getByRole('searchbox').fill(query);
    await page.getByRole('button', { name: /search/i }).click();
});

When('I filter by {string}', async ({ page }, filterName: string) => {
    await page.getByRole('checkbox', { name: filterName }).check();
});

When('I clear all filters', async ({ page }) => {
    await page.getByRole('button', { name: /clear filters/i }).click();
});

Then('I should see {int} search results', async ({ page }, count: number) => {
    await expect(page.getByRole('article')).toHaveCount(count);
});

Then('all results should match {string}', async ({ page }, criteria: string) => {
    const results = await page.getByRole('article').all();
    for (const result of results) {
        await expect(result).toContainText(criteria);
    }
});
```

## Parameter Types

Define custom parameter types for reusability:

```typescript
// steps/parameterTypes.ts
import { defineParameterType } from 'playwright-bdd';

defineParameterType({
    name: 'username',
    regexp: /@(\w+)/,
    transformer: (match) => match
});

defineParameterType({
    name: 'money',
    regexp: /\$(\d+(?:\.\d{2})?)/,
    transformer: (amount) => parseFloat(amount)
});

// Usage in steps
Then('I should see {money} in my account', async ({ page }, amount: number) => {
    await expect(page.getByTestId('balance')).toHaveText(`$${amount.toFixed(2)}`);
});
```

## Advanced Waiting Strategies

### The Golden Rule: Never Use waitForTimeout

❌ **NEVER DO THIS:**

```typescript
await page.waitForTimeout(5000); // Arbitrary wait - FORBIDDEN!
```

✅ **ALWAYS USE CONDITION-BASED WAITS:**

### 1. Built-in Auto-Waiting

Playwright automatically waits for most actions:

```typescript
// Auto-waits for element to be visible, enabled, and stable
await page.getByRole('button', { name: 'Submit' }).click();

// Auto-waits for element to be editable
await page.getByLabel('Email').fill('user@example.com');

// Auto-waits for element to be visible
await expect(page.getByText('Success!')).toBeVisible();
```

### 2. Explicit Condition Waits

When auto-waiting isn't enough:

```typescript
// Wait for specific state
await page.getByText('Loading...').waitFor({ state: 'hidden' });
await page.getByRole('dialog').waitFor({ state: 'visible' });

// Wait for network to be idle
await page.waitForLoadState('networkidle');

// Wait for DOM content loaded
await page.waitForLoadState('domcontentloaded');

// Wait for a specific URL
await page.waitForURL('**/dashboard');
await page.waitForURL(/\/products\/\d+/);

// Wait for response
await page.waitForResponse(
    (response) => response.url().includes('/api/products') && response.status() === 200
);
```

### 3. Retry Pattern (Recommended)

For operations that may take varying time:

```typescript
// steps/common/retry.steps.ts
async function waitForCondition(
    checkFn: () => Promise<boolean>,
    options: { timeout?: number; interval?: number } = {}
): Promise<void> {
    const { timeout = 10000, interval = 500 } = options;
    const startTime = Date.now();

    while (Date.now() - startTime < timeout) {
        if (await checkFn()) return;
        await page.waitForTimeout(interval); // Only acceptable use!
    }

    throw new Error(`Condition not met within ${timeout}ms`);
}

// Usage in steps
Then('the cart should update', async ({ page }) => {
    await waitForCondition(
        async () => {
            const count = await page.getByTestId('cart-count').textContent();
            return count === '3';
        },
        { timeout: 10000, interval: 500 }
    );
});
```

### 4. Polling Pattern (Advanced Cases)

For continuously changing data:

```typescript
// steps/common/polling.steps.ts
async function pollUntil<T>(
    getFn: () => Promise<T>,
    condition: (value: T) => boolean,
    options: { timeout?: number; interval?: number } = {}
): Promise<T> {
    const { timeout = 30000, interval = 1000 } = options;
    const startTime = Date.now();

    while (Date.now() - startTime < timeout) {
        const value = await getFn();
        if (condition(value)) return value;
        await new Promise((resolve) => setTimeout(resolve, interval));
    }

    throw new Error(`Polling timeout after ${timeout}ms`);
}

// Usage: Wait for processing to complete
Then('the order should be processed', async ({ page, api }) => {
    const order = await pollUntil(
        async () => {
            const response = await api.get('/orders/123');
            return await response.json();
        },
        (order) => order.status === 'processed',
        { timeout: 30000, interval: 2000 }
    );

    expect(order.status).toBe('processed');
});
```

### 5. Custom Wait Steps

Encapsulate complex waits in reusable steps:

```typescript
// steps/common/advanced-waits.steps.ts
When('I wait for the search results to load', async ({ page }) => {
    // Wait for loading indicator to disappear
    await page.getByTestId('search-loading').waitFor({ state: 'hidden' });

    // Wait for at least one result to appear
    await page.getByRole('article').first().waitFor({ state: 'visible' });

    // Wait for network to stabilize
    await page.waitForLoadState('networkidle');
});

When('I wait for file upload to complete', async ({ page }) => {
    // Wait for progress bar to appear
    await page.getByRole('progressbar').waitFor({ state: 'visible' });

    // Wait for progress bar to disappear
    await page.getByRole('progressbar').waitFor({
        state: 'hidden',
        timeout: 60000 // Longer timeout for file uploads
    });

    // Wait for success message
    await expect(page.getByText('Upload complete')).toBeVisible();
});

When('I wait for background processing', async ({ page }) => {
    // Poll API until job completes
    await page.waitForResponse(
        async (response) => {
            if (!response.url().includes('/api/job/status')) return false;
            const data = await response.json();
            return data.status === 'completed';
        },
        { timeout: 60000 }
    );
});
```

### 6. Expectations with Custom Timeouts

Adjust timeouts for specific expectations:

```typescript
// Longer timeout for slow operations
await expect(page.getByText('Report generated')).toBeVisible({ timeout: 30000 });

// Shorter timeout for quick checks
await expect(page.getByText('Error')).not.toBeVisible({ timeout: 2000 });

// Wait for multiple conditions
await Promise.all([
    expect(page.getByText('User created')).toBeVisible(),
    expect(page.getByRole('button', { name: 'Edit' })).toBeEnabled(),
    expect(page).toHaveURL(/\/users\/\d+/)
]);
```

### 7. Network-Based Waits

Wait for specific network activity:

```typescript
When('I wait for data to save', async ({ page }) => {
    // Start waiting for response BEFORE triggering action
    const responsePromise = page.waitForResponse(
        (response) => response.url().includes('/api/save') && response.ok()
    );

    await page.getByRole('button', { name: 'Save' }).click();

    const response = await responsePromise;
    expect(response.status()).toBe(200);
});

When('I wait for images to load', async ({ page }) => {
    // Wait for all images to load
    await page.waitForLoadState('load');

    // Verify images loaded successfully
    const images = page.getByRole('img');
    const count = await images.count();

    for (let i = 0; i < count; i++) {
        await expect(images.nth(i)).toHaveJSProperty('complete', true);
    }
});
```

## Best Practices

1. **Keep steps atomic** - One action/assertion per step
2. **Use semantic roles** - button, link, heading, textbox
3. **Leverage ARIA** - getByRole with accessible names
4. **Fail fast** - Use strict locators (fail if multiple matches)
5. **NO explicit timeouts** - Use waitFor, auto-waiting, or retry patterns
6. **Encapsulate IDs** - Hide test IDs inside custom steps
7. **Make readable** - Steps should read like English sentences
8. **Prefer built-in waits** - Use Playwright's auto-waiting before custom solutions

## Anti-Patterns

### ❌ 1. Technical Implementation in Steps

```typescript
// Bad - Exposes technical details
When('I click element with selector {string}', async ({ page }, selector) => {
    await page.locator(selector).click();
});

When('I set value {string} to input #email', async ({ page }, value) => {
    await page.locator('#email').fill(value);
});
```

✅ **Good - Business language**

```typescript
When('I add a new product', async ({ page }) => {
    await page.getByRole('button', { name: 'Add Product' }).click();
});

When('I enter my email address', async ({ page }) => {
    await page.getByLabel('Email').fill('user@example.com');
});
```

### ❌ 2. Explicit Timeouts (CRITICAL ANTI-PATTERN)

```typescript
// Bad - Arbitrary waits
When('I wait for results', async ({ page }) => {
    await page.waitForTimeout(5000); // NEVER DO THIS!
});

When('I submit the form', async ({ page }) => {
    await page.getByRole('button', { name: 'Submit' }).click();
    await page.waitForTimeout(2000); // Waiting for "things to happen"
});

// Bad - Sleeping between actions
When('I complete the checkout', async ({ page }) => {
    await page.getByRole('button', { name: 'Next' }).click();
    await page.waitForTimeout(1000); // ❌
    await page.getByLabel('Card Number').fill('4242424242424242');
    await page.waitForTimeout(1000); // ❌
    await page.getByRole('button', { name: 'Pay' }).click();
});
```

✅ **Good - Condition-based waits**

```typescript
When('I wait for results', async ({ page }) => {
    await page.getByRole('heading', { name: 'Search Results' }).waitFor();
});

When('I submit the form', async ({ page }) => {
    await page.getByRole('button', { name: 'Submit' }).click();
    // Playwright auto-waits for navigation or use explicit condition
    await expect(page.getByText('Form submitted')).toBeVisible();
});

When('I complete the checkout', async ({ page }) => {
    await page.getByRole('button', { name: 'Next' }).click();
    // Wait for next step to be visible
    await page.getByLabel('Card Number').waitFor({ state: 'visible' });
    await page.getByLabel('Card Number').fill('4242424242424242');
    await page.getByRole('button', { name: 'Pay' }).click();
    // Wait for confirmation
    await expect(page.getByText('Payment successful')).toBeVisible();
});
```

### ❌ 3. CSS/ID Selectors in Feature Files

```typescript
// Bad - Technical selectors exposed
When('I click {string}', async ({ page }, selector) => {
    await page.locator(selector).click();
});

// Feature file:
// When I click "#submit-button"
// When I click ".nav-menu > li:nth-child(2)"
```

✅ **Good - Semantic selectors**

```typescript
When('I click the {string} button', async ({ page }, buttonName) => {
    await page.getByRole('button', { name: buttonName }).click();
});

// Feature file:
// When I click the "Submit" button
// When I click the "Products" link
```

### ❌ 4. Hardcoded IDs in Test Data

```typescript
// Bad - Fragile, breaks if DB state changes
Given('product {int} exists', async ({ page }, productId) => {
    await db('products').insert({ id: productId, name: 'Test Product' });
});

When('I view product {int}', async ({ page }, id) => {
    await page.goto(`/products/${id}`);
});
```

✅ **Good - Dynamic references**

```typescript
Given('a product named {string} exists', async ({ page }, name, world) => {
    const product = await productFactory.create({ name });
    world.currentProduct = product;
});

When('I view that product', async ({ page }, world) => {
    await page.goto(`/products/${world.currentProduct.slug}`);
});
```
