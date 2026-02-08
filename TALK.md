# TALK: Event Sourcing & CQRS - The Architecture of Truth 🚀

Este documento es una guía estructurada para una charla técnica de **30 minutos**. Está diseñada para llevar a tus compañeros desde el concepto básico hasta una implementación profesional ("Enterprise Grade").

---

## 1. El Problema: La Amnesia del CRUD 🧠💨
En una aplicación tradicional (CRUD), si un usuario cambia su email de `a@b.com` a `c@d.com`, ejecutamos un `UPDATE` y el valor antiguo **desaparece para siempre**.
*   **Perdemos el contexto**: No sabemos por qué cambió, ni cuántas veces lo ha hecho.
*   **Estado Acumulado**: Solo conocemos el "ahora", pero el camino para llegar aquí se ha borrado.

---

## 2. ¿Qué es Event Sourcing? (La Definición TED) 📖
Event Sourcing es un patrón donde **el estado de la aplicación no se guarda; se calcula a partir de una secuencia inmutable de eventos.**

*   **¿Qué es un Evento?**: Un hecho inmutable que ya ocurrió (ej: `ReservaCompletada`, `PrecioCambiado`).
*   **¿Para qué se usa?**: Sistemas donde la auditoría, la trazabilidad y la recuperación ante desastres son críticas (Banca, E-commerce, Logística).
*   **¿Por qué se usa?**: Permite "viajar en el tiempo", crear nuevas vistas de datos años después y garantiza que nunca perdamos información de negocio.

---

## 3. El Mecanismo: Cómo funciona esta Demo 🛠️

Nuestra arquitectura se divide en dos mundos (**CQRS**):

### A. El Mundo de la Verdad (Write Side)
Cuando el usuario pulsa "Submit", el **Handler** hace tres cosas sagradas:
1.  **Bloqueo**: Asegura que nadie más procese ese ID a la vez.
2.  **Idempotencia**: Mira en el `Event Store` si ese UUID ya existe.
3.  **Persistencia**: Guarda el hecho en la tabla `event_store`.

```php
// src/Application/Handler/SubmitBookingWizardHandler.php
public function __invoke(SubmitBookingWizardCommand $command) {
    // 1. La Identidad viene del CLIENTE (UUID v7)
    $aggregateId = Uuid::fromString($command->id);

    // 2. Persistimos el HECHO (La Verdad Inmutable)
    $storedEvent = new StoredEvent($aggregateId, BookingWizardCompleted::class, $payload);
    $this->entityManager->persist($storedEvent);
    $this->entityManager->flush(); // <-- Aquí el negocio ya está a salvo.
}
```

### B. El Mundo de las Consecuencias (Read Side)
Una vez guardado el evento, los **Proyectores** se despiertan y actualizan las tablas que ve el usuario (`users`, `bookings`).

```php
// src/Application/Projection/UserProjection.php
public function __invoke(BookingWizardCompleted $event) {
    // Generamos la "consecuencia" en la tabla de lectura
    $user = UserEntity::create($event->clientName, $event->clientEmail);
    $this->userRepository->save($user);
    
    // GUARDAMOS EL MARCAPÁGINAS (Checkpoint)
    $checkpoint->update($event->id); 
}
```

---

## 4. El Gran Dilema: "Se ha roto una tabla y la otra no" 😱
En Event Sourcing, las proyecciones **no son una transacción única**. Puede que actualices `users` con éxito, pero falle la tabla `bookings`.

**¿Cómo sabemos que hay un error?**
Usamos **Checkpoints** (Puntos de Control). Cada proyector tiene su propio "marcapáginas" en una tabla técnica:

| Proyector | Último Evento Procesado (ID) | Estado |
| :--- | :--- | :--- |
| UserProjection | `...UUID-100` | ✅ Al día |
| BookingProjection | `...UUID-098` | ⚠️ 2 eventos atrás |

**¿Cómo se repara? (The Self-Healing)**
Como tenemos el "Libro de la Verdad" (`event_store`), la solución es trivial y automática:
1.  Identificamos el desfase.
2.  **Replay**: Volvemos a leer los eventos desde el punto de fallo y los re-inyectamos en el proyector que se quedó atrás.
3.  **Resultado**: El sistema se auto-cura sin intervención manual en la DB.

---

## 5. Estándares de la Industria: Análisis del Proyecto 🏆

Lo que hemos construido cumple los estándares **Enterprise-Grade**:

1.  **Event Store Inmutable**: Lista cronológica de hechos con `aggregate_id` y `version`.
2.  **CQRS Puro**: Separación total entre ORM (Escritura) y SQL/DBAL (Lectura rápida).
3.  **Client-Side Identity**: UUIDs generados en el frontend para evitar duplicados en reintentos.
4.  **Snapshots Automáticos**: Cada $N$ eventos hacemos una "foto" del estado para que la recuperación no tarde horas, sino milisegundos.

---

## 6. Pros y Contras de Event Sourcing ⚖️

| Ventajas (PROS) | Desventajas (CONS) |
| :--- | :--- |
| **Auditoría Total**: Sabes quién hizo qué y cuándo por diseño. | **Complejidad**: Requiere un cambio de mentalidad radical. |
| **Recuperación**: Puedes reconstruir el sistema desde cero. | **Evolución de Datos**: Cambiar un evento antiguo es difícil. |
| **Escalabilidad**: Las lecturas pueden estar en otra base de datos. | **Consistencia Eventual**: La UI puede tardar ms en actualizarse. |

---

## 7. Estrategia de Base de Datos: ¿Una o Dos? 🗄️

En esta demo hemos implementado una **Arquitectura Multi-Base de Datos** para maximizar la resiliencia:

| Componente | Base de Datos | Por qué? |
| :--- | :--- | :--- |
| **Event Store** | **MongoDB** | Documentos JSON nativos, alta escritura, sin esquema (flexibilidad). |
| **Checkpoints** | **MongoDB** | Aislamiento total del estado técnico respecto al negocio. |
| **Read Models** | **PostgreSQL** | Consultas relacionales complejas, integridad para la UI. |

**La ventaja del Failover**: Si la base de datos de lectura (Postgres) se corrompe o cae, el "corazón" del negocio (Mongo + Events) sigue latiendo. Podemos levantar una nueva instancia de Postgres y reconstruir todo en minutos.

---

## 8. El Toque Final (Demo Live) 🎬
1.  **Reset Lab**: Limpiar todo.
2.  **Simular Caída**: Apagar un proyector.
3.  **Generar Caos**: Crear eventos y ver cómo la tabla de lectura se queda vacía.
4.  **Reparar**: Pulsar el botón mágico y ver cómo el sistema reconstruye la realidad leyendo el pasado.

**Frase Final:** *"En Event Sourcing, el estado es efímero, pero la historia es la verdad absoluta."*
