# Event Sourcing: The Architecture of Truth

Esta guía técnica detalla la implementación de la arquitectura de Event Sourcing aplicada en este proyecto, cubriendo desde los fundamentos hasta la ingeniería de alta disponibilidad y tipado estricto.

---

## ■ ¿Qué es Event Sourcing?

En los sistemas tradicionales (CRUD), la base de datos solo almacena el **estado actual**. Si un usuario cambia su dirección de "Calle A" a "Calle B", el valor antiguo se sobrescribe y se pierde.

**Event Sourcing** propone un paradigma donde:

> **El estado de la aplicación no se guarda; se deriva de una secuencia de hechos.**

Almacenamos **"todo lo que ha sucedido"** en un libro de historia inmutable (un _Ledger_). Solo añadimos nuevos hechos, nunca borramos el pasado.

---

## ■ La "Arquitectura de Tipos": PHPStan Nivel 9 🛡️

Una de las debilidades comunes de Event Sourcing es la "suciedad" de los datos al hidratar eventos desde el pasado. Hemos blindado el sistema mediante:

1.  **Tipado Estricto Extremo**: El backend opera bajo **PHPStan Nivel 9**, el máximo nivel de rigor posible. No se permiten tipos `mixed` implícitos ni castings inseguros.
2.  **TypeAssert Utility**: Hemos implementado una capa de aserción (`App\Domain\Shared\TypeAssert`) que valida cada dato extraído de MongoDB o DBAL antes de que entre en el Dominio.
    -   Si un dato en DB no cumple el contrato esperado, el sistema falla inmediatamente (_Fail-Fast_), evitando corrupciones de estado silenciosas.

---

## ■ Control de Concurrencia Optimista (Optimistic Locking) ⏱️

En sistemas de alta concurrencia, dos procesos podrían intentar emitir el evento "Versión 2" para el mismo Agregado simultáneamente.

**Nuestra Solución:**
Hemos configurado **MongoDB** con un índice único compuesto: `aggregateId` + `version`.
-   Si ocurre una colisión, MongoDB rechaza la escritura y nuestro sistema lanza una `ConcurrencyException`.
-   Esto garantiza que la historia sea lineal y coherente, sin bifurcaciones accidentales en el estado.

---

## ■ Frontend Moderno: TanStack Query ⚡

La interfaz de usuario ya no depende de intervalos manuales (`setInterval`) propensos a errores. Hemos migrado a **TanStack Query (React Query)**:

-   **Polling Inteligente**: Refresco automático de estadísticas y estados de infraestructura.
-   **Invalidación de Caché**: Al emitir un nuevo evento, las vistas de lectura se marcan automáticamente como "stale" y se sincronizan sin intervención manual.
-   **Robustez**: Gestión nativa de estados de carga, error y reintentos.

---

## ■ Anatomía de una Transacción (Core Flow)

### 1. Intención (Frontend)
- **Tecnología**: React + TypeScript Strict.
- **Identidad**: El `bookingId` nace en el cliente (UUID v7), permitiendo **idempotencia total** desde el origen.

### 2. Persistencia del Hecho (Write Side)
- **Tecnología**: Symfony 7.2 + MongoDB.
- **Acción**: El Handler bloquea el Agregado, valida la inexistencia previa y guarda el hecho inmutable en Mongo. Una vez en Mongo, el hecho es **Ley**.

### 3. Proyección (Read Side)
- **Tecnología**: PostgreSQL + Doctrine DBAL.
- **Acción**: Los Projectors escuchan los eventos y actualizan las tablas SQL optimizadas para la UI. Si PostgreSQL desaparece, se puede reconstruir al 100% desde MongoDB mediante un **Replay**.

---

## ■ Resiliencia y CI/CD

El proyecto está "blindado" por un pipeline de **GitHub Actions** que asegura que:
-   Ningún commit degrade el nivel 9 de PHPStan.
-   No se introduzcan deprecaciones (Target: Symfony 8).
-   Todos los tests unitarios y funcionales pasen.
-   El estilo de código (Prettier/ESLint) sea consistente.

Esta combinación de **Event Sourcing + Tipado Estricto + CI/CD** crea una plataforma preparada para el entorno empresarial más exigente.