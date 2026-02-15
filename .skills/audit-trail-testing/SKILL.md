---
name: audit-trail-testing
description: Patterns for verifying that critical system actions are correctly recorded in the audit trail.
---

# Audit Trail Testing (E2E)

## Overview

Asegurar que cada acción crítica (creación, edición, borrado) deja una huella digital para accountability y debugging.

## The Verification Pattern

Un test de auditoría debe verificar tres cosas: el Actor, la Acción y el Objeto.

```gherkin
Scenario: Audit creation of a sensitive resource
  Given I am logged in as "admin@example.com"
  When I create a new sensitive resource "Secure-01"
  Then the audit trail should contain a record for "creation" of "Secure-01" by "admin@example.com"
```

## Implementation Strategy

### 1. Wait for Processing

El log de auditoría a veces es asíncrono. Usa la utilidad `spin` para re-intentar la verificación hasta que el registro aparezca.

```typescript
// ✅ GOOD
await spin(async () => {
    const auditLogs = await fetchAuditLogsFromUI(page);
    expect(auditLogs).toContain('Resource Secure-01 created');
});
```

### 2. Verify Metadata

No te limites a ver el texto. Verifica que la fecha es reciente y el usuario es el correcto.

## Best Practices

- **No omitir el borrado**: El borrado es la acción más crítica de auditar. Asegúrate de que el log persiste incluso si el objeto ya no existe.
- **Detalle de cambios**: Si el sistema audita campos específicos, verifica que el log muestra "Changed Name from A to B".
- **Aislamiento**: Asegúrate de limpiar la base de datos de tests antes de verificar el audit trail para no confundir registros de ejecuciones anteriores.

## Checklist

- [ ] Se verifica la acción en el panel de administración o sección de actividad.
- [ ] El test comprueba el Actor (quién lo hizo).
- [ ] Se usa `spin` para evitar falsos negativos por latencia de escritura.
- [ ] Se verifican tanto acciones exitosas como intentos fallidos si la seguridad lo requiere.
