# ADR-0003 — Dinero, Pagos y Fiscalidad

- Estado: Propuesto
- Fecha: 2026-07-30
- Decisores: Equipo de arquitectura

## Contexto

El sistema gestiona dinero en múltiples contextos: precios de productos, totales de pedidos, pagos con Stripe Connect, propinas, cierres de caja, Informes Z y registros fiscales antifraude. La especificación de Fase 14 introduce Stripe Connect por tenant con webhooks firmados. La Fase 17 introduce el módulo fiscal con hash chaining. AGENTS.md prohíbe el uso de `float` para importes, exige snapshots inmutables, un modelo de transacción de pago append-only separado del estado del pedido, y un registro fiscal append-only con cadena de hash.

El riesgo principal es la corrupción financiera: doble cobro, importes manipulados por el cliente, webhooks falsos o repetidos, mesas liberadas sin pago, registros fiscales alterados o reordenados.

## Decisión

Se adopta un modelo de dinero estricto con tres pilares:

### 1. Representación de dinero

- Todos los importes se almacenan como **enteros en céntimos** (`int` / `BIGINT`). Nunca `float`, nunca `decimal` con decimales.
- `price` en `products` se convierte a `price_cents` (`unsignedBigInteger`).
- `unit_price` en `order_items` se almacena como snapshot en `unit_price_cents`.
- `total_price` en `orders` se calcula y almacena como `total_price_cents`.
- `tip_cents` en pagos, `amount_cents` en transacciones.
- La conversión de euros a céntimos y viceversa se centraliza en un servicio `MoneyService` con métodos `fromEuros(int)` y `toEuros(int)`.

### 2. Snapshots inmutables

Cada operación que involucra dinero persiste snapshots inmutables:

- **OrderItem**: `unit_price_cents`, `name_snapshot` (JSON multi-idioma), `tax_rate_cents`, `allergens_snapshot` (JSON) en el momento exacto del pedido.
- **Order**: `total_price_cents` calculado en backend a partir de los snapshots de order_items.
- **FiscalRecord**: datos fiscales del restaurante en el momento de la factura (IVA, IRPF, tipo impositivo).
- **InventoryAdjustment**: coste y receta/escandallo aplicados en el momento del movimiento.

Los snapshots son inmutables: una vez creados, no se modifican. Las actualizaciones de precio en `products` solo afectan a nuevos pedidos.

### 3. Transacción de pago append-only

Se crea la tabla `payment_transactions` con estructura:

```
payment_transactions
- id (BIGINT)
- restaurant_id (BIGINT, indexed)
- order_id (BIGINT, nullable)
- provider (ENUM: 'stripe', 'manual')
- provider_payment_id (VARCHAR, nullable)
- webhook_event_id (VARCHAR, nullable)
- idempotency_key (VARCHAR, unique)
- amount_cents (BIGINT, unsigned)
- tip_cents (BIGINT, unsigned, default 0)
- currency (CHAR(3), default 'EUR')
- status (ENUM: 'pending', 'confirmed', 'failed', 'cancelled', 'refunded', 'partially_refunded')
- confirmed_at (TIMESTAMP, nullable)
- metadata_reference (TEXT, nullable)
- created_at, updated_at
```

Reglas:
- El cliente **nunca** decide el importe final, moneda, cuenta Stripe Connect ni estado de pago.
- Todo pago manual registra: `user_id`, `method`, `amount_cents`, `tip_cents`, `reason`.
- Una mesa solo se libera tras una transición de pago válida (`confirmed`) o una acción manual autorizada y auditada.
- El fallo de envío de email o ticket nunca deshace un cobro confirmado.

### 4. Stripe Connect por tenant

- Cada `restaurant` tiene su cuenta Stripe Connect (`stripe_account_id`, `stripe_connected_at`).
- Las intenciones de pago se crean en la cuenta del restaurante, no la cuenta principal.
- Los webhooks verifican: firma criptográfica (`Stripe-Signature`), timestamp (ventana de 5 minutos), y se deduplican por `event_id`.
- Eventos fuera de orden se gestionan con estado `pending` y reevaluación al recibir el evento faltante.

### 5. Módulo fiscal append-only

- Tabla `fiscal_records` con: `id`, `restaurant_id`, `order_id`, `sequence_number`, `payload_canonical` (JSON estable), `hash`, `previous_hash`, `issued_at`, `status`.
- Hash encadenado: `hash = SHA256(canonical_payload || previous_hash)`.
- La representación canónica del payload se genera con un serializador determinista (claves ordenadas, sin whitespace extra).
- Cualquier modificación, borrado o reordenación de registros se detecta verificando la cadena de hashes.
- **No se afirma cumplimiento de Veri*Factu, TicketBAI, SII ni ninguna normativa oficial**. El módulo es un registro interno antifraude hasta validación legal específica.
- Soporte para facturas rectificativas (nuevo registro con `type = 'credit_note'` que referencia el original).

## Alternativas consideradas

| Alternativa | Ventajas | Riesgos o motivos de descarte |
|---|---|---|
| `decimal(10,2)` en MySQL | Soporte nativo de decimales | Riesgo de redondeo en cálculos intermedios; los enteros en céntimos eliminan toda ambigüedad |
| Float/double | Simplicidad de código | **No aceptable**: imprecisión aritmética inherente a IEEE 754 |
| Estado de pago en `orders.status` | Simplicidad | No soporta pagos parciales, reembolsos, múltiples métodos ni conciliación |
| Hash en aplicación (no en BD) | Simplicidad | Si la BD se altera externamente, no hay forma de detectar la alteración sin la cadena en BD |

## Consecuencias

### Positivas
- Cero ambigüedad en cálculos financieros: enteros en céntimos.
- Historial inmutable: nunca se alteran precios, impuestos o importes históricos.
- Conciliación completa: cada cobro tiene un registro trazable independiente del pedido.
- Detección de alteración fiscal: la cadena de hash detecta cualquier modificación retroactiva.

### Costes y riesgos
- Migración de todas las columnas de precio a céntimos.
- Los tests deben verificar redondeo, importes manipulados, webhooks inválidos/repetidos.
- El hash chaining requiere un serializador canónico estable.
- La tabla `payment_transactions` añade complejidad al modelo de datos.

## Reglas de seguridad e integridad

- Nunca aceptar `amount`, `total`, `tip` o `currency` del cliente para determinar importes finales.
- Nunca usar `float` o `double` para importes en PHP ni en SQL.
- Nunca liberar una mesa sin una transición de pago válida o una excepción manual autorizada y auditada.
- Los webhooks de Stripe deben verificar firma, timestamp y deduplicarse por `event_id`.
- Los registros fiscales son append-only: sin API de edición/borrado.
- La cadena de hash fiscal detecta modificación, borrado o reordenación.
- No afirmar cumplimiento de normativa fiscal oficial sin validación legal.

## Estrategia de pruebas

- **Unitarias**: Verificar que `MoneyService::fromEuros(10.50)` devuelve `1050`. Verificar que `MoneyService::toEuros(1050)` devuelve `10.50`. Verificar el cálculo del hash canónico.
- **Integración**: Crear un pedido, verificar que el snapshot de precio se mantiene aunque el producto cambie de precio. Procesar un pago Stripe simulado y verificar la transacción.
- **Seguridad**: Enviar un webhook de Stripe con firma inválida, firma válida pero evento duplicado, importe manipulado en la petición de pago. Verificar 401/409/422 según corresponda.
- **Concurrencia**: Dos pagos concurrentes para la misma orden con la misma `idempotency_key`. Solo uno se confirma.
- **Fiscalidad**: Alterar un registro fiscal en un clon de pruebas y verificar que la cadena de hash se rompe. Verificar que un registro creditivo referencia correctamente al original.

## Impacto por fases

| Fases | Impacto |
|---|---|
| Fase 1 | Migraciones: `price` como `decimal` provisional (se migrará a `*_cents`). |
| Fase 7 | Snapshots de precio en order_items; cálculo de total en backend. |
| Fase 14 | `payment_transactions`, Stripe Connect, webhooks firmados, deduplicación. |
| Fase 15 | Costes de inventario en céntimos. |
| Fase 17 | `fiscal_records`, hash chaining, cash sessions, Informe Z. |
| Fase 18 | Formularios de admin/owner con validación de importes. |
