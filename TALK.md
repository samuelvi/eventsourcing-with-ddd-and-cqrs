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

A continuación, detallamos el ciclo de vida completo de la operación **"Generate New Event"**, desde la interfaz de usuario hasta la persistencia física.

### 1. Intención del Usuario (Frontend)
El usuario hace clic en el botón. El frontend genera un ID único (UUID v4) y envía un payload JSON a la API.
*   **Seguridad**: El ID generado por el cliente garantiza la **idempotencia**. Si la red falla y el usuario reintenta, el servidor sabrá que es la misma petición.

### 2. Recepción y Bloqueo (Handler)
El `SubmitBookingWizardHandler` recibe el comando.
*   **Locking**: Se adquiere un bloqueo distribuido (`LockFactory`) sobre el ID para evitar condiciones de carrera.
*   **Validación**: Se consulta a `MongoStore` si el `aggregateId` ya existe. Si existe, se detiene el proceso (Idempotencia).

### 3. Generación del Hecho (Domain)
Se instancia el objeto de dominio `BookingWizardCompleted`. Este objeto es puro, inmutable y representa la "verdad".
*   **Persistencia**: El handler envuelve este evento en un `StoredEvent` y llama a `MongoStore::saveEvent()`.
*   **Commit**: En este milisegundo, el evento se escribe en **MongoDB**. El negocio está a salvo.

### 4. Efectos Secundarios (Projections)
Una vez asegurada la escritura en Mongo, el evento se despacha al bus asíncrono (`EventBus`).
*   **UserProjection**: Escucha el evento. Verifica en **PostgreSQL** si el usuario ya existe (`fetchOne`). Si no, lo crea (`INSERT`).
*   **BookingProjection**: Verifica si la reserva existe. Si no, la inserta.
*   **Checkpoint**: Cada proyector actualiza su "marcapáginas" en la colección `checkpoints` de Mongo, indicando qué evento acaba de procesar.

### 5. Gestión de Fallos (Failure Handling)
*   **Si falla Mongo**: La transacción se aborta antes de emitir nada. El usuario recibe un error 500 limpio.
*   **Si falla Postgres**: El evento *ya está guardado* en Mongo. El sistema es consistente, pero la vista está desactualizada. El mecanismo de **Replay** recuperará la sincronización automáticamente cuando el servicio vuelva a estar online.

---

## ■ ¿Por qué usarlo? (Beneficios Estructurales)

*   **Auditoría Nativa**: No necesitas logs de auditoría separados. La propia base de datos *es* la auditoría.
*   **Análisis Temporal**: Permite viajar en el tiempo y reconstruir el estado pasado.
*   **Flexibilidad de Negocio**: Puedes proyectar la historia pasada en nuevos modelos de datos sin perder información.

---

## ■ Implementación Híbrida

| Componente | Tecnología | Rol |
| :--- | :--- | :--- |
| **Event Store** | **MongoDB** | Fuente de verdad inmutable (JSON). |
| **Read Models** | **PostgreSQL** | Proyecciones optimizadas para consultas (SQL). |

### El Rol de CQRS
Utilizamos el patrón **CQRS** como facilitador. Separamos el modelo de escritura (MongoDB) del modelo de lectura (PostgreSQL) para que las consultas de la UI no impacten en la ingesta de eventos.

---

## ■ Resiliencia y Recuperación (Self-Healing)

### ◇ El Proceso de Replay
Si las tablas de lectura (Postgres) se corrompen, el sistema puede regenerarlas:
1.  Se vacían las tablas SQL.
2.  Se leen todos los eventos desde MongoDB.
3.  Se re-ejecuta la lógica de proyección en orden secuencial.

### ◇ Snapshots
Para optimizar el rendimiento, guardamos periódicamente una "foto" del estado actual en MongoDB (Snapshots), permitiendo una recuperación rápida sin re-procesar millones de eventos antiguos.
