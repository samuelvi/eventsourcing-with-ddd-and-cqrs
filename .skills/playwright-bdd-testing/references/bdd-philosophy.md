# BDD Philosophy: From Behat/Mink to Playwright

Una guía pedagógica para aplicar principios BDD probados en Behat/Mink al ecosistema Playwright + TypeScript.

## Fundamentos BDD: La Perspectiva del Profesor

### El Propósito de BDD

BDD (Behavior-Driven Development) no es solo escribir tests en Gherkin. Es una metodología de comunicación que:

1. **Une el negocio con el código** - Los escenarios son especificaciones ejecutables
2. **Documenta comportamiento** - No implementación
3. **Guía el diseño** - Los tests se escriben ANTES del código
4. **Facilita mantenimiento** - Tests legibles = fáciles de actualizar

### Los Tres Amigos

En BDD tradicional (Behat), los escenarios nacen de conversaciones entre:

- **Business** (PO/Stakeholder) - Define QUÉ debe hacer el sistema
- **Developer** - Implementa CÓMO lo hace
- **Tester** - Valida que funcione correctamente

**En Playwright:** El mismo principio aplica. Los `.feature` files son el resultado de estas conversaciones.

## Principios Fundamentales de Behat Aplicados a Playwright

### 1. Gherkin Como Lenguaje Ubicuo

#### En Behat/Mink:

```gherkin
Feature: Compra de productos
  Como cliente registrado
  Quiero poder comprar productos
  Para recibir mis artículos en casa

  Scenario: Compra exitosa
    Given estoy logueado como "user@example.com"
    And hay un producto "Laptop" con precio "€999"
    When añado "Laptop" a mi carrito
    And proceso el pago con tarjeta válida
    Then debería ver "Pedido confirmado"
    And debería recibir un email de confirmación
```

#### En Playwright (mismo concepto):

```gherkin
Feature: Product Purchase
  As a registered customer
  I want to purchase products
  So that I can receive my items at home

  Scenario: Successful purchase
    Given I am logged in as "user@example.com"
    And a product "Laptop" exists with price "€999"
    When I add "Laptop" to my cart
    And I proceed to checkout with valid card
    Then I should see "Order confirmed"
    And I should receive a confirmation email
```

**Lección clave:** El lenguaje debe ser entendible por stakeholders no técnicos.

### 2. Steps Reutilizables: La Biblioteca de Contextos

#### Filosofía Behat:

En Behat organizamos steps en "Contexts":

- `FeatureContext` - Steps generales
- `WebContext` - Navegación, formularios (Mink)
- `DatabaseContext` - Datos de prueba
- `ApiContext` - Llamadas API

#### Equivalente en Playwright:

```
steps/
├── common/
│   ├── navigation.steps.ts    # Como WebContext
│   ├── forms.steps.ts
│   ├── assertions.steps.ts
├── fixtures/
│   └── database.fixture.ts     # Como DatabaseContext
└── domain/
    ├── auth.steps.ts           # Como UserContext
    └── products.steps.ts       # Como ProductContext
```

**Lección clave:** Organiza steps por dominio y reutilización, no por feature.

### 3. Mink Selectors → Playwright Locators

#### Mink (PHP) usaba selectores semánticos:

```php
// Mink - Buscar por texto visible
$page->clickLink('Login');
$page->fillField('Email', 'user@example.com');
$page->pressButton('Submit');

// Mink - Evitar CSS cuando sea posible
$page->find('css', '#submit-button'); // ❌ Desaconsejado
```

#### Playwright aplica la MISMA filosofía:

```typescript
// Playwright - Buscar por texto/rol visible
await page.getByRole('link', { name: 'Login' }).click();
await page.getByLabel('Email').fill('user@example.com');
await page.getByRole('button', { name: 'Submit' }).click();

// Playwright - Evitar CSS cuando sea posible
await page.locator('#submit-button').click(); // ❌ Desaconsejado
```

**Lección clave:** Si un humano no puede ver el selector, no lo uses en el step.

### 4. Fixtures vs Factories: La Evolución

#### Behat Antiguo (Anti-patrón):

```php
// ❌ SQL dumps - rígido, difícil de mantener
Given /^the following users exist:$/
  Database::loadSQLDump('users.sql');
```

#### Behat Moderno (con Factories):

```php
// ✅ Factories - flexible, dinámico
Given /^a user "([^"]*)" exists$/
  UserFactory::create(['email' => $email]);
```

#### Playwright (mismo patrón evolucionado):

```typescript
// ✅ Fishery factories - TypeScript-friendly
Given('a user {string} exists', async ({}, email: string) => {
    await userFactory.create({ email });
});

// ✅ Con extensibilidad
const adminUser = await adminUserFactory.create();
const inactiveUser = await inactiveUserFactory.create({ isActive: false });
```

**Lección clave:** Factories sobre dumps. Datos dinámicos sobre estáticos.

## Patrones Avanzados de Behat en Playwright

### 1. Hooks de Ciclo de Vida

#### Behat Hooks:

```php
/** @BeforeScenario */
public function beforeScenario() {
    $this->database->truncate();
    $this->database->migrate();
}

/** @AfterScenario */
public function afterScenario() {
    // NO limpiar - dejar datos para debug
}
```

#### Playwright Fixtures (equivalente):

```typescript
export const test = base.extend<{}, { dbReset: void }>({
    dbReset: [
        async ({}, use) => {
            // BeforeScenario
            await db.raw('TRUNCATE TABLE users CASCADE');
            await db.migrate.latest();

            await use();

            // AfterScenario - NO limpiar
        },
        { scope: 'test', auto: true }
    ]
});
```

**Lección clave:** Cada test empieza limpio, termina con datos para debugging.

### 2. Transformaciones de Parámetros

#### Behat Transformations:

```php
/**
 * @Transform /^(\d+)$/
 */
public function castStringToNumber($string) {
    return intval($string);
}

/**
 * @Transform /^(enabled|disabled)$/
 */
public function castToBoolean($string) {
    return $string === 'enabled';
}
```

#### Playwright Parameter Types:

```typescript
import { defineParameterType } from 'playwright-bdd';

defineParameterType({
    name: 'boolean',
    regexp: /enabled|disabled/,
    transformer: (s) => s === 'enabled'
});

defineParameterType({
    name: 'money',
    regexp: /\$(\d+(?:\.\d{2})?)/,
    transformer: (amount) => parseFloat(amount)
});

// Uso
Then('the product should be {boolean}', async ({ page }, isEnabled) => {
    // isEnabled es boolean, no string
});
```

**Lección clave:** Transforma parámetros para tipos seguros y reutilización.

### 3. Tagged Scenarios

#### Behat Tags:

```gherkin
@javascript @slow
Scenario: Upload large file
  Given I am on the upload page
  When I upload a 100MB file
  Then the upload should complete

@api @fast
Scenario: Create user via API
  Given I send a POST to "/users"
  Then the response code should be 201
```

#### Playwright Annotations (equivalente):

```gherkin
Feature: File Operations

  @slow @requires-ui
  Scenario: Upload large file
    Given I am on the upload page
    When I upload a 100MB file
    Then the upload should complete
```

```typescript
// playwright.config.ts
export default defineConfig({
    projects: [
        {
            name: 'slow-tests',
            testMatch: /.*@slow.*/,
            timeout: 120000
        },
        {
            name: 'fast-tests',
            testMatch: /.*@fast.*/,
            timeout: 30000
        }
    ]
});
```

**Lección clave:** Usa tags para organizar y filtrar tests.

## Anti-Patrones Comunes (Behat → Playwright)

### ❌ Anti-Patrón 1: Steps Demasiado Específicos

#### Malo (Behat y Playwright):

```gherkin
Given I click the button with id "submit-btn-123"
When I fill the input with class "email-input-primary"
```

#### Bueno:

```gherkin
Given I click the "Submit" button
When I fill "Email" with "user@example.com"
```

### ❌ Anti-Patrón 2: Steps Que Hacen Demasiado

#### Malo:

```typescript
Given('I am logged in with a full cart and proceed to checkout', async ({ page }) => {
    // Hace 3 cosas a la vez
    await login(page);
    await addItemsToCart(page);
    await goToCheckout(page);
});
```

#### Bueno:

```typescript
Given('I am logged in', async ({ page }) => {
    await login(page);
});

Given('I have items in my cart', async ({ page }) => {
    await addItemsToCart(page);
});

When('I proceed to checkout', async ({ page }) => {
    await goToCheckout(page);
});
```

**Lección clave:** Steps atómicos = reutilizables y componibles.

### ❌ Anti-Patrón 3: Esperas Explícitas (Sleep/Timeout)

#### Malo (ambos frameworks):

```php
// Behat/Mink
$this->getSession()->wait(5000); // ❌
```

```typescript
// Playwright
await page.waitForTimeout(5000); // ❌
```

#### Bueno:

```php
// Behat/Mink
$this->getSession()->wait(10000, "document.readyState === 'complete'");
```

```typescript
// Playwright
await page.waitForLoadState('networkidle'); // ✅
await page.getByText('Loading...').waitFor({ state: 'hidden' }); // ✅
```

**Lección clave:** Esperar condiciones, no tiempo arbitrario.

## Prácticas Específicas de Playwright (Mejoras sobre Behat)

### 1. Auto-Waiting (Ventaja de Playwright)

Playwright tiene auto-waiting que Mink no tenía:

```typescript
// Playwright espera automáticamente:
// - Elemento visible
// - Elemento habilitado
// - Elemento estable (no en movimiento)
await page.getByRole('button', { name: 'Submit' }).click();

// En Mink necesitábamos esperas manuales
```

### 2. Parallel Execution

Behat tiene paralelización limitada. Playwright es paralelo por defecto:

```typescript
// playwright.config.ts
export default defineConfig({
    fullyParallel: true,
    workers: process.env.CI ? 4 : undefined
});
```

### 3. Multiple Browsers

```typescript
// playwright.config.ts
export default defineConfig({
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } }
    ]
});
```

## Checklist del Profesor: ¿Es buen BDD?

✅ **Scenario legible para no-técnicos**
✅ **Steps reutilizables entre features**
✅ **Sin selectores CSS/IDs en features**
✅ **Sin timeouts explícitos**
✅ **Datos dinámicos (factories), no estáticos**
✅ **Tests independientes (cualquier orden)**
✅ **BD limpia antes, datos preservados después**
✅ **Steps atómicos (una acción/aserción)**
✅ **Lenguaje de negocio, no técnico**
✅ **Given (setup), When (acción), Then (verificación)**

## Ejemplo Completo: Behat vs Playwright

### Feature File (idéntico):

```gherkin
Feature: User Registration

  Scenario: Successful registration
    Given I am on the homepage
    When I click the "Sign Up" link
    And I fill in the following:
      | Field            | Value              |
      | Email            | user@example.com   |
      | Password         | SecurePass123!     |
      | Confirm Password | SecurePass123!     |
    And I check "I agree to terms"
    And I click the "Create Account" button
    Then I should see "Welcome! Your account has been created"
    And I should be on "/dashboard"
```

### Step Definitions:

#### Behat (PHP):

```php
/**
 * @Given I am on the homepage
 */
public function iAmOnTheHomepage() {
    $this->visitPath('/');
}

/**
 * @When I fill in the following:
 */
public function iFillInTheFollowing(TableNode $fields) {
    foreach ($fields->getHash() as $row) {
        $this->fillField($row['Field'], $row['Value']);
    }
}
```

#### Playwright (TypeScript):

```typescript
Given('I am on the homepage', async ({ page }) => {
    await page.goto('/');
});

When('I fill in the following:', async ({ page }, dataTable) => {
    for (const row of dataTable.hashes()) {
        await page.getByLabel(row.Field).fill(row.Value);
    }
});
```

**Nota:** Misma estructura, diferente sintaxis. Los principios son universales.

## Conclusión

BDD es una filosofía, no una herramienta. Ya sea que uses:

- **Behat + Mink** (PHP)
- **Playwright + BDD** (TypeScript)
- **Cucumber + Selenium** (Java)

Los principios son los mismos:

1. **Colaboración** entre negocio y desarrollo
2. **Lenguaje ubicuo** que todos entienden
3. **Especificaciones ejecutables** como documentación viva
4. **Tests mantenibles** que evolucionan con el producto

Playwright simplemente trae estos principios al ecosistema TypeScript con herramientas modernas y más robustas.
