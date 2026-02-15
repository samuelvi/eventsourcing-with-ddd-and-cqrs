# Tags and Development Workflow

Complete guide to using tags for efficient BDD development with Playwright, following Behat/Mink industry standards.

## Tag System Overview

### @reset

**Purpose**: Force database truncation before this scenario

**When to use**:

- Starting a new independent flow within a feature
- After scenarios that modified too much state
- Testing error scenarios that corrupt data

```gherkin
Feature: Invoice Management

  Scenario: Create invoice
    When I create an invoice
    Then it should be saved

  @reset
  Scenario: Delete invoice (independent flow)
    Given I create a simple invoice
    When I delete it
    Then it should not exist
```

### @no-reset

**Purpose**: Explicitly reuse data from previous scenarios

**When to use**:

- Verification scenarios after a setup scenario
- Testing different views of the same data
- Performance optimization (avoid recreating data)

```gherkin
Feature: User Registration

  Scenario: User registers
    When I register with "john@example.com"
    Then I should see "Welcome"

  @no-reset
  Scenario: Verify user in database
    Then user "john@example.com" should exist in database
    And user should have role "customer"

  @no-reset
  Scenario: User can login
    When I login with "john@example.com"
    Then I should see "Dashboard"
```

### No Tag (Default Behavior)

**Feature-level auto-reset**:

- **First scenario**: Database automatically truncated
- **Subsequent scenarios**: Reuse data (sequential journey)
- **In CI**: Respects @no-reset to support sequential journeys, but defaults to reset if untagged (configurable)

```gherkin
Feature: Product Catalog

  # Automatically resets (first scenario)
  Scenario: Create product
    When I create product "Laptop"
    Then it should appear in catalog

  # Reuses data from previous (default)
  Scenario: View product details
    When I view product "Laptop"
    Then I should see product information

  # Still reusing data
  Scenario: Update product
    When I update product "Laptop" price to 999
    Then the price should be updated
```

## Development Workflow Patterns

### Pattern 1: Setup → Multiple Verifications

**Use case**: Complex setup, multiple independent checks

```gherkin
Feature: Invoice Calculations

  Scenario: Create invoice with multiple items
    When I create invoice with:
      | Product  | Quantity | Price |
      | Laptop   | 2        | 999   |
      | Mouse    | 5        | 25    |
      | Keyboard | 3        | 75    |
    Then the invoice should be saved

  @no-reset
  Scenario: Verify subtotal calculation
    Then the invoice subtotal should be "2348.00"

  @no-reset
  Scenario: Verify tax calculation
    Then the invoice tax should be "234.80"

  @no-reset
  Scenario: Verify total calculation
    Then the invoice total should be "2582.80"

  @no-reset
  Scenario: Verify line items display
    Then the invoice should have 3 line items
    And each line item should show correct subtotal
```

**Development workflow**:

```bash
# 1. Run setup once
npm run test -- --grep "Create invoice with multiple items"

# 2. Iterate on verifications (fast, no DB reset)
npm run test -- --grep "@no-reset"
npm run test -- --grep "@no-reset"  # Run again and again
```

### Pattern 2: User Journey Flow

**Use case**: Complete user flow from start to finish

```gherkin
Feature: E-commerce Purchase Flow

  Scenario: User browses catalog
    Given I am on the homepage
    When I navigate to "Products"
    Then I should see product listings

  # Continues journey (no tag = reuse data)
  Scenario: User adds items to cart
    When I add "Laptop" to cart
    And I add "Mouse" to cart
    Then my cart should have 2 items

  Scenario: User proceeds to checkout
    When I click "Checkout"
    And I fill in shipping details
    Then I should see order summary

  Scenario: User completes payment
    When I enter payment details
    And I click "Place Order"
    Then I should see "Order confirmed"

  Scenario: User receives confirmation email
    Then I should receive email with order details
```

### Pattern 3: Error Handling with Reset

**Use case**: Testing error states that leave bad data

```gherkin
Feature: Payment Processing

  Scenario: Successful payment
    When I process payment with valid card
    Then payment should succeed

  @reset
  Scenario: Invalid card error
    When I process payment with invalid card
    Then I should see "Card declined"
    And payment should fail

  @reset
  Scenario: Insufficient funds error
    When I process payment with insufficient funds
    Then I should see "Insufficient funds"
    And payment should fail
```

**Why @reset**: Error scenarios might create failed transactions that could interfere with subsequent tests.

### Pattern 4: Mixed Flows in Same Feature

**Use case**: Testing different features within same domain

```gherkin
Feature: User Management

  # Flow 1: Registration and profile
  Scenario: New user registration
    When I register as "john@example.com"
    Then I should see "Welcome"

  @no-reset
  Scenario: Update profile
    When I update my name to "John Smith"
    Then profile should be updated

  # Flow 2: Independent admin operations
  @reset
  Scenario: Admin creates user
    Given I am logged in as admin
    When I create user "jane@example.com"
    Then user should be created

  @no-reset
  Scenario: Admin can list users
    Then I should see 2 users in the list
```

## CI vs Development Mode

### Development Mode (Local)

**Characteristics**:

- Tags are respected (@reset/@no-reset)
- First scenario of feature resets
- Subsequent scenarios share data
- Fast iteration

**Configuration**:

```bash
# .env or environment
TEST_MODE=dev

# Or implicitly (not CI)
# CI env variable not set
```

**Usage**:

```bash
# Run full feature (first scenario resets)
npm run test -- invoices.feature

# Run only verification scenarios (fast)
npm run test -- --grep "@no-reset"

# Run headed for debugging
npm run test:headed -- invoices.feature
```

### CI Mode (GitHub Actions, GitLab, etc)

**Characteristics**:

- **Respects @no-reset** to maintain sequential flow integrity
- **Defaults to reset** for untagged scenarios to ensure isolation
- Tests run in parallel at Feature level

**Configuration**:

```bash
# Automatically set by CI platforms
CI=true

# Or explicitly
TEST_MODE=ci
```

**Result**: Features run in parallel, but scenarios within a feature respect the sequential flow defined by tags.

## Real-World Example

```gherkin
Feature: Invoice Management
  As an accountant
  I want to manage invoices
  So that I can track customer payments

  Background:
    Given I am logged in as "accountant@company.com"

  # FLOW 1: Create and verify invoice
  Scenario: Create invoice with line items
    When I navigate to "Invoices"
    And I click the "New Invoice" button
    And I create invoice for customer "Acme Corp" with:
      | Product  | Quantity | Unit Price |
      | Laptop   | 2        | 999.00     |
      | Mouse    | 5        | 25.00      |
    Then I should see "Invoice created successfully"
    And I should see invoice number

  @no-reset
  Scenario: Verify invoice calculations
    When I view the last created invoice
    Then the subtotal should be "2123.00"
    And the tax (10%) should be "212.30"
    And the total should be "2335.30"

  @no-reset
  Scenario: Invoice appears in list
    When I navigate to "Invoices"
    Then I should see invoice for "Acme Corp"
    And the total should show "2335.30"

  # FLOW 2: Update existing invoice
  @no-reset
  Scenario: Add item to invoice
    When I view the last created invoice
    And I click "Edit"
    And I add line item:
      | Product  | Quantity | Unit Price |
      | Keyboard | 3        | 75.00      |
    And I click "Save"
    Then I should see "Invoice updated"

  @no-reset
  Scenario: Updated invoice calculations are correct
    When I view the last created invoice
    Then the subtotal should be "2348.00"
    And the tax (10%) should be "234.80"
    And the total should be "2582.80"

  # FLOW 3: Independent deletion test
  @reset
  Scenario: Delete invoice
    When I navigate to "Invoices"
    And I click the "New Invoice" button
    And I create a simple invoice for "Test Corp"
    And I view the invoice
    And I click "Delete"
    And I confirm the deletion
    Then I should see "Invoice deleted"
    And the invoice should not appear in the list
```

## Best Practices

### ✅ DO

1. **Use @no-reset for verifications**

    ```gherkin
    Scenario: Setup data

    @no-reset
    Scenario: Verify aspect 1

    @no-reset
    Scenario: Verify aspect 2
    ```

2. **Use @reset for independent flows**

    ```gherkin
    Scenario: Happy path

    @reset
    Scenario: Error path
    ```

3. **Group related scenarios**

    ```gherkin
    # Registration flow
    Scenario: Register
    Scenario: Login
    Scenario: Update profile

    # Admin flow (separate)
    @reset
    Scenario: Admin creates user
    ```

4. **Document why you use tags**
    ```gherkin
    # Need fresh data for error scenario
    @reset
    Scenario: Handle duplicate email error
    ```

### ❌ DON'T

1. **Don't overuse @reset**

    ```gherkin
    # Bad - every scenario resets (slow, repetitive)
    @reset
    Scenario: Create user

    @reset
    Scenario: View user

    @reset
    Scenario: Update user
    ```

2. **Don't create tight coupling without tags**

    ```gherkin
    # Bad - relies on exact state from previous scenario
    Scenario: Create invoice #123

    Scenario: View invoice #123  # What if first fails?
    ```

3. **Don't mix flows without @reset**

    ```gherkin
    Scenario: User registration

    # Bad - should have @reset
    Scenario: Admin operations  # Uses wrong user context!
    ```

## Debugging Tips

### Run Single Scenario

```bash
# With full feature setup (first scenario runs)
npm run test -- --grep "Verify invoice calculations"

# Headed mode to see what's happening
npm run test:headed -- invoices.feature
```

### Check Database State

```bash
# Run scenario, then check DB (data persists!)
npm run test -- --grep "Create invoice"

# Now inspect database
mysql -u user -p test_db
> SELECT * FROM invoices;
```

### Force Independence (Test CI Behavior)

```bash
# Simulate CI mode locally
TEST_MODE=ci npm run test
```

## Summary

- **@reset**: Explicit database reset (new flow)
- **@no-reset**: Explicit reuse data (verification)
- **No tag**: Auto-reset first scenario, share data after
- **CI mode**: Respects tags (sequential integrity)
- **Dev mode**: Respects tags (fast iteration)

This approach balances:

- **Development speed**: Iterate quickly without recreating data
- **Test reliability**: CI respects sequential flows while isolating features
- **Readability**: User journeys flow naturally in features
- **Flexibility**: Explicit control with tags when needed
