# Uso del sistema — lafrenona3

Guía de uso por perfil de usuario.

## Perfiles del sistema

| Rol | Descripción | Acceso |
|---|---|---|
| `superadmin` | Administrador de la plataforma SaaS | `/api/v1/superadmin/*`, `/superadmin/*` |
| `owner` | Propietario del restaurante | `/api/v1/owner/*`, `/owner/*` |
| `waiter`/`kitchen`/`bar` | Personal del restaurante | `/api/v1/staff/*` |
| Cliente QR | Cliente que accede desde QR | `/api/v1/client/*` (público) |

## Seguridad y rendimiento

### Características de seguridad implementadas

- **CORS**: Configurado con `config/cors.php`, paths restringidos a `api/*` y `sanctum/csrf-cookie`
- **Rate limiting**: 5 limiters aplicados (auth_login, client_routes, default, offline_sync, superadmin)
- **CSP Headers**: Middleware `SecurityHeaders` aplica Content-Security-Policy, X-Frame-Options, HSTS
- **Timing attack prevention**: Login siempre ejecuta `Hash::check()` independientemente de existencia del usuario
- **Tenant isolation**: Global scopes, policies, middleware chain, verificación IDOR en controllers
- **CSRF**: Protegido vía Sanctum stateful + middleware ValidateCsrfToken
- **Rate limiting en login**: Máximo 10 intentos/minuto por email o IP

### Optimizaciones de rendimiento

- **Database indexes**: 17 indexes añadidos en migración `add_missing_indexes`
- **Eager loading**: N+1 queries corregidos en StaffBilling, ClientOrders, CashSession
- **Caching**: Menú público cacheado 15 minutos por restaurante
- **Bundle optimization**: Vite config con chunk splitting, terser minification, tree shaking
- **PWA caching**: Service worker con runtime caching (API NetworkFirst, imágenes CacheFirst)

---

## SuperAdmin

### Acceso

- Login con credenciales de `role = 'superadmin'` vía `POST /api/v1/auth/login`.
- Todas las rutas `/api/v1/superadmin/*` están protegidas con el middleware `superadmin`.
- UI disponible en `/superadmin/dashboard` (requiere login como superadmin).

### Funcionalidades implementadas

| Función | Endpoint | Método | UI |
|---|---|---|---|
| Listar restaurantes | `/api/v1/superadmin/restaurants` | GET | ✅ AdminDashboard |
| Crear restaurante | `/api/v1/superadmin/restaurants` | POST | ✅ AdminDashboard |
| Ver restaurante | `/api/v1/superadmin/restaurants/{restaurant}` | GET | ✅ AdminDashboard |
| Suspender restaurante | `/api/v1/superadmin/restaurants/{restaurant}/suspend` | PUT | ✅ AdminDashboard |
| Activar restaurante | `/api/v1/superadmin/restaurants/{restaurant}/activate` | PUT | ✅ AdminDashboard |
| Listar usuarios | `/api/v1/superadmin/users` | GET | ✅ AdminDashboard |
| Crear usuario | `/api/v1/superadmin/users` | POST | ✅ AdminDashboard |
| Suspender usuario | `/api/v1/superadmin/users/{user}/suspend` | PUT | ✅ AdminDashboard |

### Flujo principal

1. Login en `/login` con credenciales superadmin.
2. Redirección automática a `/superadmin/dashboard`.
3. CRUD completo de restaurantes y usuarios.
4. Suspender/activar tenants y usuarios.

---

## Owner (Propietario)

### Acceso

- Login con credenciales de `role = 'owner'`.
- Todas las rutas `/api/v1/owner/*` requieren autenticación + middleware `tenant.context` + `check.owner.restaurant` + `check.subscription`.
- UI disponible en `/owner/*` (requiere login como owner).

### Funcionalidades implementadas

| Función | Endpoint | Método | UI |
|---|---|---|---|
| Ver restaurante | `/api/v1/owner/restaurants` | GET | ✅ RestaurantsView |
| CRUD categorías | `/api/v1/owner/categories` | GET/POST/PUT/DELETE | ✅ OwnerLayout |
| CRUD productos | `/api/v1/owner/products` | GET/POST/PUT/DELETE | ✅ OwnerLayout |
| Categorías de productos | `/api/v1/owner/products/categories` | GET | ✅ OwnerLayout |
| CRUD mesas | `/api/v1/owner/tables` | GET/POST/PUT/DELETE | ✅ OwnerLayout |
| CRUD staff | `/api/v1/owner/staff` | GET/POST/PUT/DELETE | ✅ StaffView |
| Analíticas resumen | `/api/v1/owner/analytics/summary` | GET | ✅ OwnerLayout |
| Top productos | `/api/v1/owner/analytics/top-products` | GET | ✅ OwnerLayout |
| Exportar CSV | `/api/v1/owner/analytics/export/csv` | GET | ✅ OwnerLayout |
| Logs de auditoría | `/api/v1/owner/audit-logs` | GET | ✅ OwnerLayout |
| Inventario | `/api/v1/owner/inventory` | GET | ✅ OwnerLayout |
| Ajustar inventario | `/api/v1/owner/inventory/adjust` | POST | ✅ OwnerLayout |

### Flujo principal

1. Login en `/login` con credenciales owner.
2. Redirección automática a `/owner/staff` (dashboard por defecto).
3. Crear categorías de menú.
4. Crear productos con precio, alérgenos, disponibilidad.
5. Crear mesas con número y estado.
6. Gestionar staff (camareros, cocina, barra).
7. Consultar analíticas y exportar datos.
8. Gestionar inventario de ingredientes.

---

## Camarero / Sala (Staff)

### Acceso

- Login con credenciales de `role = 'waiter'` o similar.
- Rutas `/api/v1/staff/*` requieren autenticación + tenant context.

### Funcionalidades implementadas

| Función | Endpoint | Método |
|---|---|---|
| Ver estado de sala | `/api/v1/staff/room` | GET |
| Items pendientes cocina/barra | `/api/v1/staff/order-items/pending` | GET |
| Actualizar estado de item | `/api/v1/staff/order-items/{orderItem}/status` | PUT |
| Actualización masiva | `/api/v1/staff/order-items/bulk` | PUT |
| Cerrar pedido | `/api/v1/staff/orders/{order}/close` | POST |
| Sincronización offline | `/api/v1/staff/sync/offline` | POST |
| Reservar mesa para cliente | `/api/v1/staff/reservations/{reservation}/seat` | POST |
| Sesiones de caja | `/api/v1/staff/cash-sessions` | GET/POST |
| Cerrar caja | `/api/v1/staff/cash-sessions/{cashSession}/close` | POST |
| Registros fiscales | `/api/v1/staff/fiscal-records` | GET |

### Flujo principal

1. Ver estado de sala (mesas libres, ocupadas, con asistencia).
2. Ver pedidos pendientes en cocina/barra.
3. Actualizar estado de items: `pending -> cooking -> ready -> delivered`.
4. Cerrar pedidos y liberar mesas.
5. Gestionar sesiones de caja (apertura, cierre, diferencia).
6. Sincronizar operaciones offline cuando se recupera conexión.

---

## Cocina / Barra

### Acceso

- Mismo acceso que staff.
- Endpoint `/api/v1/staff/order-items/pending?area=kitchen` o `area=bar`.

### Funcionalidades implementadas

| Función | Descripción |
|---|---|
| Ver items pendientes | Lista de items con estado `pending`, `cooking`, `ready` |
| Actualizar estado | Transiciones validadas: `pending -> cooking -> ready -> delivered` |
| Actualización masiva | Cambiar estado de múltiples items a la vez |

### Transiciones de estado permitidas

| De | A |
|---|---|
| `pending` | `cooking`, `cancelled` |
| `cooking` | `ready` |
| `ready` | `delivered`, `cancelled` |
| `delivered` | (ninguna) |
| `cancelled` | (ninguna) |

### Agente de impresión

- El archivo `agentes/agente_impresion.py` escucha eventos WebSocket de `order-item.created`.
- Se conecta a Reverb (`localhost:6001` por defecto).
- Genera tickets de impresión en formato texto ESC/POS.
- Reconexión infinita con backoff exponencial.

---

## Cliente QR

### Acceso

- Acceso público sin autenticación.
- Se accede vía URL con parámetro `?restaurant={slug}`.
- Con token de mesa: `?restaurant={slug}&token={secret_token}`.
- PWA instalable en móvil (Android/iOS).

### Funcionalidades implementadas

| Función | Endpoint | Método | Autenticación |
|---|---|---|---|
| Ver carta | `/api/v1/client/menu` | GET | No |
| Crear pedido | `/api/v1/client/orders` | POST | Token de sesión |
| Añadir items | `/api/v1/client/orders/{order}/items` | POST | Token de sesión |
| Cerrar pedido | `/api/v1/client/orders/{order}/close` | POST | Auth o token |
| Solicitar asistencia | `/api/v1/client/assistance` | POST | Token de sesión |
| Iniciar pago | `/api/v1/client/payments/initiate` | POST | Auth |
| Crear reserva | `/api/v1/client/reservations` | POST | No |
| Ver reserva | `/api/v1/client/reservations/{reservation}` | GET | No |

### Flujo principal

1. Cliente escanea QR -> accede a `?restaurant={slug}`.
2. Ve carta con categorías y productos (cacheada 15 min).
3. Con token de mesa (QR específico de mesa): se marca mesa como `occupied`.
4. Añade productos al pedido (precios validados desde base de datos).
5. Solicita asistencia o pide la cuenta.
6. Inicia pago (simulado Stripe).

### Sesión QR

- `session_token`: token opaco de 64 caracteres generado para la mesa.
- `secret_token`: token de la mesa para autenticación pública.
- La mesa se marca `occupied` al iniciar sesión con token.
- Se libera `free` al cerrar pedido.

### PWA

- **Instalable**: Manifest configurado con nombre, icons, tema, display standalone.
- **Service Worker**: Workbox con precaching (25 archivos) y runtime caching.
- **Offline**: API responses cacheadas con NetworkFirst, imágenes con CacheFirst.
- **Actualizaciones**: Notificación automática al usuario cuando hay nueva versión.
- **Accesibilidad**: WCAG 2.1 AA, contraste, focus-visible, prefers-reduced-motion.

---

## Frontend Admin UI

### Estructura de vistas

| Vista | Ruta | Rol requerido | Descripción |
|---|---|---|---|
| LoginView | `/login` | Cualquiera | Login con validación reactiva |
| AdminDashboard | `/superadmin/dashboard` | superadmin | CRUD de restaurantes y usuarios |
| StaffView | `/owner/staff` | owner | Gestión de personal |
| RestaurantsView | `/owner/restaurants` | owner | Gestión de restaurantes |
| MenuView | `/client/menu` | Público | Carta del cliente |
| KitchenMonitor | `/staff/kitchen` | kitchen/bar | Monitor de cocina (no en router) |

### Estado de autenticación (Pinia)

- `authStore`: Token en localStorage, user en state.
- `fetchUser()`: Refresca datos del usuario desde API.
- `logout()`: Limpia estado y token, llama API de logout.
- Getters: `isAuthenticated`, `isSuperAdmin`, `isOwner`, `isStaff`, `restaurantId`.

### Router guards

- `requiresAuth`: Redirige a `/login` si no autenticado.
- `requiresSuperAdmin`: Redirige a `/owner/staff` si no es superadmin.
- `publicOnly`: Redirige al dashboard si ya autenticado.
