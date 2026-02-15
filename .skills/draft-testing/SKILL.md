---
name: draft-testing
description: E2E patterns for testing auto-save mechanisms and draft recovery flows.
---

# Draft & Auto-Save Testing

## Overview

Verificar que el trabajo del usuario no se pierde ante cierres inesperados o fallos de conexión.

## Key Scenarios

### 1. Auto-save Trigger

Verificar que después de escribir y esperar un tiempo, aparece un indicador de guardado.

```gherkin
Scenario: Work is automatically saved
  When I fill in "Content" with "Important work"
  And I wait for the auto-save indicator
  Then I should see "Draft saved"
```

### 2. Recovery after Page Reload

Verificar que si recargamos la página sin haber guardado explícitamente, los datos persisten.

```gherkin
Scenario: Recovery after reload
  When I fill in "Title" with "Draft Title"
  And I wait for auto-save
  And I reload the page
  Then the "Title" field should contain "Draft Title"
```

## Testing Implementation

### Handling Debounce

Muchos sistemas de auto-guardado usan "debounce" (esperar a que el usuario deje de escribir). Tu test debe esperar un tiempo superior al debounce.

```typescript
// Common step
When('I wait for potential auto-save', async ({ page }) => {
    // Wait 1 second more than the system debounce (e.g. 5s)
    await page.waitForTimeout(6000);
});
```

### Mocking Connection Failures

Para tests avanzados, usa `page.route` para simular que el guardado falla y verificar que el usuario recibe una alerta.

```typescript
// Simulate failure
await page.route('**/api/drafts', (route) => route.abort());
```

## Best Practices

- **Visual Feedback**: Verifica que el usuario sabe que se está guardando (spinners, alerts).
- **Cleanup**: Los borradores pueden ensuciar la base de datos. Asegúrate de que el proceso de "Guardado Final" elimina el borrador temporal.
- **Concurrencia**: Si el sistema soporta varios borradores, verifica que no se mezclan.

## Checklist

- [ ] Se ha verificado el tiempo de debounce del sistema.
- [ ] Se prueba el escenario de recarga de página (F5).
- [ ] Se prueba el escenario de navegación atrás/adelante.
- [ ] Se verifica la desaparición del indicador de borrador tras un guardado exitoso.
