---
name: playwright-bdd-testing
description: Comprehensive E2E testing framework using Playwright with BDD/Gherkin inspired by Behat/Mink best practices. Use when users need to create, structure, or improve automated tests with (1) Gherkin/BDD scenarios, (2) Text-based selectors (NOT CSS/IDs), (3) NO explicit timeouts (retry/polling patterns), (4) Database truncation + exclusive test data, (5) Structured factories (avoiding SQL dumps), (6) Reusable step library, (7) Advanced table operations, or (8) Test isolation strategies.
---

# Playwright BDD Testing

Modern E2E testing framework combining Playwright's power with BDD's readability, following battle-tested principles from Behat/Mink adapted to TypeScript.

## Core Philosophy

**Document behavior, not implementation**

- Write tests as executable specifications following real user journeys
- **ALWAYS** use text-based selectors (getByRole, getByText, getByLabel)
- **NEVER** use explicit timeouts (waitForTimeout) - use conditions, retry, or polling
- **Features are independent** - can run in parallel (industry standard)
- **Scenarios within a feature are sequential** - model user journeys naturally
- **Truncate DB before each feature** - scenarios share state for natural flow
- Use **@reset/@no-reset tags** for explicit control when needed
- Use **structured factories** with Faker - never SQL dumps or hardcoded IDs
- Build **reusable, atomic steps** - one action/assertion per step

## Quick Start

### Installation

```bash
npm install -D @playwright/test playwright-bdd @cucumber/cucumber
npm install -D @faker-js/faker fishery
npx playwright install
```

### Project Structure

**Domain-Centric Organization**
We organize tests by Domain and Use Case (Context). Each folder contains the feature documentation, step implementation, and generated specs.

```
tests/e2e/
├── common/                # Shared fixtures and helpers
│   ├── bdd.ts            # Main BDD fixture (Given/When/Then)
│   └── auth.ts           # Auth helpers
├── invoices/              # Domain: Invoices
│   └── invoices/         # Context: General Management
│       ├── invoices.feature          # Documentation (Gherkin)
│       ├── invoices.steps.ts         # Implementation (Steps)
│       └── invoices.feature.spec.js  # Generated Test (Do not edit manually)
├── patients/              # Domain: Patients
│   └── management/       # Context: Patient Management
│       ├── ...
└── playwright.config.ts
```

## Essential Patterns

### 1. Gherkin Features

Write business-readable scenarios:

```gherkin
Feature: Product Search

  Scenario: Search by category
    Given products exist in the "Electronics" category
    When I search for "laptop"
    Then I should see products matching "laptop"
    And all results should be in "Electronics" category
```

### 2. Datafixtures with Factories

**Read references/datafixtures.md for complete patterns**

```typescript
// factories/productFactory.ts
import { Factory } from 'fishery';

export const productFactory = Factory.define<Product>(() => ({
    name: faker.commerce.productName(),
    slug: faker.helpers.slugify(faker.commerce.productName()),
    price: parseFloat(faker.commerce.price())
}));

// Usage in steps
Given('products exist in the {string} category', async ({}, category) => {
    await productFactory.createList(3, { category });
});
```

### 3. Visual Content Selectors

**✅ DO:** Search by visible content

```typescript
await page.getByRole('button', { name: 'Add to Cart' }).click();
await page.getByText('Product added successfully').waitFor();
```

**❌ DON'T:** Hardcode IDs or test-specific selectors

```typescript
await page.locator('#product-123').click(); // Fragile!
await page.click('[data-testid="add-btn-456"]'); // Breaks on refactor
```

**When visual content is insufficient**, create custom encapsulated steps (see references/step-patterns.md).

### 4. Parallelism Model: Feature-Level (Industry Standard)

**Features = Parallel Units** | **Scenarios = Sequential Flow**

```
Feature A (Worker 1)      Feature B (Worker 2)      Feature C (Worker 3)
├─ Scenario 1             ├─ Scenario 1             ├─ Scenario 1
├─ Scenario 2  Sequential ├─ Scenario 2  Sequential ├─ Scenario 2  Sequential
└─ Scenario 3             └─ Scenario 3             └─ Scenario 3
       ↓                         ↓                         ↓
   IN PARALLEL              IN PARALLEL              IN PARALLEL
```

This matches Behat/Cucumber standard behavior and models natural user journeys.

### 5. Database Hooks with Tags

**Read references/hooks.md for complete patterns**

```typescript
// fixtures/database.fixture.ts
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use, testInfo) => {
            const hasResetTag = testInfo.tags.includes('@reset');
            const hasNoResetTag = testInfo.tags.includes('@no-reset');

            if (hasResetTag || isFirstScenarioOfFeature) {
                await truncateDatabase(); // Clean slate for feature/explicit reset
            }
            // @no-reset scenarios reuse data from previous

            await use();
            // NO cleanup - leave data for debugging
        },
        { scope: 'test', auto: true }
    ]
});
```

### 5. Reusable Step Definitions

**Read references/step-patterns.md for library of common steps**

Organize by domain and reusability:

```typescript
// steps/common/navigation.steps.ts
When('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path);
});

// steps/common/forms.steps.ts
When('I fill {string} with {string}', async ({ page }, label, value) => {
    await page.getByLabel(label).fill(value);
});
```

## Anti-Patterns to Avoid

### ❌ CRITICAL: Explicit Timeouts

```typescript
await page.waitForTimeout(5000); // FORBIDDEN!
```

**Use instead:** `waitFor()`, `waitForLoadState()`, retry patterns, or polling

### ❌ CSS/ID Selectors

```typescript
await page.locator('#submit-btn').click(); // Fragile!
await page.locator('.nav-menu > li:nth-child(2)').click(); // Breaks on refactor
```

**Use instead:** `getByRole()`, `getByText()`, `getByLabel()`

### ❌ Hardcoded IDs in Test Data

```typescript
// Bad - hardcoded ID
Given('product 123 exists', async () => {
    await db('products').insert({ id: 123, name: 'Test' });
});
```

**Use instead:** Dynamic factories with slugs or natural keys

### ❌ SQL Dumps for Fixtures

```typescript
await db.raw(fs.readFileSync('fixtures.sql', 'utf8')); // Static, inflexible
```

**Use instead:** Fishery factories with Faker for dynamic data

### ❌ Cleanup After Tests

```typescript
test.afterEach(async () => {
    await db.raw('TRUNCATE users'); // Loses debug data!
});
```

**Use instead:** Truncate in `beforeEach`, leave data after test

### ❌ Scenario-Level Parallelism (Anti-Pattern for Journeys)

Don't force every scenario to be independent when modeling user journeys:

```gherkin
# Bad - repetitive setup in every scenario
Scenario: View invoice
  Given I create an invoice with items
  When I view the invoice
  Then I should see line items

Scenario: Edit invoice
  Given I create an invoice with items  # Duplicate setup!
  When I edit the invoice
  Then I should see updated data
```

**Use instead:** Feature-level parallelism, sequential scenarios sharing state

## Tag System for Flow Control

### @reset

Forces database truncation before the scenario:

```gherkin
@reset
Scenario: Start new user flow
  When I create a user
  Then user should exist
```

### @no-reset

Explicitly reuses data from previous scenario:

```gherkin
@no-reset
Scenario: Verify user data
  Then the user from previous scenario should exist
```

### Default Behavior (no tags)

- **First scenario of feature**: Automatically resets database
- **Subsequent scenarios**: Reuse data from previous (sequential journey)
- **In CI mode**: Respects @no-reset to support sequential journeys, but defaults to reset if untagged (configurable)

## Configuration Example

```typescript
// playwright.config.ts
import { defineConfig } from '@playwright/test';
import { defineBddConfig } from 'playwright-bdd';

const testDir = defineBddConfig({
    paths: ['tests/features/**/*.feature'],
    require: ['tests/steps/**/*.ts']
});

export default defineConfig({
    testDir,

    // Feature-level parallelism (industry standard)
    fullyParallel: true,
    workers: process.env.CI ? 4 : 2,

    use: {
        baseURL: 'http://localhost:3000',
        trace: 'on-first-retry'
    }
});
```

## Resources

### Essential Reading (Start Here)

- **references/bdd-philosophy.md** - 🎓 BDD principles from Behat/Mink perspective
- **references/tags-and-workflow.md** - 🏷️ **@reset/@no-reset tags** and development workflow (START HERE!)
- **references/hooks.md** - Production-ready database fixture with tag support
- **references/step-patterns.md** - Library of reusable steps + advanced waiting strategies
- **references/datafixtures.md** - Factory patterns, dynamic data, avoiding hardcoded IDs
- **references/best-practices.md** - Comprehensive guide with real-world examples

### Project Setup

- **scripts/init_project.sh** - Initialize new test project structure
- **assets/typescript/** - Base TypeScript configuration templates

## Key Principles Summary

1. **Feature-Level Parallelism** - Features run in parallel (industry standard)
2. **Sequential Scenarios** - Scenarios within feature share state (user journeys)
3. **Tag System** - @reset/@no-reset for explicit flow control
4. **Gherkin Features** - Business-readable scenarios (Given/When/Then)
5. **Text-Based Selectors** - getByRole, getByText, getByLabel (NOT CSS/IDs)
6. **NO Timeouts** - Use waitFor, conditions, retry patterns, or polling
7. **Truncate at Feature Start** - First scenario resets, preserve data after
8. **Atomic Steps** - One action/assertion per step
9. **Reusable Steps** - Organize by domain, build composable library
10. **CI Compatibility** - CI respects @no-reset tags to maintain sequential flow integrity
