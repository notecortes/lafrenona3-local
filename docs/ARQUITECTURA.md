# Arquitectura — lafrenona3

## Diagrama de componentes

```
                    ┌─────────────────────────────────┐
                    │         Frontend (Vue 3)         │
                    │  ┌───────────┬────────────────┐  │
                    │  │ MenuView  │KitchenMonitor  │  │
                    │  │ (cliente) │ (staff - parcial)│  │
                    │  └─────┬─────┴────────┬───────┘  │
                    │        │              │          │
                    │  ┌─────▼──────────────▼───────┐  │
                    │  │  Pinia Store + Axios API   │  │
                    │  └─────────────┬──────────────┘  │
                    └────────────────┼─────────────────┘
                                     │ HTTP / API
                    ┌────────────────▼─────────────────┐
                    │      Backend (Laravel 13/PHP 8.3) │
                    │  ┌────────────────────────────┐  │
                    │  │        API Controllers      │  │
                    │  │  Auth │ Client │ Owner │Staff│  │
                    │  └────────────┬───────────────┘  │
                    │               │                   │
                    │  ┌────────────▼───────────────┐  │
                    │  │      Service Layer          │  │
                    │  │ TenantContext │Reservation  │  │
                    │  │ InventoryStock │FiscalChain │  │
                    │  └────────────┬───────────────┘  │
                    │               │                   │
                    │  ┌────────────▼───────────────┐  │
                    │  │      Models + Scopes        │  │
                    │  │ BelongsToTenant (Global)    │  │
                    │  └────────────┬───────────────┘  │
                    │               │                   │
                    │  ┌────────────▼───────────────┐  │
                    │  │  Events │ Listeners │Jobs   │  │
                    │  └────────────┬───────────────┘  │
                    └────────────────┼─────────────────┘
                    ┌────────────────▼─────────────────┐
         ┌─────────┤  MySQL 8.0    │    Redis         │
         │          │  (lafrenona3  │  (sessions,      │
         │          │   _matrix)    │   cache, queue)   │
         │          └───────────────┴──────────────────┘
         │
         │  WebSocket (Reverb)
         │
    ┌────▼──────────────────────────┐
    │  Print Agent (Python 3)       │
    │  asyncio + websockets + ESC/POS│
    └───────────────────────────────┘
```

## Estructura de directorios

### Backend

```
backend/
├── app/
│   ├── Console/Commands/       # Comandos Artisan (3)
│   ├── Events/                 # Eventos domain (4)
│   │   ├── ClientAssistanceRequested.php
│   │   ├── OrderItemCreated.php
│   │   ├── OrderStateChanged.php
│   │   └── TableCleared.php
│   ├── Http/
│   │   ├── Controllers/Api/    # Controladores REST (18)
│   │   │   ├── AuthController.php
│   │   │   ├── Client/         # Clientes QR (5)
│   │   │   ├── Owner/          # Owner CRUD (7)
│   │   │   ├── Staff/          # Staff operaciones (7)
│   │   │   ├── SuperAdmin/     # Gestión tenants (1)
│   │   │   └── Webhooks/       # Stripe (1)
│   │   ├── Middleware/         # Middlewares (4)
│   │   │   ├── EnsureTenantContext.php
│   │   │   ├── CheckOwnerRestaurant.php
│   │   │   ├── CheckSubscription.php
│   │   │   └── EnsureSuperAdmin.php
│   │   ├── Requests/           # Form Requests (2 dirs)
│   │   └── Resources/          # API Resources (3 dirs)
│   ├── Listeners/              # Event listeners (1)
│   │   └── ProcessInventoryDeduction.php
│   ├── Mail/                   # Mails (DigitalInvoiceMail)
│   ├── Models/                 # Modelos Eloquent (15)
│   │   ├── Scopes/
│   │   │   └── TenantScope.php
│   │   └── Traits/
│   │       └── BelongsToTenant.php
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/               # Servicios de negocio (6)
│       ├── AuditLogger.php
│       ├── ErrorMonitoringService.php
│       ├── FiscalChainingService.php
│       ├── InventoryStockService.php
│       ├── ReservationEngine.php
│       └── TenantContext.php
├── bootstrap/
│   └── app.php                 # Configuración de routing, middleware
├── config/                     # Configuración Laravel (12 ficheros)
├── database/
│   ├── migrations/             # 25 migraciones
│   ├── factories/              # UserFactory
│   └── seeders/                # DatabaseSeeder
├── routes/
│   ├── api.php                 # Rutas API v1
│   ├── channels.php            # Canales WebSocket
│   ├── console.php             # Comandos Artisan
│   └── web.php                 # Rutas web
├── tests/
│   ├── Feature/                # 19 tests feature
│   └── Unit/                   # 1 test unit
└── composer.json               # PHP 8.3, Laravel 13
```

### Frontend

```
frontend/
├── src/
│   ├── router/index.js         # Vue Router (2 rutas client)
│   ├── stores/clientMenu.js    # Pinia store
│   ├── services/api.js         # Axios client
│   ├── views/
│   │   ├── client/MenuView.vue
│   │   └── staff/KitchenMonitor.vue
│   ├── __tests__/setup.js      # Vitest setup
│   ├── App.vue
│   ├── main.js
│   └── style.css               # Variables CSS globales
├── public/
│   └── manifest.webmanifest    # PWA manifest
├── index.html
├── vite.config.js
├── vitest.config.js
└── package.json                # Vue 3, Vite, Vitest
```

## Modelo multi-tenant

### Capas de aislamiento

1. **TenantContext (Servicio)**: Resuelve `restaurant_id` desde el usuario autenticado. Nunca acepta `restaurant_id` del cliente.

2. **Global Scope (TenantScope)**: Todos los modelos multi-tenant aplican `BelongsToTenant` que añade `TenantScope` automáticamente. Filtra por `restaurant_id` en todas las consultas.

3. **Middleware (EnsureTenantContext)**: Aplica el tenant al contexto antes de pasar al controlador. Excluye a `superadmin`.

4. **Policies / validación en controladores**: Cada controlador verifica que el usuario pertenece al restaurante del recurso.

5. **Restricciones SQL**: Índices `unique(restaurant_id, ...)`, foreign keys, `CHECK` constraints.

### Resolución de tenant

| Tipo de usuario | Resolución |
|---|---|
| `superadmin` | Sin tenant scope (acceso global) |
| `owner`/`staff` | `user.restaurant_id` |
| Cliente QR (público) | Desde `Table.secret_token` -> `table.restaurant_id` |

### Modelos con TenantScope

`Category`, `Product`, `Table`, `Order`, `OrderItem`, `Subscription`, `AuditLog`, `Ingredient`, `InventoryAdjustment`, `Reservation`, `CashSession`, `FiscalRecord`, `PaymentTransaction`, `OfflineOperation`, `TenantDesign`.

## Roles y permisos

| Rol | Rutas | Middlewares aplicados |
|---|---|---|
| `superadmin` | `/api/v1/superadmin/*` | `auth:sanctum`, `superadmin` |
| `owner` | `/api/v1/owner/*` | `auth:sanctum`, `tenant.context`, `check.owner.restaurant`, `check.subscription` |
| `staff` | `/api/v1/staff/*` | `auth:sanctum`, `tenant.context`, `check.owner.restaurant`, `check.subscription` |
| Cliente (público) | `/api/v1/client/*` | Ninguno (público, rate limited) |
| Webhook | `/api/v1/webhooks/stripe` | Ninguno (verifica firma Stripe) |

## Modelos de datos principales

```
User (id, name, email, password, role, restaurant_id)
  └── belongsTo -> Restaurant (owner_id)
  └── hasOne   -> Subscription (owner_id)
  └── hasMany  -> Restaurant (users)

Restaurant (id, owner_id, name, slug, status, weekend_mode)
  └── belongsTo -> User (owner)
  └── hasMany   -> User (users), Category, Product, Table, Order, Subscription

Category (id, restaurant_id, name [JSON], sort_order, is_active)
  └── belongsTo -> Restaurant
  └── hasMany   -> Product

Product (id, restaurant_id, category_id, name [JSON], description [JSON], price, weekend_price, allergens [JSON], is_active, is_vegan, is_vegetarian, stock_status)
  └── belongsTo -> Restaurant, Category
  └── hasMany   -> OrderItem
  └── belongsToMany -> Ingredient (product_ingredient)

Table (id, restaurant_id, number, status, secret_token, session_token, assistance_status, capacity)
  └── belongsTo -> Restaurant
  └── hasMany   -> Order

Order (id, restaurant_id, table_id, session_token, status, total_price, idempotency_key)
  └── belongsTo -> Restaurant, Table
  └── hasMany   -> OrderItem

OrderItem (id, restaurant_id, order_id, product_id, quantity, unit_price, status, target_area, idempotency_key, price_snapshot, tax_rate, discount_amount)
  └── belongsTo -> Restaurant, Order, Product

PaymentTransaction (id, restaurant_id, order_id, provider, provider_payment_id, webhook_event_id, idempotency_key, amount_cents, tip_cents, currency, status, confirmed_at)
  └── belongsTo -> Restaurant, Order

FiscalRecord (id, restaurant_id, order_id, sequence_number, hash, prev_hash, total_amount, tax_amount, currency, status)
  └── belongsTo -> Restaurant, Order

Reservation (id, restaurant_id, table_id, customer_name, customer_email, customer_phone, party_size, reservation_date, reservation_time, status, notes)
  └── belongsTo -> Restaurant, Table

CashSession (id, restaurant_id, user_id, opened_at, closed_at, opening_amount, expected_amount, actual_amount, status)
  └── belongsTo -> Restaurant, User

Ingredient (id, restaurant_id, name, unit, stock_quantity, min_stock)
  └── belongsTo -> Restaurant

InventoryAdjustment (id, restaurant_id, ingredient_id, adjustment_type, quantity, reference_type, reference_id, notes)
  └── belongsTo -> Restaurant, Ingredient

OfflineOperation (id, restaurant_id, idempotency_key, type, payload [JSON], status, error_message)
  └── belongsTo -> Restaurant

AuditLog (id, restaurant_id, user_id, action, subject_type, subject_id, old_values [JSON], new_values [JSON], ip_address, user_agent)
  └── belongsTo -> Restaurant, User

Subscription (id, owner_id, restaurant_id, plan_name, status, ends_at)
  └── belongsTo -> User, Restaurant

TenantDesign (id, restaurant_id, primary_color, secondary_color, font_family, layout)
  └── belongsTo -> Restaurant
```

## Flujos de datos y eventos

### Creación de pedido

```
Cliente -> POST /client/orders -> Transaction (Order + idempotency)
    -> OrderItemCreated event -> Outbox (si implementado)
    -> WebSocket broadcast (si Reverb activo)
    -> Print Agent (si configurado)
```

### Actualización de estado de OrderItem

```
Staff -> PUT /staff/order-items/{id}/status -> Transaction (update + event)
    -> OrderStateChanged event
    -> ProcessInventoryDeduction listener (si implementado)
    -> WebSocket broadcast
```

### Cierre de mesa

```
Staff/Cliente -> POST /orders/{id}/close -> Transaction (order closed + table free + events)
    -> TableCleared event
    -> OrderStateChanged events (all items -> delivered)
    -> WebSocket broadcast
```

### Pago Stripe

```
Stripe Webhook -> POST /webhooks/stripe -> Verificar firma
    -> Deduplicar por event_id
    -> Transaction (PaymentTransaction update)
    -> closeOrderAndTable()
    -> sendDigitalInvoice()
```

### Sincronización offline

```
Frontend -> POST /staff/sync/offline -> Check idempotency_key
    -> Transaction (OfflineOperation + operation apply)
    -> Return: accepted/duplicate/rejected/conflict per operation
```

## Decisiones de arquitectura (ADRs)

| ADR | Tema | Estado |
|---|---|---|
| [0001](../docs/adr/0001-multitenancy-y-tenant-context.md) | Multi-tenancy y Tenant Context | Propuesto |
| [0002](../docs/adr/0002-eventos-outbox-e-idempotencia.md) | Eventos, Outbox e Idempotencia | Propuesto |
| [0003](../docs/adr/0003-dinero-pagos-y-fiscalidad.md) | Dinero, Pagos y Fiscalidad | Propuesto |

## Concurrencia e idempotencia

### Idempotencia

- `orders.idempotency_key`: `unique(restaurant_id, idempotency_key)`
- `order_items.idempotency_key`: se verifica antes de crear
- `OfflineOperation.idempotency_key`: deduplicación en sync
- Webhooks Stripe: deduplicación por `provider_payment_id` y `webhook_event_id`

### Concurrencia

- `lockForUpdate()` en `ReservationEngine` (mesas concurrentes)
- `lockForUpdate()` en `InventoryStockService` (ingredientes concurrentes)
- `DB::transaction()` en todas las operaciones críticas
- Transiciones de estado validadas en controlador (no se pueden saltar)

## Outbox Pattern

**Estado**: Diseñado en ADR-0002 pero **no implementado** como tabla `outbox_events` en el código actual. Los eventos se emiten directamente con `event()` dentro de transacciones.

## Snapshots históricos

**Implementado parcialmente**:
- `OrderItem.price_snapshot`: almacena precio en el momento del pedido
- `OrderItem.tax_rate`: almacena tipo impositivo aplicado
- `OrderItem.discount_amount`: almacena descuento aplicado
- `FiscalRecord.hash` / `prev_hash`: chain de integridad fiscal

**No implementado**:
- `price_cents` (se usa `decimal:2` en lugar de enteros en céntimos)
- Snapshots de nombre multi-idioma en order_items
- Snapshots de datos fiscales del restaurante
