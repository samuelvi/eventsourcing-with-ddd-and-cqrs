# Derivation Quotes Refactor Execution Plan

Last updated: 2026-03-20

## Scope

Este documento baja a ejecucion los tres primeros pasos del refactor descrito en `docs/architecture/derivation-quotes-refactor-decision.md`.

Queda fuera de este documento el paso previo de tests de caracterizacion. La idea aqui es dejar claro que se implementa primero, en que orden y con que criterio de cierre.

## Principios de ejecucion

- mantener el comportamiento externo actual mientras se mueve la estructura interna
- no cambiar en este tramo la politica funcional de ranking, limite o seleccion
- mantener derivation como flujo async por Messenger
- no mezclar en el mismo paso cambio de modelo, cambio de API y cambio de persistencia si no es necesario
- introducir ids y tracking antes de tocar reglas de negocio o transiciones complejas

## Paso 1. Introducir `DerivationRun` y contexto de correlacion

### Objetivo

Hacer explicita una ejecucion concreta de derivation para un booking, y mover la propiedad de `correlationId` al flujo de aplicacion.

### Resultado esperado

- cada intento de derivation tiene un `derivationRunId`
- `correlationId` existe antes de llamar a n8n
- commands, eventos y callbacks pueden referirse al run concreto y no solo al booking
- el sistema queda preparado para soportar reintentos y varios runs por booking

### Cambios a implementar

1. Introducir un identificador explicito de run:
    - `DerivationRunId`
    - `DerivationRunContext` o equivalente
2. Extender `GenerateQuotesCommand` para transportar:
    - `bookingId`
    - `derivationRunId`
    - `correlationId`
3. Generar `correlationId` y `derivationRunId` en aplicacion antes de disparar derivation:
    - flujo de booking
    - entrada HTTP local que dispare derivation
    - entrada desde n8n cuando venga sin contexto suficiente
4. Registrar la apertura del run en tracking de proceso:
    - estado inicial del run
    - timestamps basicos
    - referencias a `bookingId`, `derivationRunId` y `correlationId`
5. Propagar el contexto a:
    - `GenerateQuotesHandler`
    - eventos de derivation en Mongo
    - creacion de quotes
    - llamada de arranque a n8n
    - callbacks de n8n

### Piezas a tocar

- `src/App/Application/Command/Quotes/GenerateQuotesCommand.php`
- `src/App/Application/Handler/SubmitBookingWizardHandler.php`
- `src/App/Integrations/N8n/Application/Service/N8nNotifier.php`
- `src/App/Integrations/N8n/Infrastructure/Http/Controller/N8nBookingReadyController.php`
- `src/App/Application/Handler/Quotes/GenerateQuotesHandler.php`
- `src/App/Application/Handler/Quotes/DerivationEventPublisher.php`
- DTOs y payloads de n8n relacionados con supplier process
- nuevo modelo tecnico para run de derivation

### Criterio de cierre

- no hay ninguna pieza de integracion que invente por su cuenta el `correlationId`
- un mismo booking puede identificarse con varios `derivationRunId`
- el tracking del run se puede consultar sin inferir el contexto escaneando eventos de booking
- el comportamiento funcional de seleccion y creacion de quotes no cambia

### Riesgos a vigilar

- introducir el run sin propagarlo a todos los payloads deja el sistema en estado mixto
- si `QuoteStatusProcessor` sigue resolviendo correlacion por booking, quedara deuda tecnica visible hasta el paso 4
- si el contrato con n8n cambia de forma destructiva, se rompe la compatibilidad durante la migracion

## Paso 2. Reducir `GenerateQuotesHandler` a un orquestador fino

### Objetivo

Sacar del handler la logica de negocio y de integracion para dejarlo como coordinador de etapas.

### Resultado esperado

- `GenerateQuotesHandler` solo orquesta el flujo
- cada fase tiene un servicio propio, reemplazable y testeable
- la semantica actual se conserva aunque la implementacion cambie de forma

### Cambios a implementar

1. Extraer carga de facts a un servicio explicito:
    - `BookingDerivationFactsProvider`
2. Extraer busqueda de candidatos:
    - `QuoteCandidateFinder`
3. Reemplazar el filtro actual por una politica con nombre:
    - `QuoteEligibilityPolicy`
    - por ahora puede delegar internamente en `DerivationRuleEngine`
4. Extraer ranking a una estrategia explicita:
    - `QuoteRankingStrategy`
    - mantener exactamente la ordenacion actual en esta fase
5. Extraer limite y seleccion:
    - `QuoteSelectionLimiter`
    - mantener exactamente el comportamiento actual en esta fase
6. Extraer persistencia de quotes:
    - `QuoteBatchCreator`
7. Extraer arranque del proceso de proveedor:
    - `SupplierProcessStarter`
8. Introducir un recorder del run:
    - `DerivationRunRecorder`
    - registra inicio, cortes tempranos y cierre tecnico del paso

### Forma objetivo del handler

1. abrir o cargar contexto del run
2. cargar facts
3. cargar candidatos
4. evaluar elegibilidad
5. ordenar y limitar
6. crear quotes
7. arrancar proceso de proveedor
8. registrar el estado final de la ejecucion

### Piezas a tocar

- `src/App/Application/Handler/Quotes/GenerateQuotesHandler.php`
- `src/App/Application/Handler/Quotes/DerivationContextBuilder.php`
- `src/App/Application/Handler/Quotes/BookingFactsProvider.php`
- `src/App/Application/Handler/Quotes/QuoteCandidatesFilter.php`
- `src/App/Infrastructure/Repository/Product/DbalProductReadRepository.php`
- nuevos servicios de aplicacion en el modulo de quotes o derivation

### Criterio de cierre

- `GenerateQuotesHandler` deja de contener ranking, limitacion, persistencia y notificacion HTTP en linea
- el comportamiento externo no cambia
- la ruta feliz y los finales tempranos quedan expresados por etapas nombradas
- el codigo deja clara la frontera entre orquestacion, politica y persistencia

### Riesgos a vigilar

- no cambiar accidentalmente la semantica actual de slice y deduplicacion
- no mezclar en esta fase la limpieza de SQL con el adelgazamiento del handler
- no introducir side effects ocultos en servicios que deberian ser puros

## Paso 3. Separar tracking tecnico de n8n del estado de negocio de quote

### Objetivo

Quitar del centro del modelo de quote la metadata tecnica de callbacks y dividir claramente registro tecnico y resultado funcional.

### Resultado esperado

- registrar una `callbackUrl` deja de ser una responsabilidad central de `QuoteEntity`
- el sistema diferencia entre:
    - registrar donde llegara un callback
    - procesar una respuesta real o un timeout
- el flujo de integracion queda preparado para conectarse luego con una maquina de estados explicita

### Cambios a implementar

1. Introducir un modelo tecnico de tracking, por ejemplo:
    - `SupplierProcessTracking`
    - `QuoteIntegrationTracking`
2. Mover `callbackUrl` y metadatos tecnicos a ese tracking:
    - run asociado
    - quote asociada
    - correlation asociado
    - timestamps tecnicos
3. Adaptar `QuoteStartedProcess` para que:
    - registre el arranque tecnico
    - persista la `callbackUrl` en tracking tecnico
    - no necesite escribir metadata tecnica en `QuoteEntity`
4. Dividir el endpoint actual en dos casos de uso:
    - registrar callback de supplier process
    - procesar respuesta o timeout real del supplier
5. Introducir un handler de callback real que resuelva:
    - `derivationRunId`
    - `quoteId`
    - tipo de resultado recibido
6. Registrar eventos tecnicos y de proceso sin cerrar aun la maquina de estados final

### Piezas a tocar

- `src/App/Integrations/N8n/Application/Service/SupplierProcess/QuoteStartedProcess.php`
- `src/App/Integrations/N8n/Application/Service/SupplierProcess/SupplierResponseProcessCallbackUrlRegistrar.php`
- `src/App/Integrations/N8n/Infrastructure/Http/Controller/N8nSupplierResponseProcessController.php`
- `src/App/Domain/Model/QuoteEntity.php`
- `src/App/Infrastructure/Repository/Quote/DoctrineQuoteWriteRepository.php`
- nuevo modelo y repositorio de tracking tecnico

### Criterio de cierre

- la integracion con n8n puede registrar callbacks sin depender del write model de quote
- el sistema puede distinguir callback registrado de supplier respondio o supplier timeout
- la quote deja de ser el contenedor principal de metadata de transporte
- el paso queda listo para conectar despues con commands de transicion explicitos

### Riesgos a vigilar

- eliminar `n8nCallbackUrl` demasiado pronto puede romper compatibilidad con el flujo actual de `quote_sent`
- si el tracking tecnico no queda ligado al `derivationRunId`, reaparece el mismo problema con varios intentos por booking
- si el endpoint actual se parte sin compatibilidad temporal, n8n puede quedar desincronizado

## Fuera de alcance de estos tres pasos

- cambiar la politica funcional de ranking
- mover toda la elegibilidad de negocio fuera del SQL
- sustituir el PATCH generico por commands de transicion
- decidir y eliminar aun la arquitectura alternativa `QuoteRequested` -> `QuoteProjection`
- reemplazar n8n

## Secuencia recomendada de trabajo

1. cerrar Paso 1 completo y propagar el contexto a todo el flujo
2. con el contexto estable, ejecutar Paso 2 sin tocar la semantica funcional
3. cuando el handler ya sea fino, ejecutar Paso 3 para separar integracion tecnica y preparar el paso de transiciones explicitas

## Entregable al terminar este tramo

Al acabar estos tres pasos, el flujo deberia seguir comportandose igual de cara a fuera, pero con estas mejoras estructurales:

- una ejecucion de derivation ya es una unidad explicita
- el handler principal deja de ser el workflow entero comprimido en una clase
- la integracion con n8n deja de contaminar el estado central de quote
- el sistema queda listo para abordar despues maquina de estados, idempotencia fina y limpieza de arquitectura duplicada
