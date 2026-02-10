# Event Sourcing: The Architecture of Truth

Esta guía técnica detalla la implementación de la arquitectura de Event Sourcing aplicada en este proyecto, cubriendo desde los fundamentos hasta la estrategia de persistencia híbrida y resiliencia.

---

## ■ ¿Qué es Event Sourcing?

En la mayoría de los sistemas tradicionales (CRUD), la base de datos solo almacena el **estado actual**. Si un usuario cambia su dirección de "Calle A" a "Calle B", el valor antiguo se sobrescribe y se pierde para siempre.

**Event Sourcing** propone un paradigma donde:

> **El estado de la aplicación no se guarda; se deriva de una secuencia de hechos.**

En lugar de almacenar "cómo están las cosas ahora", almacenamos **"todo lo que ha sucedido"** para llegar hasta aquí. La base de datos se convierte en un libro de historia inmutable (un _Ledger_ contable) donde solo se permite añadir nuevas entradas, nunca borrar ni modificar las existentes.

### Conceptos Clave

1.  **El Evento (The Fact)**: Representa algo que _ya ha sucedido_. Es inmutable y se nombra en pasado (ej: `BookingWizardCompleted`).
2.  **Event Store**: Almacén especializado (MongoDB) que actúa como la única fuente de verdad (_Source of Truth_).
3.  **Proyección**: Una vista del estado derivada de la historia, optimizada para lectura (ej: tablas en PostgreSQL).

---

## ■ Arquitectura de Persistencia Híbrida

Hemos implementado un enfoque Multi-DB para maximizar la especialización de cada motor:

| Capa                | Motor          | Rol en Event Sourcing                                                                |
| :------------------ | :------------- | :----------------------------------------------------------------------------------- |
| **Event Store**     | **MongoDB**    | Alta disponibilidad de escritura, documentos JSON nativos y flexibilidad de esquema. |
| **Read Models**     | **PostgreSQL** | Integridad referencial, consultas SQL complejas y optimización para la UI.           |
| **Technical State** | **MongoDB**    | Almacenamiento de _Checkpoints_ y _Snapshots_ técnicos.                              |

_Nota: Utilizamos el patrón **CQRS** como facilitador natural, separando la escritura (Mongo) de la lectura (Postgres)._

---

## ■ Anatomía de una Transacción (Core Flow)

Ciclo de vida completo de la operación **"Generate New Event"**, detallando la lógica y archivos involucrados:

### 1. Intención del Usuario (Frontend)

- **Archivo**: `assets/pages/DemoFlow.tsx`
- **Acción**: El usuario pulsa el botón. Se genera un `bookingId` (UUID v4) y se envía un `POST` a la API.
- **Idempotencia**: La identidad nace en el cliente, permitiendo reintentos seguros sin duplicar hechos.

### 2. Punto de Entrada (Controller)

- **Archivo**: `src/Infrastructure/Http/Controller/BookingWizardController.php`
- **Lógica**: Recibe el payload y despacha un comando interno (`SubmitBookingWizardCommand`).

### 3. El Corazón del Flujo (Handler)

- **Archivo**: `src/Application/Handler/SubmitBookingWizardHandler.php`
- **Pasos Críticos**:
    1.  **Locking**: Bloquea el ID para evitar procesamientos duplicados simultáneos.
    2.  **Validation**: Verifica en MongoDB si el evento ya existe.
    3.  **Persistence**: Crea un `StoredEvent` y lo guarda en **MongoDB**.

    ```php
    // Persistencia del Hecho (Punto de no retorno)
    $this->mongoStore->saveEvent($storedEvent);
    ```

    4.  **Dispatch**: Una vez guardado en Mongo, se despacha el evento de dominio al bus de mensajes.

### 4. Actualización de Vistas (Projections)

- **Archivos**: `src/Application/Projection/UserProjection.php` y `BookingProjection.php`
- **Lógica**:
    1.  Verifican en **PostgreSQL** si el registro ya existe (idempotencia en lectura).
    2.  Actualizan la tabla SQL (`INSERT`).
    3.  Actualizan el **Checkpoint** en MongoDB para marcar el progreso técnico.
    ```php
    // Registro de progreso técnico
    $checkpoint->update(Uuid::fromString($event->bookingId));
    $this->mongoStore->saveCheckpoint($checkpoint);
    ```

---

## ■ Gestión de Fallos y Resiliencia (Self-Healing)

### ◇ Fallo en la Escritura (MongoDB)

Si la base de datos de eventos falla, el Handler lanza una excepción y nada se guarda. El sistema mantiene la integridad total. El usuario recibe un error y puede reintentar.

### ◇ Fallo en la Proyección (PostgreSQL)

Si falla la base de datos relacional, el hecho ya es seguro en MongoDB. El sistema es eventualmente consistente.

- **Recuperación (Replay)**: Se vacían las tablas SQL y se vuelven a procesar todos los eventos desde MongoDB mediante el comando `app:projections:rebuild`.

---

## ■ Preparado para la Asincronía (Async-Ready)

Aunque en esta demo el procesamiento es síncrono para facilitar la visualización, la arquitectura está diseñada para escalar horizontalmente:

- Utilizamos **Symfony Messenger** como mediador.
- Cambiar a un modelo asíncrono (RabbitMQ, Redis) es una tarea de configuración, no de código.

---

## ■ Optimización: Snapshots

Para evitar que el Replay penalice el tiempo de recuperación, el sistema genera **Snapshots** automáticos cada N eventos en MongoDB, permitiendo inicializar el estado desde una "foto" reciente.
