---
name: validation-testing
description: E2E patterns for testing form validations and error mapping between backend and frontend.
---

# Validation Testing (E2E)

## Overview

Verificar que los errores de validación del backend se muestran correctamente al usuario en el frontend.

## The Pattern: Field-Level Validation

Un test robusto no solo verifica que la acción falló, sino que el error está asociado al campo correcto.

```gherkin
Scenario: Create object with invalid data
  When I fill in "Email" with "invalid-email"
  And I click the "Save" button
  Then I should see an error message containing "Invalid email address"
  And the "Email" field should be marked as invalid
```

## Testing Implementation

### 1. Wait for Response

Las validaciones suelen requerir un viaje al servidor. Asegúrate de esperar la respuesta antes de verificar el error.

```typescript
// ✅ GOOD
const responsePromise = page.waitForResponse((r) => r.status() === 422);
await page.click('button[type="submit"]');
await responsePromise;
```

### 2. Verify Mapping

Asegúrate de que el mensaje de error aparece cerca del input correspondiente.

```typescript
// Example step definition
Then('the {string} field should show error {string}', async ({ page }, field, message) => {
    const container = page.locator('div').filter({ has: page.getByLabel(field) });
    await expect(container.getByText(message)).toBeVisible();
});
```

## Best Practices

- **Atomicidad**: Prueba un fallo por escenario para evitar confusión si varios errores aparecen.
- **Agnosticismo de Texto**: Usa expresiones regulares para que el test no rompa con cambios menores en el copy.
- **Sync con Backend**: Si el backend cambia el nombre de un campo (property path), el test E2E debe fallar, confirmando que el mapeo frontend se rompió.

## Checklist

- [ ] El test espera explícitamente a que termine la petición de guardado.
- [ ] Se verifica el mensaje de error específico, no solo un "hubo un error" genérico.
- [ ] Se comprueba que el error está asociado visualmente al campo que lo causó.
- [ ] Se limpian los campos antes de re-intentar en el mismo escenario si fuera necesario.
