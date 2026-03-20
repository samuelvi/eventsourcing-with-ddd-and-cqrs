# Decision de Refactor de Derivation y Quotes

Last updated: 2026-03-20

## Objetivo

Definir con claridad:

- que hay que cambiar
- que merece la pena mantener
- que decisiones funcionales siguen abiertas
- cual deberia ser el flujo objetivo para reemplazar el workflow actual de derivation, quotes y supplier response

Este documento se centra en:

- `src/App/Application/Handler/Quotes/GenerateQuotesHandler.php`
- `src/App/Application/Handler/Quotes/*`
- `src/App/Integrations/N8n/**/*`
- `src/App/Quotes/Infrastructure/ApiPlatform/State/QuoteStatusProcessor.php`
- repositorios y entidades de quotes
- routing de Messenger para derivation
- eventos de derivation

## Foto Actual

El flujo actual funciona, pero mezcla demasiadas responsabilidades entre orquestacion de negocio, persistencia, auditoria, integracion con n8n, callbacks y transiciones de estado de quotes.

El problema estructural mas importante es que no existe un concepto explicito de una ejecucion concreta de derivation para un booking. Hoy el flujo se apoya en `bookingId` y en un `correlationId` que nace dentro de la integracion con n8n. Eso complica reintentos, callbacks, trazabilidad y cierre del flujo.

## GenerateQuotesHandler

### Que hace hoy

`src/App/Application/Handler/Quotes/GenerateQuotesHandler.php` hace actualmente todo esto:

1. resuelve `correlationId`
2. carga los facts del booking
3. consulta productos candidatos
4. aplica reglas de derivation
5. ordena y limita candidatos
6. deduplica pares supplier-product
7. crea `QuoteEntity` directamente en Postgres
8. escribe eventos de derivation en Mongo
9. arranca el proceso de proveedores en n8n
10. resuelve finales tempranos del flujo

Eso es demasiado para un unico handler. En la practica, hoy es el verdadero motor del workflow, pero sin tener el workflow modelado de forma explicita.

### Como lo refactorizaria

Mantendria `GenerateQuotesHandler` como punto de entrada del mensaje, pero lo reduciria a un orquestador fino.

Forma objetivo:

1. `DerivationRunStarter`
    - crea o recupera un `DerivationRunId`
    - garantiza que existe contexto de correlacion antes de tocar integraciones
2. `BookingDerivationFactsProvider`
    - devuelve los facts necesarios del booking
3. `QuoteCandidateFinder`
    - devuelve candidatos brutos desde repositorios
4. `QuoteEligibilityPolicy`
    - evalua reglas de negocio y devuelve permitidos y rechazados con sus motivos
5. `QuoteRankingStrategy`
    - ordena candidatos permitidos segun una politica explicita
6. `QuoteSelectionLimiter`
    - aplica el limite y, si hace falta, reglas de diversidad
7. `QuoteBatchCreator`
    - persiste las quotes y devuelve los ids creados
8. `SupplierProcessStarter`
    - llama a n8n y registra el resultado tecnico del arranque
9. `DerivationRunRecorder`
    - registra eventos de proceso y auditoria del run completo

Con eso, el handler quedaria aproximadamente asi:

1. abrir run de derivation
2. cargar facts
3. cargar candidatos
4. evaluar elegibilidad
5. ordenar y limitar
6. crear quotes
7. arrancar proceso de proveedor
8. cerrar el run como completado o fallido

Este refactor mantiene reconocible el comportamiento externo, pero hace que cada fase sea reemplazable, testeable y mas facil de evolucionar.

## Cambiar

### 1. Introducir un `DerivationRun` de primer nivel

Que cambiar:

- anadir un concepto explicito como `DerivationRun`, `QuoteDerivationRun` o `DerivationExecution`
- darle su propio id, enlazado a `bookingId`
- propagar ese id por commands, eventos, quotes, llamadas a n8n y callbacks

Por que:

- un booking puede necesitar mas de un intento de derivation
- `bookingId` no basta para identificar una ejecucion concreta
- callbacks, reintentos y auditoria deben colgar de un run, no del booking en general
- hoy se intenta usar `correlationId` como sustituto, pero ese id nace en integracion, no en la orquestacion de negocio

### 2. Mover la creacion de `correlationId` dentro del flujo de aplicacion

Que cambiar:

- generar `correlationId` antes de llamar a n8n
- tratarlo como parte del contexto del caso de uso
- persistirlo en tracking de run y en todos los eventos de proceso

Por que:

- el flujo de negocio debe ser duenio de su correlacion
- las integraciones deben consumir la correlacion, no crearla
- ahora la trazabilidad depende de `N8nNotifier`

### 3. Reducir `GenerateQuotesHandler` a orquestacion

Que cambiar:

- extraer lookup de candidatos, filtrado, ranking, seleccion, creacion de quotes, arranque del proceso de proveedor y registro de eventos a servicios dedicados

Por que:

- el handler actual tiene demasiado alcance
- cualquier cambio en ranking o n8n hoy toca el corazon de creacion de quotes
- un orquestador fino es mucho mas sencillo de testear con escenarios

### 4. Eliminar la duplicidad entre filtros SQL y reglas de dominio

Que cambiar:

- dejar de codificar la elegibilidad de negocio tanto en SQL como en reglas de derivation
- dejar en repositorio solo prefiltros tecnicos
- mover las decisiones reales de allow/deny a un unico punto

Por que:

- hoy presupuesto y pais se filtran en `DbalProductReadRepository` y luego se vuelven a comprobar en reglas
- eso impide una configurabilidad real
- muchos candidatos rechazados ni siquiera llegan al motor de reglas y por tanto no dejan buena traza de auditoria

### 5. Hacer explicitas las politicas de ranking y limite

Que cambiar:

- sacar la ordenacion actual de `GenerateQuotesHandler`
- definir estrategias con nombre, por ejemplo `HighestPriceFirstRankingStrategy`
- mover el limite de quotes a configuracion o politica

Por que:

- el orden actual esta hardcodeado y parece accidental
- el codigo no explica cual es el objetivo de negocio del ranking
- `QUOTE_LIMIT = 4` deberia ser una decision funcional explicita, no una constante escondida

### 6. Registrar eventos de proceso mas ricos

Que cambiar:

- introducir eventos de run como:
    - `DerivationStarted`
    - `CandidatesLoaded`
    - `CandidatesRejectedByRule`
    - `CandidatesRanked`
    - `QuotesSelected`
    - `QuotesCreated`
    - `SupplierProcessRequested`
    - `SupplierProcessCallbackRegistered`
    - `SupplierResponded`
    - `SupplierTimedOut`
    - `DerivationCompleted`

Por que:

- el set actual de eventos es parcial e inconsistente
- no hay una buena auditoria del flujo de respuesta y timeout de proveedor
- `QuoteFlowFinsih` esta mal escrito y ademas tiene una semantica pobre

### 7. Separar estado de negocio de quote y metadata tecnica de n8n

Que cambiar:

- sacar `n8nCallbackUrl` de `QuoteEntity`, o al menos aislarlo en un modelo tecnico de tracking de integracion
- tratar el registro del callback como estado tecnico, no como estado central de quote

Por que:

- `QuoteEntity` mezcla estado de dominio con metadata de transporte
- registrar un callback y gestionar el ciclo de vida de una quote son cosas relacionadas, pero no la misma responsabilidad

### 8. Sustituir side effects del PATCH generico por casos de uso explicitos

Que cambiar:

- sacar logica de `QuoteStatusProcessor`
- preferir commands explicitos como:
    - `MarkQuoteAsSentCommand`
    - `ExpireQuoteCommand`
    - `DiscardQuoteCommand`
    - `RegisterSupplierResponseCommand`

Por que:

- un PATCH generico es un contrato demasiado debil para un workflow
- hoy el processor guarda eventos, escribe estado, resuelve correlacion, llama a n8n y publica en bus de una sola vez
- las transiciones de quote necesitan reglas e idempotencia, no solo campos mutables

### 9. Separar registro de callback y procesamiento del resultado del callback

Que cambiar:

- usar un endpoint y servicio para registrar `callbackUrl`
- usar otro endpoint y servicio para procesar respuesta o timeout real del proveedor

Por que:

- el actual `N8nSupplierResponseProcessController` recibe `supplierResponded` y `elapsedMinutes`, pero no los usa funcionalmente
- registrar adonde llegara un callback no es lo mismo que procesar una respuesta real del proveedor

### 10. Definir una maquina de estados de quote

Que cambiar:

- definir transiciones validas y sus side effects
- definir si son idempotentes
- definir si `expired`, `discarded` y `quote_sent` son estados intermedios o terminales

Por que:

- hoy el estado de quote es basicamente un string con side effects ad hoc
- el workflow no deberia depender de mutaciones libres del campo `status`

### 11. Elegir una sola arquitectura para la creacion de quotes

Que cambiar:

- decidir si la creacion de quotes sera:
    - persistencia directa mas eventos de auditoria, o
    - flujo event-first con proyeccion (`QuoteRequested` -> `QuoteProjection`)

Por que:

- el repositorio ahora mismo contiene ambas ideas
- `QuoteRequestedPublisher` y `QuoteProjection` sugieren una arquitectura alternativa que el flujo actual ya no usa
- mantener las dos a la vez genera ambiguedad y errores futuros

### 12. Definir idempotencia por etapa

Que cambiar:

- definir claves y comportamiento de replay para:
    - callback `booking-ready`
    - generacion de derivation
    - arranque de proceso de proveedor
    - registro de callback
    - callbacks de respuesta de proveedor
    - transiciones de estado de quote

Por que:

- existe algo de deduplicacion, pero no como politica de extremo a extremo
- los workflows integrados suelen reintentar mucho

## Mantener

### 1. Messenger como frontera async para derivation

Mantener:

- el manejo asincrono de generacion de quotes con Messenger
- un transporte separado como `derivations_events` si este flujo necesita throughput y retries aislados

Por que mantenerlo:

- la derivation debe seguir fuera del request principal de booking
- esa frontera de cola es util y encaja con la arquitectura actual

### 2. La idea de facts, candidates y reglas

Mantener:

- `BookingFactsProvider`
- `QuoteCandidate`
- `DerivationRuleEngine`

Por que mantenerlo:

- la separacion conceptual es buena
- el problema no es que existan estas abstracciones, sino donde se toman hoy las decisiones del workflow

### 3. Reglas registradas por tagging de servicios

Mantener:

- el mecanismo base de tagging de Symfony para reglas de derivation

Por que mantenerlo:

- es simple y extensible
- permite anadir orden, prioridades o configuracion mas adelante sin rehacer todo

### 4. Mongo como storage de proceso y auditoria si ese es su rol

Mantener:

- Mongo para trazabilidad y historial de derivation, si esa sigue siendo su responsabilidad

Por que mantenerlo:

- ya aporta valor para inspeccionar el flujo
- puede seguir siendo util aunque las quotes sigan viviendo en Postgres

### 5. n8n como integracion externa de proceso

Mantener:

- la integracion HTTP con n8n como participante externo del workflow

Por que mantenerlo:

- el problema no es n8n en si
- el problema es que hoy los detalles de integracion se meten demasiado dentro del flujo de dominio

## Decisiones a cerrar con negocio

### 1. Que dispara la derivation

- todo booking debe derivar siempre?
- debe existir re-disparo manual?
- puede haber mas de un run de derivation para el mismo booking?

### 2. Que optimiza el ranking

- precio mas alto
- mayor probabilidad de respuesta
- diversidad de proveedores
- proveedores preferentes
- margen
- restricciones comerciales o por pais

Sin esta respuesta, la logica actual de ordenacion no puede considerarse correcta.

### 3. Cuantas quotes hay que seleccionar

- siempre 4
- configurable por pais, canal, tipo de cliente, campania o experimento

### 4. Si un supplier puede aparecer mas de una vez

- debe haber una sola quote por supplier?
- basta con una sola por par supplier-product?
- hay que maximizar diversidad de suppliers?

### 5. Que significa exactamente una respuesta del supplier

- `quote_sent` significa que el supplier respondio con una quote?
- significa que nuestro sistema notifico algo a n8n?
- hay diferencia entre "respondio" y "envio"?

El naming actual sugiere semanticas mezcladas.

### 6. Cuando se considera completado un run de derivation

- cuando se crean las quotes
- cuando n8n acepta el arranque del proceso
- cuando responden todos los suppliers
- cuando responde el primero
- cuando expiran o hacen timeout todas las quotes pendientes

### 7. Semantica del timeout

- timeout por quote
- timeout por supplier
- timeout por run completo
- una respuesta tardia puede reabrir una quote expirada?

### 8. Alcance de configuracion de reglas

- solo reglas globales
- por pais
- por supplier
- por familia de producto
- por segmento de cliente
- por experimento activado con feature flags

## Flujo objetivo propuesto paso a paso

1. El alta de booking crea o arrastra un `correlationId` y abre un `DerivationRun`.
2. El flujo de booking emite o despacha una peticion de derivation con `bookingId`, `derivationRunId` y `correlationId`.
3. `GenerateQuotesHandler` arranca el run y registra `DerivationStarted`.
4. El handler carga facts del booking mediante `BookingDerivationFactsProvider`.
5. `QuoteCandidateFinder` carga el pool bruto de candidatos.
6. `QuoteEligibilityPolicy` evalua cada candidato y registra rechazos con motivos explicitos.
7. `QuoteRankingStrategy` ordena los candidatos permitidos.
8. `QuoteSelectionLimiter` aplica limite y politica de diversidad.
9. `QuoteBatchCreator` crea las quotes seleccionadas y devuelve sus ids.
10. `SupplierProcessStarter` llama a n8n para comenzar el seguimiento de proveedores.
11. Si n8n devuelve datos de callback, el sistema registra `SupplierProcessCallbackRegistered` en tracking tecnico.
12. Cuando n8n notifica una respuesta o timeout real, `SupplierProcessCallbackHandler` resuelve el run y la quote afectados.
13. `QuoteStatusTransitionService` aplica la transicion correspondiente, por ejemplo responded, expired o discarded.
14. El sistema registra `SupplierResponded` o `SupplierTimedOut`, mas la transicion aplicada a la quote.
15. Cuando el run alcanza su condicion de cierre, el sistema registra `DerivationCompleted` con el resultado final.

## Orden de migracion recomendado

1. Introducir `DerivationRunId` y contexto de correlacion sin cambiar el comportamiento externo.
2. Extraer servicios puros desde `GenerateQuotesHandler` manteniendo el comportamiento actual de ranking y seleccion.
3. Mover la elegibilidad de negocio fuera del SQL y hacia una politica explicita.
4. Ampliar eventos de proceso de derivation y reemplazar nombres debiles o mal escritos.
5. Separar registro tecnico de callback y manejo funcional de respuesta de proveedor.
6. Sustituir los side effects del PATCH generico por commands y logica de transicion explicita.
7. Eliminar la arquitectura de creacion de quotes que finalmente no se elija.

## No Objetivos de la Primera Fase

- reemplazar n8n
- redisenar todo el flujo de booking
- migrar todo el historico de eventos existente
- introducir un BPM engine completo

La primera fase deberia centrarse en hacer el workflow explicito, auditable y seguro de evolucionar, no en reescribir toda la aplicacion.
