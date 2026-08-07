# ADR-0002 — Eventos, Outbox e Idempotencia

- Estado: Propuesto
- Fecha: 2026-07-30
- Decisores: Equipo de arquitectura

## Contexto

El sistema gestiona operaciones distribuidas que conectan transacciones de base de datos con efectos secundarios: WebSockets (Reverb), impresión ESC/POS, inventario, emails y pasarelas de pago (Stripe). La especificación de Fase 7 exige transacciones ACID con snapshots de precio, idempotencia y `DB::transaction()`. La Fase 5 requiere impresión asíncrona no bloqueante. La Fase 14 requiere webhooks de Stripe con verificación de firma y deduplicación. AGENTS.md exige el patrón outbox para cualquier conexión entre transacción y efectos secundarios.

El riesgo principal es la inconsistencia: si una transacción de negocio se confirma pero el evento secundario falla (o viceversa), se producen estados corruptos como pedidos pagados pero sin imprimir, stock descontado sin pedido, o mesas liberadas sin pago confirmado.

## Decisión

Se adopta el **patrón Transactional Outbox** como mecanismo principal para garantizar la entrega exacta-once de eventos, combinado con **claves de idempotencia** para operaciones repetibles.

### Transacciones ACID

Todas las operaciones críticas se ejecutan dentro de `DB::transaction()`:
- Creación/modificación de pedidos y order items (Fase 7).
- Pagos y conciliación (Fase 14).
- Reservas y asignación de mesas (Fase 16).
- Cierre de mesa y liberación (Fase 7).
- Movimientos de inventario (Fase 15).
- Cierre de caja e informe Z (Fase 17).
- Generación de registros fiscales (Fase 17).

Cuando haya riesgo de condiciones de carrera (reservas concurrentes, deducción de inventario por múltiples mesas, cierre simultáneo de mesa), se usa `lockForUpdate()` sobre las filas afectadas.

### Transactional Outbox

Cuando una operación de negocio genera un evento que debe consumirse de forma asíncrona (WebSockets, impresión, inventario, emails, Stripe):

1. La transacción de negocio y la inserción del evento de dominio en la tabla `outbox_events` se ejecutan dentro de la **misma** `DB::transaction()`.
2. La tabla `outbox_events` contiene: `id` (UUID), `event_type`, `aggregate_type`, `aggregate_id`, `restaurant_id`, `payload` (JSON), `correlation_id`, `idempotency_key`, `dispatched_at` (NULL hasta dispatch).
3. Un **outbox dispatcher** (job de cola de Laravel de alta prioridad) lee eventos no dispatchados por `dispatched_at IS NULL`, los emite (broadcast, queue, mail) y marca `dispatched_at`.
4. Cada consumidor deduplica por `idempotency_key` o `event_id`.

Los eventos de UI/WebSocket son **derivados** de los eventos de dominio, no la fuente de verdad.

### Idempotencia

Las operaciones que pueden repetirse por reintentos de red, reconexión offline o reenvío de webhooks usan claves de idempotencia:
- `orders.idempotency_key`: `unique(restaurant_id, idempotency_key)`.
- `order_items.idempotency_key`: `unique(idempotency_key)`.
- `outbox_events`: deduplicación por `event_id`.
- Webhooks Stripe: deduplicación por `event_id` en tabla `stripe_webhook_events`.

### Separación de eventos

- **Eventos de dominio**: Se generan dentro de la transacción y se persisten en `outbox_events`. Son la fuente de verdad.
- **Eventos de UI/WebSocket**: Se emiten como derivación de los eventos de dominio procesados por el outbox dispatcher.

### Reintentos y dead-letter

- Los jobs del outbox dispatcher usan la política de reintento de Laravel con backoff exponencial.
- Tras un número máximo de reintentos (configurable, default 5), el evento se mueve a una tabla `outbox_dead_letter` con el error y se registra en auditoría.
- Un comando de administrador permite reintentar manualmente eventos en dead-letter.

## Alternativas consideradas

| Alternativa | Ventajas | Riesgos o motivos de descarte |
|---|---|---|
| Laravel Events + Listeners directos (síncronos) | Simplicidad | Si el listener falla (ej. webhook Stripe caído), la transacción de negocio revierte; no es asíncrono |
| Colas de Laravel sin outbox | Asíncrono nativo | Si la transacción revierte, los jobs ya se encolaron; no hay garantía de que el evento refleje un estado confirmado |
| Event Sourcing completo | Trazabilidad total | Complejidad excesiva para el alcance actual; sobreingeniería |
| Outbox + polling por cron | Simple de implementar | Latencia mayor (depende del cron); el dispatcher de cola de Laravel con prioridad alta es suficiente para tiempo real |

## Consecuencias

### Positivas
- Garantía de que ningún efecto secundario se pierde si la transacción se confirma.
- Garantía de que ningún efecto secundario se ejecuta si la transacción revierte.
- Deduplicación automática por idempotencia_key en todos los puntos de entrada repetibles.
- Trazabilidad completa de eventos a través de `outbox_events` y `outbox_dead_letter`.

### Costes y riesgos
- Tabla adicional `outbox_events` que requiere mantenimiento y limpieza.
- El outbox dispatcher introduce una dependencia adicional en la cola de Laravel.
- Los tests deben simular fallos parciales del dispatcher (outbox no despachado, evento duplicado, dead-letter).

## Reglas de seguridad e integridad

- Nunca emitir un evento WebSocket, de impresión, inventario o email fuera de una transacción confirmada.
- Nunca descontar stock si la transacción de pedido revierte.
- Nunca liberar una mesa si el pago no se confirma.
- Cada consumidor debe deduplicar por `event_id` o `idempotency_key`.
- Los webhooks de Stripe deben verificar firma, timestamp y deduplicarse por `event_id`.
- Los eventos en `outbox_events` incluyen `restaurant_id` para garantizar el aislamiento.

## Estrategia de pruebas

- **Unitarias**: Verificar que la inserción en `outbox_events` ocurre dentro de la misma transacción que la operación de negocio. Verificar la deduplicación por `idempotency_key`.
- **Integración**: Simular un fallo del outbox dispatcher tras la inserción del evento pero antes del dispatch. Verificar que el evento se despacha al reintento sin duplicar la operación de negocio.
- **Seguridad**: Enviar un webhook de Stripe con firma inválida, con timestamp fuera de ventana, y un evento duplicado. Verificar que solo el válido se procesa una vez.
- **Concurrencia**: Dos pedidos concurrentes con la misma `idempotency_key` para el mismo restaurante. Solo uno debe persistir.
- **Regresión**: Verificar que la adición de nuevos efectos secundarios (ej. notificación push) pasa por el outbox.

## Impacto por fases

| Fases | Impacto |
|---|---|
| Fase 1 | Estructura base: migraciones de datos, sin eventos aún. |
| Fase 2 | Aislamiento de tenant en eventos. |
| Fase 4 | Primeros eventos WebSocket: `OrderItemCreated`, `OrderStateChanged`, `TableCleared`. |
| Fase 5 | Impresión ESC/POS asíncrona vía outbox. |
| Fase 7 | Pedidos transaccionales con snapshots e idempotencia; outbox obligatorio. |
| Fase 8 | Sincronización offline con UUID/idempotency_key. |
| Fase 14 | Webhooks Stripe con firma, deduplicación y conciliación. |
| Fase 15 | Descuento de inventario vía outbox con `lockForUpdate()`. |
| Fase 17 | Cierre de caja y registros fiscales via transacciones atómicas. |
