---
name: clean-code
description: Clean Code principles from Robert C. Martin and Refactoring patterns from Martin Fowler applied to TypeScript/JavaScript and Test Engineering. Use when writing or reviewing code for readability, maintainability, and structure.
---

# Clean Code for Test Engineering

Principios de Robert C. Martin (Clean Code) y Martin Fowler (Refactoring) adaptados a TypeScript y automatización de pruebas.

## Newspaper Metaphor (Stepdown Rule)

El código debe leerse como un artículo de periódico: de arriba a abajo, de lo general a lo específico. Este principio es vital en los Step Definitions.

### Regla en clases (orden de métodos)

En una clase, si un método `A` llama a otro método `B` de la misma clase, `B` debe implementarse **después** de `A`.
Así mantenemos lectura descendente: primero la intención, luego el detalle.

```typescript
class UserService {
    updateUserProfile(input: UpdateUserInput): void {
        this.validateInput(input);
        // ...
    }

    private validateInput(input: UpdateUserInput): void {
        // ...
    }
}
```

### Orden de un módulo de pasos

```typescript
// 1. Imports
import { expect } from '@playwright/test';
import { Given, When, Then } from '../bdd';

// 2. Public Step Definitions (Lo que el negocio lee)
When('I perform a main action', async ({ page }) => {
    await helperFunction(page);
});

// 3. Private Helpers (Detalles técnicos ocultos al negocio)
async function helperFunction(page) {
    await page.getByRole('button').click();
}
```

## Early Returns

Evitar anidación profunda retornando temprano. Muy útil en fixtures de configuración.

```typescript
// ✅ BIEN
export const test = base.extend({
    dbReset: [
        async ({ request }, use, testInfo) => {
            if (testInfo.tags.includes('@no-reset')) {
                await use();
                return;
            }
            // logic for reset...
            await use();
        }
    ]
});
```

## Single Responsibility in Steps

Cada paso hace una sola cosa. No mezcles "Setup" con "Acción" o "Aserción".

```typescript
// ❌ MAL - hace demasiado
Given('I am logged in and create a record', async ({ page }) => {
    await login(page);
    await createRecord(page);
});

// ✅ BIEN - pasos atómicos
Given('I am logged in', async ({ page }) => {
    await login(page);
});

When('I create a record', async ({ page }) => {
    await createRecord(page);
});
```

## Nombres descriptivos

En tests, el nombre de las variables debe reflejar el rol en el escenario.

```typescript
// ❌ MAL
const p = await productFactory.create();

// ✅ BIEN
const featuredProduct = await productFactory.create({ isFeatured: true });
```

## Evitar comentarios obvios

```typescript
// ❌ MAL - el código ya lo dice
// Click the submit button
await page.getByRole('button', { name: 'Submit' }).click();

// ✅ BIEN - comentar el "por qué" técnico o de negocio
// Wait 6s because the backend auto-save trigger is debounced at 5s
await page.waitForTimeout(6000);
```

## Replace Magic Numbers/Strings

Usa constantes para valores con significado especial en el dominio.

```typescript
// ✅ BIEN
const MIN_PASSWORD_LENGTH = 8;
const DEBOUNCE_WAIT_MS = 6000;

await page.fill('input[type="password"]', 'a'.repeat(MIN_PASSWORD_LENGTH));
```

## Checklist

### Clean Code en Tests

- [ ] Steps atómicos (una acción o una verificación).
- [ ] Helpers técnicos ocultos al final del archivo.
- [ ] Sin lógica de negocio compleja dentro de los Step Definitions.
- [ ] Variables que describen el rol del dato en el test.
- [ ] Uso de Early Returns en fixtures y helpers complejos.
- [ ] Cero comentarios que repitan lo que hace el locator.
