# API Reference — lafrenona3

Base URL: `http://localhost:4005/api/v1`

## Autenticación

### Login

```
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secret123"
}
```

**Respuesta 200:**
```json
{
  "access_token": "3|AbCdEf...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "role": "owner",
    "restaurant_id": 1
  }
}
```

**Respuesta 422:** credenciales incorrectas o email inválido (mismo mensaje para ambos casos).

### Logout

```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

**Respuesta 200:** `{"message": "Logged out successfully."}`

### Perfil actual

```
GET /api/v1/user
Authorization: Bearer {token}
```

**Respuesta 200:**
```json
{
  "data": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "role": "owner",
    "restaurant_id": 1,
    "restaurant": {
      "id": 1,
      "name": "Mi Restaurante",
      "slug": "mi-restaurante",
      "status": "active"
    }
  }
}
```

## Formato de errores

```json
{
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

O mensajes simples:
```json
{
  "message": "Access denied."
}
```

## Códigos HTTP

| Código | Significado |
|---|---|
| 200 | OK |
| 201 | Created |
| 400 | Bad request (firma webhook inválida) |
| 401 | Unauthenticated |
| 403 | Forbidden (suscripción, restaurante suspendido, tenant mismatch) |
| 404 | Not found |
| 422 | Validation error / invalid state transition |
| 500 | Internal server error |

---

## Rutas Públicas (Cliente QR)

### Ver carta

```
GET /api/v1/client/menu?restaurant={slug}&token={table_secret_token}
```

**Respuesta 200:**
```json
{
  "restaurant": {"id": 1, "name": "...", "slug": "..."},
  "categories": [...],
  "products": [...],
  "session_token": "abc123...",
  "table_number": 5
}
```

### Crear pedido

```
POST /api/v1/client/orders
Content-Type: application/json

{
  "session_token": "abc123...",
  "restaurant_slug": "mi-restaurante"
}
```

**Respuesta 201:**
```json
{
  "data": {
    "id": 1,
    "restaurant_id": 1,
    "table_id": 5,
    "status": "open",
    "total_price": 0.00,
    "items": []
  }
}
```

### Añadir items al pedido

```
POST /api/v1/client/orders/{order}/items
Content-Type: application/json

{
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 12.50,
      "notes": "Sin cebolla",
      "target_area": "kitchen",
      "idempotency_key": "uuid-here"
    }
  ]
}
```

**Headers opcionales:** `X-Idempotency-Key: uuid`

### Cerrar pedido

```
POST /api/v1/client/orders/{order}/close
Authorization: Bearer {token}
```

### Solicitar asistencia

```
POST /api/v1/client/assistance
Content-Type: application/json

{
  "session_token": "abc123...",
  "type": "waiter_called"
}
```

`type`: `waiter_called` | `bill_requested`

### Iniciar pago

```
POST /api/v1/client/payments/initiate
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_id": 1,
  "tip_cents": 200,
  "currency": "EUR"
}
```

### Crear reserva

```
POST /api/v1/client/reservations
Content-Type: application/json

{
  "restaurant_slug": "mi-restaurante",
  "customer_name": "Juan",
  "customer_email": "juan@example.com",
  "customer_phone": "+34600000000",
  "party_size": 4,
  "reservation_date": "2026-08-15",
  "reservation_time": "20:00",
  "notes": "Ventana"
}
```

### Ver reserva

```
GET /api/v1/client/reservations/{reservation}
```

---

## Rutas Staff

### Items pendientes

```
GET /api/v1/staff/order-items/pending?area=kitchen
Authorization: Bearer {token}
```

`area`: `kitchen` | `bar`

### Actualizar estado de item

```
PUT /api/v1/staff/order-items/{orderItem}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "cooking"
}
```

Transiciones válidas: `pending -> cooking/ready -> delivered/cancelled`

### Actualización masiva

```
PUT /api/v1/staff/order-items/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
  "items": [
    {"id": 1, "status": "cooking"},
    {"id": 2, "status": "ready"}
  ]
}
```

### Cerrar pedido (staff)

```
POST /api/v1/staff/orders/{order}/close
Authorization: Bearer {token}
```

### Estado de sala

```
GET /api/v1/staff/room
Authorization: Bearer {token}
```

### Sincronización offline

```
POST /api/v1/staff/sync/offline
Authorization: Bearer {token}
Content-Type: application/json

{
  "operations": [
    {
      "idempotency_key": "uuid-1",
      "type": "order_item_create",
      "payload": {"product_id": 1, "order_id": 5, "quantity": 2, "unit_price": 12.50}
    }
  ]
}
```

**Respuesta:**
```json
{
  "data": {
    "total": 1,
    "accepted": 1,
    "duplicates": 0,
    "rejected": 0,
    "conflicts": 0,
    "results": [
      {
        "idempotency_key": "uuid-1",
        "type": "order_item_create",
        "status": "accepted",
        "message": "Order item created."
      }
    ]
  }
}
```

Estados por operación: `accepted`, `duplicate`, `rejected`, `conflict`

### Reservar mesa (staff)

```
POST /api/v1/staff/reservations/{reservation}/seat
Authorization: Bearer {token}
Content-Type: application/json

{
  "table_id": 3
}
```

### Sesiones de caja

```
GET  /api/v1/staff/cash-sessions
POST /api/v1/staff/cash-sessions
POST /api/v1/staff/cash-sessions/{cashSession}/close
```

**Store:**
```json
{"opening_amount": 100.00}
```

**Close:**
```json
{"actual_amount": 150.50}
```

### Registros fiscales

```
GET /api/v1/staff/fiscal-records?per_page=15&status=issued&date_from=2026-01-01
Authorization: Bearer {token}
```

---

## Rutas Owner

### CRUD Categorías

```
GET    /api/v1/owner/categories
GET    /api/v1/owner/categories/{category}
POST   /api/v1/owner/categories
PUT    /api/v1/owner/categories/{category}
DELETE /api/v1/owner/categories/{category}
```

**Store/Update:**
```json
{
  "name": {"en": "Starters", "es": "Entrantes"},
  "sort_order": 1,
  "is_active": true
}
```

### CRUD Productos

```
GET    /api/v1/owner/products
GET    /api/v1/owner/products/{product}
POST   /api/v1/owner/products
PUT    /api/v1/owner/products/{product}
DELETE /api/v1/owner/products/{product}
GET    /api/v1/owner/products/categories
```

**Store:**
```json
{
  "category_id": 1,
  "name": {"en": "Paella", "es": "Paella"},
  "description": {"en": "Traditional valencian paella", "es": "Paella valenciana tradicional"},
  "price": 15.50,
  "weekend_price": 18.00,
  "allergens": ["gluten", "shellfish"],
  "is_active": true,
  "is_vegan": false,
  "is_vegetarian": false,
  "stock_status": "available"
}
```

### CRUD Mesas

```
GET    /api/v1/owner/tables
GET    /api/v1/owner/tables/{table}
POST   /api/v1/owner/tables
PUT    /api/v1/owner/tables/{table}
DELETE /api/v1/owner/tables/{table}
```

**Store:**
```json
{
  "number": 5,
  "status": "free"
}
```

### CRUD Staff

```
GET    /api/v1/owner/staff
GET    /api/v1/owner/staff/{user}
POST   /api/v1/owner/staff
PUT    /api/v1/owner/staff/{user}
DELETE /api/v1/owner/staff/{user}
```

**Store:**
```json
{
  "name": "Camarero Nuevo",
  "email": "camarero@example.com",
  "password": "password123",
  "role": "waiter"
}
```

### Analíticas

```
GET /api/v1/owner/analytics/summary?start_date=2026-01-01&end_date=2026-08-01
GET /api/v1/owner/analytics/top-products?limit=10&start_date=2026-01-01
GET /api/v1/owner/analytics/export/csv?start_date=2026-01-01
```

### Auditoría

```
GET /api/v1/owner/audit-logs?per_page=15&action=category_updated&date_from=2026-01-01
```

### Inventario

```
GET  /api/v1/owner/inventory?low_stock=true
POST /api/v1/owner/inventory/adjust
```

**Adjust:**
```json
{
  "ingredient_id": 1,
  "quantity": 5.0,
  "type": "in",
  "notes": "Reposición semanal"
}
```

`type`: `in` | `out` | `adjustment`

---

## Rutas SuperAdmin

```
GET    /api/v1/superadmin/restaurants?per_page=15
POST   /api/v1/superadmin/restaurants
GET    /api/v1/superadmin/restaurants/{restaurant}
PUT    /api/v1/superadmin/restaurants/{restaurant}/suspend
PUT    /api/v1/superadmin/restaurants/{restaurant}/activate
GET    /api/v1/superadmin/users?per_page=15
POST   /api/v1/superadmin/users
PUT    /api/v1/superadmin/users/{user}/suspend
```

**Crear restaurante:**
```json
{
  "name": "Nuevo Restaurante",
  "slug": "nuevo-restaurante",
  "owner_email": "owner@example.com",
  "owner_name": "Propietario",
  "owner_password": "password123",
  "plan_name": "pro"
}
```

**Crear usuario:**
```json
{
  "name": "Empleado",
  "email": "empleado@example.com",
  "password": "password123",
  "role": "waiter",
  "restaurant_id": 1
}
```

---

## Webhooks

### Stripe

```
POST /api/v1/webhooks/stripe
Stripe-Signature: {signature}
```

Maneja eventos: `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`.

Verifica firma Stripe, deduplica por `event_id` y `provider_payment_id`.

---

## Rate Limiting

| Named rate limiter | Límite | Basado en |
|---|---|---|
| `default` | 60/min | user.id o IP |
| `client_routes` | 100/min | IP |
| `auth_login` | 10/min | email o IP |
| `offline_sync` | 60/min | user.id o IP |
| `superadmin` | 120/min | user.id o IP |

## WebSockets (Reverb)

### Canales

| Canal | Tipo | Acceso |
|---|---|---|
| `App.Models.User.{id}` | Presence | Usuario con ese ID |
| `restaurant.{restaurantId}` | Presence | SuperAdmin o usuario del restaurant |

### Eventos

| Evento | Descripción |
|---|---|
| `OrderItemCreated` | Nuevo item de pedido |
| `OrderStateChanged` | Cambio de estado de order item |
| `TableCleared` | Mesa liberada |
| `ClientAssistanceRequested` | Solicitud de asistencia |

---

## Ejemplos curl

```bash
# Login
curl -X POST http://localhost:4005/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'

# Ver carta
curl http://localhost:4005/api/v1/client/menu?restaurant=mi-restaurante

# Crear categoría (owner)
TOKEN="3|AbCdEf..."
curl -X POST http://localhost:4005/api/v1/owner/categories \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":{"en":"Starters"},"sort_order":1}'

# Actualizar estado de item (staff)
curl -X PUT http://localhost:4005/api/v1/staff/order-items/1/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"cooking"}'

# Health check
curl http://localhost:4005/up
```
