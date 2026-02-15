---
name: secrets-management
description: Secure handling of test credentials and secrets in automated testing environments.
---

# Secrets Management for E2E Tests

## Golden Rule

**NEVER commit real credentials to version control.**

## Test Credentials Hierarchy

```
.env                 # Committed: Placeholders for E2E environment
.env.test            # Committed: Defaults for local test environment
.env.test.local      # NOT committed: Real secrets for local testing
```

## The Placeholder Pattern

Usa valores obvios que fallen si no se configuran correctamente.

```bash
# ✅ GOOD - Clear placeholders
TEST_ADMIN_EMAIL=admin@example.com
TEST_ADMIN_PASSWORD=REPLACE_ME_IN_LOCAL_ENV
CI_API_KEY=SET_AS_GITHUB_SECRET

# ❌ BAD - Real credentials committed
TEST_ADMIN_PASSWORD=MySecretPassword123
```

## Integration with CI/CD

En entornos de integración continua (CI), inyecta los secretos como variables de entorno desde el almacén seguro del proveedor (GitHub Secrets, GitLab Variables, etc.).

```yaml
# Example CI Config
steps:
    - name: Run E2E Tests
      env:
          TEST_ADMIN_PASSWORD: ${{ secrets.E2E_ADMIN_PASSWORD }}
      run: npm run test:e2e
```

## Handing Credentials in Step Definitions

Evita hardcodear contraseñas en los archivos `.feature` o `.steps.ts`. Úsalos siempre a través de constantes que lean de `process.env`.

```typescript
// ✅ GOOD: Read from constants
import { AUTH_CREDENTIALS } from './constants';

await page.fill('input[name="password"]', AUTH_CREDENTIALS.password);
```

## Checklist

- [ ] `.env.test.local` está en el `.gitignore`.
- [ ] No hay contraseñas reales en los archivos `.feature`.
- [ ] Los Step Definitions usan constantes o variables de entorno.
- [ ] Los secretos de CI están configurados en el panel de seguridad del repo.
- [ ] Se han rotado las credenciales si alguna vez se subieron al historial de Git.
