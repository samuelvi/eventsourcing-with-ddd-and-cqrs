# Event Sourcing: The Architecture of Truth

Esta guía técnica explora los fundamentos y la implementación de una arquitectura basada en eventos, utilizando este proyecto como referencia práctica.

---

## ■ ¿Qué es Event Sourcing?

En la mayoría de los sistemas tradicionales, la base de datos solo almacena el **estado actual**. Si un usuario cambia su dirección de "Calle A" a "Calle B", el valor antiguo se sobrescribe y se pierde para siempre.

**Event Sourcing** propone un paradigma diferente:
> **El estado de la aplicación no se guarda; se deriva.**

En lugar de almacenar "cómo están las cosas ahora", almacenamos **"todo lo que ha sucedido"** para llegar hasta aquí. La base de datos se convierte en un libro de historia inmutable (un *Ledger* contable) donde solo se permite añadir nuevas páginas, nunca borrar ni modificar las anteriores.

---

## ■ Anatomía de una Transacción (Core Flow)

A continuación, detallamos el flujo técnico completo cuando se pulsa **"Generate New Event"**, indicando archivos y lógica clave.

### 1. Desencadenante (Frontend)
*   **Archivo**: `assets/pages/DemoFlow.tsx`
*   **Acción**: El usuario pulsa el botón. Se genera un `bookingId` mediante `uuidv4()` y se envía un `POST` a la API.
*   **Clave**: La identidad nace en el cliente, permitiendo reintentos seguros (idempotencia).

### 2. Punto de Entrada (Controller)
*   **Archivo**: `src/Infrastructure/Http/Controller/BookingWizardController.php`
*   **Método**: `__invoke`
*   **Lógica**: Recibe el payload y despacha un comando interno (`SubmitBookingWizardCommand`).

### 3. El Corazón del Flujo (Handler)
*   **Archivo**: `src/Application/Handler/SubmitBookingWizardHandler.php`
*   **Método**: `__invoke`
*   **Pasos Críticos**:
    1.  **Locking**: Bloquea el ID para evitar procesamientos duplicados simultáneos.
    2.  **Idempotency Check**: `mongoStore->findEventByAggregateId($id)` verifica si el hecho ya existe.
    3.  **Persistence**: Crea un `StoredEvent` y lo guarda en MongoDB.
    ```php
    // Persistencia del Hecho (La Verdad Inmutable)
    $this->mongoStore->saveEvent($storedEvent);
    ```
    4.  **Event Dispatch**: Una vez guardado en Mongo, se despacha el evento de dominio al bus asíncrono para las proyecciones.

### 4. Actualización de Vistas (Projections)
*   **Archivos**: `src/Application/Projection/UserProjection.php` y `BookingProjection.php`
*   **Método**: `__invoke`
*   **Lógica**:
    1.  Verifican en **PostgreSQL** si el registro ya existe (idempotencia en lectura).
    2.  Actualizan la tabla SQL (`INSERT`).
    3.  Actualizan el **Checkpoint** en MongoDB para marcar el progreso técnico.
    ```php
    // Registro de progreso técnico
    $checkpoint->update(Uuid::fromString($event->bookingId));
    $this->mongoStore->saveCheckpoint($checkpoint);
    ```

---

## ■ Gestión de Fallos (Resilience Strategy)

### ◇ Fallo en la Escritura (MongoDB)
Si la base de datos de eventos falla, el Handler lanza una excepción y la petición del usuario devuelve un error. **Nada se ha guardado.** El sistema mantiene la integridad total.

### ◇ Fallo en la Proyección (PostgreSQL)
Si falla la base de datos relacional:
1.  **El hecho ya es seguro**: MongoDB tiene guardado el evento.
2.  **Detección**: El monitor de la demo mostrará una desincronización (el Checkpoint se quedará atrás).
3.  **Recuperación**: No hace falta restaurar backups. Se utiliza el proceso de **Replay**:
    *   *Comando*: `app:projections:rebuild` (o botón "Repair").
    *   *Acción*: Lee la historia de MongoDB y vuelve a ejecutar las proyecciones sobre PostgreSQL.

---

## ■ ¿Por qué usarlo? (Beneficios Estructurales)

*   **Auditoría Nativa**: La propia base de datos *es* la auditoría total.
*   **Análisis Temporal**: Permite reconstruir el estado del sistema en cualquier punto del tiempo.
*   **Flexibilidad de Negocio**: Permite crear nuevos modelos de datos a partir de eventos históricos años después de que ocurrieran.

---

## ■ Implementación Híbrida

| Capa | Tecnología | Rol |
| :--- | :--- | :--- |
| **Event Store** | **MongoDB** | Fuente de verdad inmutable (JSON). |
| **Read Models** | **PostgreSQL** | Proyecciones optimizadas para consultas (SQL). |

---

## ■ Optimización: Snapshots
Para sistemas con millones de eventos, implementamos **Snapshots** en MongoDB. Guardamos una "foto" del estado cada $N$ eventos para que la reconstrucción no tenga que leer toda la historia desde el inicio, sino solo desde la última foto.