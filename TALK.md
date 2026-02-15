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

1.  **Identidad Determinística**: El sistema ya no pregunta a PostgreSQL "¿existe este usuario?". En su lugar, utiliza un **UUID v5** generado a partir del email del cliente. Esto garantiza que el mismo email siempre resulte en el mismo ID de usuario, asegurando la unicidad sin consultar bases de datos externas.
2.  **Rehidratación (Reconstitution)**: El repositorio de Event Sourcing recupera todos los eventos pasados de ese Agregado desde **MongoDB** y reconstruye su estado actual en memoria. Esto permite validar reglas de negocio basadas en el historial real.
3.  **Persistencia del Hecho**: El Agregado registra nuevos eventos (como `BookingWizardCompleted`) y el repositorio los vuelca al Event Store. MongoDB asegura mediante un índice único (`aggregateId` + `version`) que no haya colisiones, implementando un control de concurrencia optimista.
4.  **Desacoplamiento Total**: El lado de escritura es ahora 100% independiente del lado de lectura. No hay inyecciones de repositorios SQL en los Handlers.

### Fase 3: La Proyección y la Consistencia Eventual (Read Side)

El sistema utiliza proyecciones especializadas para transformar hechos en vistas útiles:

1.  **Consolidación de Identidad**: `UserProjection` es el único responsable de la tabla de usuarios. Escucha tanto el registro directo de usuarios como los pedidos (`BookingWizardCompleted`). Esto asegura que el usuario exista en SQL antes de que cualquier otra proyección intente usar su clave foránea.

2.  **Polimorfismo en Lectura (Generalista vs Especialista)**:
    - `ProductProjection` (Generalista): Gestiona el catálogo comercial (nombres, precios, monedas).

    - `MenuProjection` (Especialista): Gestiona el detalle técnico del producto (platos, descripción).

    - Esta separación permite que un solo evento de escritura alimente múltiples tablas optimizadas, permitiendo que el sistema escale a nuevos tipos de productos (ej. "Cooking Classes") sin modificar la estructura comercial base.

3.  **Idempotencia en SQL**: El proyector verifica si el registro ya existe antes de insertarlo, permitiendo que el sistema sea **re-ejecutable** sin generar duplicados.

4.  **Checkpoint**: Una vez guardado, se actualiza el "marcador" en MongoDB para saber por dónde va cada proyector.

### ¿Cómo se gestiona la reconstrucción del historial?

En esta arquitectura pura, la reconstrucción ocurre en dos lugares con propósitos distintos:

1.  **Rehidratación en Caliente (Write Side)**: Ocurre cada vez que un Agregado es invocado. Se leen sus eventos específicos para tomar decisiones de negocio consistentes al 100% (ej. `AggregateRoot::reconstituteFromHistory`).
2.  **Replay Global (Read Side)**: Se lleva a cabo en `ArchitectureControlService::rebuild()`. Recupera el flujo completo de todos los eventos del sistema para reconstruir las tablas de consulta desde cero en caso de desastre o cambio de esquema.

### Optimización: Snapshots y Estado Puro

Reconstruir un estado a partir de miles de eventos puede ser costoso.

1.  **Snapshots**: El sistema puede capturar una "foto" del estado rehidratado. En lugar de leer 1000 eventos, carga el último Snapshot y aplica solo los eventos posteriores.
2.  **Pureza de Dominio**: Los Agregados (`User`, `Product`, `Booking`) son ahora objetos de PHP puros. Todo el código de la aplicación se ha centralizado en `src/App/`, aislando por completo la lógica de negocio de la infraestructura de soporte y testing. Esto facilita enormemente el testeo unitario y asegura que la lógica de negocio esté blindada contra cambios tecnológicos.

---

## 3. Resiliencia y Consistencia Eventual

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

### Evolución: Del Pragmatismo a la Pureza

Este proyecto comenzó con un enfoque pragmático para ser didáctico, pero ha evolucionado hacia un modelo **Purista** por las siguientes razones:

#### 1. Eliminación del "Leak" de Lectura

- **Antes**: El Handler consultaba PostgreSQL para validar usuarios. Esto creaba una dependencia peligrosa: si PostgreSQL fallaba, la escritura fallaba.
- **Ahora (Puro)**: Usamos **Identidades Determinísticas** (UUID v5). El Handler sabe qué ID le toca a un email por pura matemática. La escritura es ahora autónoma y resiliente.

#### 2. Agregados como Fuente de Verdad

- **Antes**: Los eventos se guardaban casi como logs de una operación SQL.
- **Ahora (Puro)**: El Agregado es el que manda. Solo si el Agregado acepta el cambio y genera el evento, la operación es válida. Se ha implementado el patrón **State-Event Application**, donde el estado interno del objeto se deriva exclusivamente de sus eventos.

#### 3. Control de Concurrencia

- **Mecánica**: Cada evento tiene una versión secuencial. Si dos procesos intentan guardar la versión 5 del mismo Agregado, el motor de persistencia bloquea el segundo intento. Esto garantiza integridad total en entornos distribuidos sin necesidad de bloqueos de base de datos globales y pesados.

---

## 4. Decisiones de Ingeniería y Casos de Borde

### Idempotencia en Proyecciones

Es posible que un proyector reciba el mismo evento más de una vez debido a reintentos de red. El sistema está diseñado para manejar esto: si el proyector intenta crear un registro que ya existe en PostgreSQL, simplemente lo ignora y continúa. Esto hace que el proceso de lectura sea seguro y repetible.

### ¿Dónde consultar la información?

Es vital entender qué base de datos usar según el objetivo:

- **Para mostrar datos (Lectura)**: Se usa PostgreSQL. Es extremadamente rápido para realizar búsquedas y filtros complejos.
- **Para validar reglas (Negocio)**: Si la decisión depende de una validación crítica (ej. "¿tiene el usuario saldo suficiente?"), debemos consultar el estado que ofrece el Agregado o el Event Store, ya que es la única fuente que garantiza tener el dato exacto al milisegundo.

### Evolución y Flexibilidad

Event Sourcing permite responder a preguntas que no nos hicimos al principio. Si dentro de un año el negocio necesita saber "cuántos usuarios abandonaron el carrito en el paso 2", no necesitamos haber guardado ese dato en una tabla específica desde el primer día; simplemente creamos un proyector que analice los eventos pasados y genere esa estadística retrospectivamente.
