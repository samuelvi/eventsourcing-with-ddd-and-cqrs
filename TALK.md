# Event Sourcing: La Arquitectura de la Verdad

Event Sourcing cambia la forma en la que almacenamos la información. En un sistema tradicional, la base de datos solo guarda el estado actual de las cosas (el resultado final). En Event Sourcing, la base de datos guarda la historia: una secuencia de eventos inmutables que describen cada cambio que ha ocurrido.

Para entenderlo con una analogía:

- **Sistema Tradicional**: Es como una pizarra donde borramos el valor viejo para escribir el nuevo. Solo vemos lo que hay escrito ahora.
- **Event Sourcing**: Es como un libro de contabilidad (ledger) donde cada línea es una entrada nueva que nunca se borra. El "saldo actual" es simplemente el resultado de sumar todas las líneas del libro.

---

## 1. Conceptos Fundamentales

### El Estado vs. El Hecho

La diferencia principal radica en qué consideramos "la verdad" en nuestro sistema:

- **El Estado (CRUD)**: Es el valor actual. Si un usuario cambia su email, ejecutamos un `UPDATE` y el email anterior desaparece. Hemos perdido la historia y el contexto.
- **El Hecho (Event Sourcing)**: Es el evento que ha ocurrido. Guardamos un registro que dice `UserEmailChanged`. Este hecho es inmutable: no se puede cambiar ni borrar porque ya sucedió en el pasado.

### El Agregado y la Proyección

Para que este sistema sea eficiente, dividimos las responsabilidades:

- **Agregado (Escritura)**: Es el encargado de tomar decisiones. Recibe una petición, valida que cumpla las reglas de negocio y, si todo es correcto, genera un nuevo evento. Su fuente de verdad es el Event Store (en este proyecto, MongoDB).
- **Proyección (Lectura)**: Es una vista de los datos optimizada para ser consultada. Escucha los eventos que genera el Agregado y actualiza tablas tradicionales (en este proyecto, PostgreSQL) para que la aplicación pueda mostrar la información rápidamente.

---

## 2. Recorrido Técnico: Anatomía de una Reserva

Para entender cómo funciona el sistema, seguiremos el rastro de una operación desde que el usuario pulsa el botón "Generate New Event" en la pantalla de monitorización hasta que el dato aparece en las tablas de consulta.

### Fase 1: Identidad en el Origen (Frontend)

La operación comienza en el navegador (`DemoFlow.tsx`). Antes de realizar la petición POST al servidor:

1.  **Generación de ID**: El cliente genera un UUID v7 para el pedido. Al nacer el ID en el cliente, el sistema adquiere la propiedad de **idempotencia total**: si la petición se repite por un fallo de red, el servidor sabrá que es el mismo pedido.
2.  **Petición**: Se envía un comando con el ID, datos del cliente aleatorios y el presupuesto.

### Fase 2: El Agregado y la Verdad (Write Side)

El comando llega a `SubmitBookingWizardHandler`, que actúa como el guardián de la integridad. Tras la refactorización purista, el flujo es el siguiente:

1.  **Identidad Determinística**: El sistema ya no pregunta a PostgreSQL "¿existe este usuario?". En su lugar, utiliza un **UUID v5** generado a partir del email del cliente. Esto garantiza que el mismo email siempre resulte en el mismo ID de usuario, permitiendo que diferentes proyecciones vinculen datos sin necesidad de consultas cruzadas entre bases de datos.
2.  **EAFP (Better to Ask Forgiveness than Permission)**: Los Handlers aplican este principio. En lugar de comprobar si un registro existe antes de crearlo (LBYL), intentamos la operación directamente. Si hay una colisión, la infraestructura lanza una `ConcurrencyException` que capturamos silenciosamente, garantizando la idempotencia con el mínimo de lecturas a la DB.
3.  **Rehidratación Optimizada**: El repositorio recupera el estado del Agregado desde **MongoDB**. Gracias a la implementación de **Snapshots por Agregado**, no siempre leemos todos los eventos. Si hay un snapshot reciente, lo cargamos y solo aplicamos los eventos posteriores (el "delta"), minimizando el uso de CPU e I/O.
4.  **Bloqueo Optimista (Control de Concurrencia)**: Se ha eliminado la necesidad de bloqueos pesimistas (`LockFactory`). Confiamos en un índice único en MongoDB (`aggregateId` + `version`). Si dos procesos intentan guardar la misma versión de un objeto, el motor de persistencia bloquea el segundo intento atómicamente.
5.  **Desacoplamiento Total**: El lado de escritura es ahora 100% independiente del lado de lectura. No hay inyecciones de repositorios SQL en los Handlers.

### Fase 3: La Proyección y la Consistencia Eventual (Read Side)

El sistema utiliza proyecciones especializadas para transformar hechos en vistas útiles:

1.  **Garantía de Integridad (Prioridades)**: En arquitecturas SQL con claves foráneas, el orden importa. `UserProjection` tiene asignada una **prioridad alta (10)** en el bus de Symfony, mientras que `BookingProjection` tiene la prioridad por defecto (0). Esto garantiza que, en un flujo síncrono, el Usuario siempre "nazca" en SQL antes de que la Reserva intente referenciarlo.

    **Nota sobre el Escalamiento Asíncrono:** Es fundamental entender que en un entorno de producción con colas de mensajes (donde cada proyección corre en un worker distinto), **las prioridades de Symfony ya no garantizan el orden**. El mensaje del Booking podría procesarse antes que el del Usuario. Para manejar esto, la industria utiliza las siguientes estrategias:
    - **Reintento con Re-encolado (Retry with Delay):** Si la `BookingProjection` falla porque el usuario no existe, se lanza una excepción que devuelve el mensaje a la cola con un pequeño retardo. Se espera que para el segundo o tercer intento, el usuario ya haya sido creado por su propio worker.
    - **Consolidación de Proyectores (Atomic Projection):** Crear un único listener que gestione ambas entidades en una única transacción SQL, asegurando el orden correcto de inserción.
    - **Look-ahead Logic:** La `BookingProjection` comprueba si el usuario existe y, si no, lo crea ella misma con los datos mínimos disponibles en el evento (gracias a que el ID es determinista).
    - **Relajación de Constraints (Eventual Consistency):** Eliminar las Foreign Keys físicas en las tablas de lectura, confiando en que la integridad ya se ha validado en el Write Model (MongoDB) y aceptando una inconsistencia temporal de milisegundos en la vista SQL.

2.  **Checkpointing**: Cada proyección guarda su propio "marcador" en MongoDB. Esto permite saber qué evento fue el último procesado con éxito, facilitando la recuperación tras un fallo y asegurando que ningún evento se procese dos veces.

3.  **Polimorfismo en Lectura (Generalista vs Especialista)**:
    - `ProductProjection` (Generalista): Gestiona el catálogo comercial (nombres, precios, monedas).
    - `MenuProjection` (Especialista): Gestiona el detalle técnico del producto (platos, descripción).
    - Esta separación permite que un solo evento de escritura alimente múltiples tablas optimizadas sin modificar la estructura comercial base.

---

## 3. Optimización Avanzada: El Ciclo de Vida del Snapshot

Reconstruir un estado a partir de miles de eventos puede ser costoso. Hemos implementado una estrategia profesional de **Snapshotting**:

1.  **Snapshots por Agregado**: Cada objeto de negocio (Booking, User, Product) decide cuándo necesita un snapshot (ej. cada 5 eventos en esta demo).
2.  **Persistencia Atómica**: El snapshot guarda el estado interno completo del objeto en una colección dedicada en MongoDB, indexada por ID y Versión.
3.  **Evolución hacia el Segundo Plano**: Aunque actualmente el snapshotting ocurre durante el `save()` para este POC, en sistemas de gran escala esta tarea se delega a procesos asíncronos o tareas programadas (**CRON**). Esto permite que el usuario reciba una respuesta instantánea mientras el sistema se optimiza en segundo plano.

---

## 4. Resiliencia y Consistencia Eventual

La separación entre la escritura y la lectura introduce la **Consistencia Eventual**: un breve periodo de tiempo (milisegundos) en el que el evento ya existe en MongoDB pero aún no se ha reflejado en PostgreSQL.

### Gestión de Fallos de Infraestructura

En la pantalla **Architecture Monitor**, podemos simular qué ocurre cuando el sistema de lectura falla:

1.  **Escenario**: Desactivamos los proyectores de datos.
2.  **Resultado**: Los usuarios pueden seguir creando pedidos normalmente porque MongoDB (la escritura) sigue funcionando. Sin embargo, los nuevos pedidos no aparecen en las listas de la web porque PostgreSQL (la lectura) no está recibiendo las actualizaciones.
3.  **Ventaja**: El negocio no se detiene. Los datos están a salvo y la "verdad" está guardada, aunque la visualización esté temporalmente retrasada.

### El Proceso de Replay (Recuperación)

Si la base de datos de lectura se pierde o queremos cambiar su estructura, podemos usar el historial de eventos:

1.  **Vaciado**: Se borran las tablas de PostgreSQL.
2.  **Re-procesamiento**: El sistema lee todos los eventos guardados en MongoDB desde el primer día.
3.  **Restauración**: Los proyectores vuelven a ejecutar cada evento en orden, reconstruyendo la base de datos de PostgreSQL exactamente como estaba, o con un nuevo formato si fuera necesario.

---

## 5. Decisiones de Ingeniería y Casos de Borde

### Idempotencia en Proyecciones

Es posible que un proyector reciba el mismo evento más de una vez debido a reintentos de red. El sistema está diseñado para manejar esto: si el proyector intenta crear un registro que ya existe en PostgreSQL, simplemente lo ignora (usando bloques try-catch o verificaciones de existencia) y continúa.

### ¿Dónde consultar la información?

Es vital entender qué base de datos usar según el objetivo:

- **Para mostrar datos (Lectura)**: Se usa PostgreSQL. Es extremadamente rápido para realizar búsquedas y filtros complejos.
- **Para validar reglas (Negocio)**: Debemos consultar el estado que ofrece el Agregado (rehidratado desde MongoDB), ya que es la única fuente que garantiza tener el dato exacto al milisegundo.
