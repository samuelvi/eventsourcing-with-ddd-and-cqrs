# Event Sourcing & CQRS: Technical Reference Guide

Esta guía detalla la implementación de la arquitectura de Event Sourcing y CQRS aplicada en este proyecto, cubriendo desde los fundamentos hasta la estrategia de persistencia híbrida.

---

## ◈ El Concepto: Estado vs. Historia
En una aplicación tradicional (CRUD), el estado actual es el único registro. En Event Sourcing, el estado es una función del tiempo.
*   **Hechos Inmutables**: Cada cambio de estado se registra como un evento atómico e inalterable.
*   **Auditoría Nativa**: La trazabilidad no es un añadido, es el núcleo del sistema.
*   **Flexibilidad Evolutiva**: Permite proyectar nuevos modelos de datos a partir de eventos históricos sin pérdida de información.

---

## ◈ Arquitectura de Persistencia Híbrida

Hemos optado por un enfoque Multi-DB para maximizar la especialización de cada motor:

| Capa | Motor | Propósito |
| :--- | :--- | :--- |
| **Write Side (Event Store)** | **MongoDB** | Alta disponibilidad de escritura, documentos JSON nativos y flexibilidad de esquema para los payloads de eventos. |
| **Read Side (Projections)** | **PostgreSQL** | Integridad referencial, consultas SQL complejas y optimización para el consumo de la interfaz de usuario. |
| **Technical State** | **MongoDB** | Almacenamiento de *Checkpoints* y *Snapshots* para aislar el estado técnico del negocio. |

---

## ◈ Implementación del Flujo de Datos

### ➔ Fase 1: Captura de la Verdad (Write Side)
El sistema garantiza que el evento se persista antes de cualquier efecto secundario. La identidad es generada por el cliente (UUID v7) para asegurar idempotencia total.

```php
// Ejemplo de persistencia de hecho inmutable
$storedEvent = new StoredEvent(
    $aggregateId,
    BookingWizardCompleted::class,
    $payload
);
$this->mongoStore->saveEvent($storedEvent);
```

### ➔ Fase 2: Proyecciones Asíncronas (Read Side)
Los proyectores reaccionan a los eventos para actualizar los modelos de lectura. Cada proyector es independiente, permitiendo fallos aislados sin corromper la verdad absoluta.

```php
// Ejemplo de proyección idempotente
$exists = $this->readRepository->findById($event->id);
if (!$exists) {
    $this->writeRepository->save(new BookingEntity(...));
}
$this->checkpoint->update($event->id);
```

---

## ◈ Gestión de Fallos y Resiliencia (Fault Tolerance)

### ⚠ Desincronización de Proyecciones
Al no ser una transacción única, una proyección puede quedar atrás respecto al Event Store. El sistema utiliza **Checkpoints** para monitorizar este desfase en tiempo real.

### ⚡ Reconstrucción (Self-Healing)
La recuperación ante desastres o la corrupción de los modelos de lectura se resuelve mediante el proceso de **Rebuild**:
1.  Truncado de las tablas de lectura (Postgres).
2.  Reset de los Checkpoints técnicos.
3.  Replay secuencial de la historia desde MongoDB hacia los buses de eventos.

---

## ◈ Optimización: Snapshots
Para evitar que el Replay de miles de eventos penalice el tiempo de recuperación, el sistema genera **Snapshots** automáticos cada N eventos. Esto permite inicializar el estado desde una "foto" reciente y solo procesar los eventos posteriores.

---

## ◈ Conclusión Técnica
Esta arquitectura sacrifica la simplicidad inicial por una robustez empresarial superior, permitiendo escalar las lecturas de forma independiente y garantizando que el historial de negocio sea el activo más valioso y protegido del sistema.