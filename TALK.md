# Event Sourcing: The Architecture of Truth

Esta guía técnica explora los fundamentos y la implementación de una arquitectura basada en eventos, utilizando este proyecto como referencia práctica.

---

## ■ ¿Qué es Event Sourcing?

En la mayoría de los sistemas tradicionales, la base de datos solo almacena el **estado actual**. Si un usuario cambia su dirección de "Calle A" a "Calle B", el valor antiguo se sobrescribe y se pierde para siempre.

**Event Sourcing** propone un paradigma diferente:
> **El estado de la aplicación no se guarda; se deriva.**

En lugar de almacenar "cómo están las cosas ahora", almacenamos **"todo lo que ha sucedido"** para llegar hasta aquí. La base de datos se convierte en un libro de historia inmutable (un *Ledger* contable) donde solo se permite añadir nuevas páginas, nunca borrar ni modificar las anteriores.

### Conceptos Fundamentales

1.  **El Evento (The Fact)**: Representa algo que *ya ha sucedido* en el dominio. Es inmutable y se nombra en pasado (ej: `BookingCompleted`).
2.  **Event Store**: La base de datos especializada (en este caso, MongoDB) que actúa como la única fuente de verdad (*Source of Truth*).
3.  **Proyección**: Una vista interpretada de la historia, optimizada para responder preguntas específicas (ej: una tabla SQL de usuarios).

---

## ■ ¿Por qué usarlo? (Beneficios Estructurales)

*   **Auditoría Nativa**: No necesitas logs de auditoría separados. La propia base de datos *es* la auditoría. Puedes saber exactamente qué pasó, cuándo y por qué.
*   **Análisis Temporal**: Permite viajar en el tiempo. Puedes reconstruir el estado del sistema tal y como estaba el "martes pasado a las 10:00".
*   **Flexibilidad de Negocio**: Si el negocio cambia y necesita nuevos informes, puedes proyectar la historia pasada en nuevos modelos de datos sin perder información.

---

## ■ Implementación Híbrida (Hybrid Persistence)

Este proyecto implementa Event Sourcing utilizando una estrategia de persistencia políglota para maximizar el rendimiento:

| Componente | Tecnología | Rol en Event Sourcing |
| :--- | :--- | :--- |
| **Event Store** | **MongoDB** | Almacena la secuencia de eventos como documentos JSON inmutables. Ideal por su flexibilidad de esquema. |
| **Read Models** | **PostgreSQL** | Almacena las proyecciones (el estado actual) para consultas rápidas y relacionales. |

### El Rol de CQRS
Aunque el foco es Event Sourcing, utilizamos el patrón **CQRS** (Command Query Responsibility Segregation) como facilitador natural. Separamos el modelo de escritura (MongoDB) del modelo de lectura (PostgreSQL) para que las consultas de la UI no impacten en la ingesta de eventos.

---

## ■ Ciclo de Vida del Dato

### → 1. Captura del Hecho
El sistema recibe una intención (Comando), la valida y genera un Evento. Este evento se persiste inmediatamente en MongoDB.
*   *Identidad*: Generada por el cliente (UUID v7) para garantizar unicidad global.
*   *Garantía*: Una vez el evento está en Mongo, el hecho es irrevocable.

### → 2. Proyección Asíncrona
Una vez guardado, el evento se publica. Los "Proyectores" escuchan estos eventos y actualizan las tablas de PostgreSQL.
*   *Idempotencia*: Cada proyector verifica si ya ha procesado ese evento para evitar duplicados.
*   *Aislamiento*: Si un proyector falla, el evento sigue seguro en Mongo. El sistema es eventualmente consistente.

---

## ■ Resiliencia y Recuperación (Self-Healing)

La mayor ventaja operativa de Event Sourcing es la capacidad de **reconstrucción**.

### ◇ El Proceso de Replay
Si las tablas de lectura (Postgres) se corrompen o se borran, el sistema puede regenerarlas desde cero:
1.  Se vacían las tablas SQL.
2.  Se leen todos los eventos desde el principio de los tiempos en MongoDB.
3.  Se re-ejecuta la lógica de proyección en orden secuencial.

Este mecanismo convierte a la base de datos relacional en una "caché desechable" de la verdad, reduciendo drásticamente el riesgo de pérdida de datos.

### ◇ Snapshots
Para optimizar el rendimiento en sistemas con millones de eventos, implementamos **Snapshots**. Periódicamente, guardamos una "foto" del estado actual en MongoDB. Al recuperar un objeto, cargamos la foto más reciente y solo aplicamos los eventos ocurridos desde entonces.