---
name: dangerous-defaults
description: Avoid dangerous default values in test data generation and method parameters to ensure explicit test scenarios and security.
---

# Dangerous Defaults in Testing

## Problem

Usar valores por defecto para parámetros sensibles (IDs de usuario, roles, permisos) en fábricas de datos o helpers crea asunciones ocultas que pueden llevar a:

- **Falsos positivos**: El test pasa porque usa el "Usuario 1" por defecto, que casualmente tiene los permisos necesarios.
- **Gaps de seguridad**: No se prueba explícitamente qué pasa cuando el ID es diferente o nulo.
- **Tests frágiles**: Cambiar el default en un sitio rompe 50 tests que dependían de él sin saberlo.

## The Antipattern in Factories

```typescript
// ❌ DANGEROUS: Always assumes an admin user if not specified
export const userFactory = Factory.define<User>(() => ({
    email: faker.internet.email(),
    role: 'ADMIN' // 🚨 Dangerous default!
}));

// The test forgets to specify the role, accidentally testing as Admin
Given('a regular user exists', async () => {
    await userFactory.create(); // Result: Admin user created
});
```

## The Solution

**Define el mínimo común denominador como default, o hazlo requerido.**

```typescript
// ✅ SAFE: Minimal permissions by default
export const userFactory = Factory.define<User>(() => ({
    email: faker.internet.email(),
    role: 'GUEST' // Safe default
}));

// Use explicit variants
export const adminUserFactory = userFactory.extend({
    role: 'ADMIN'
});
```

## Parameters That Should NEVER Have Defaults in Tests

| Parameter              | Why                                                     |
| ---------------------- | ------------------------------------------------------- |
| `userId` / `ownerId`   | Ownership must be explicit to avoid data leakage tests. |
| `role` / `permissions` | Tests must explicitly state the authority level.        |
| `tenantId` / `orgId`   | Multi-tenant tests must never assume a context.         |

## Explicit Data Principle

En E2E, es mejor ser redundante que ambiguo. Si un test verifica una restricción, el dato que causa la restricción debe ser visible en el escenario.

```gherkin
# ✅ GOOD: Explicit data
Scenario: Cannot edit others records
  Given a user "Owner" exists
  And a record belongs to "Owner"
  When I log in as "Stranger"
  Then I should not be able to edit the record
```

## Checklist

- [ ] Las fábricas de datos crean el objeto con el menor privilegio posible por defecto.
- [ ] Los tests de permisos especifican explícitamente el rol del usuario.
- [ ] No hay IDs hardcodeados (como `userId = 1`) en los Step Definitions.
- [ ] Los helpers de creación de datos requieren parámetros de identidad en lugar de asumirlos.
