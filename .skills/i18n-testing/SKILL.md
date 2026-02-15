---
name: i18n-testing
description: Patterns for testing localized applications and ensuring UI works across different languages.
---

# Internationalization (i18n) Testing

## Problem

Los tests E2E a menudo fallan al cambiar de idioma porque los selectores basados en texto (ej. `getByText('Guardar')`) dejan de funcionar.

## Solution: Testing across locales

### 1. Unified Step Patterns

Usa expresiones regulares o múltiples alternativas en tus locators para soportar los idiomas principales en un solo paso.

```typescript
// ✅ GOOD: Suporta ES y EN
When('I click the save button', async ({ page }) => {
    await page.getByRole('button', { name: /Save|Guardar/i }).click();
});
```

### 2. Explicit Locale Setup

Para tests específicos de una traducción, fuerza el locale al inicio del test (vía localStorage, cookies o prefijo de URL).

```typescript
// Navigation step
Given('I am on the login page in English', async ({ page }) => {
    await page.addInitScript(() => {
        localStorage.setItem('app_locale', 'en');
    });
    await page.goto('/login');
});
```

## Verify content direction (RTL/LTR)

Si la aplicación soporta idiomas como árabe o hebreo, verifica que la dirección del DOM cambia.

```typescript
Then('the page direction should be RTL', async ({ page }) => {
    const dir = await page.getAttribute('html', 'dir');
    expect(dir).toBe('rtl');
});
```

## Best Practices

- **No hardcodear textos**: Si el texto es dinámico, usa data-attributes para la estructura pero verifica el texto inyectado.
- **Fallbacks**: Asegúrate de que si falta una traducción, el sistema no muestra claves crudas (ej. `error.validation.required`) al usuario.
- **Monedas y Fechas**: Verifica que los formatos de moneda y fecha cambian según el locale.

## Checklist

- [ ] El test fuerza un locale conocido antes de empezar.
- [ ] Los locators comunes soportan al menos dos idiomas mediante Regex.
- [ ] Se verifica que las traducciones no se cortan visualmente (overflow) en idiomas con palabras largas.
- [ ] Se ha probado el sistema con un locale inexistente para verificar el fallback.
