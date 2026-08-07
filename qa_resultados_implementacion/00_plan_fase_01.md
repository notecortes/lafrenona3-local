# Plan técnico — Fase 1

## Estado actual del repositorio

- **Estructura encontrada**: Repositorio con 18 especificaciones funcionales (`fase_1_*.md` a `fase_18_*.md`), 18 planes QA (`qa_fase_01_*` a `qa_fase_18_*`), `AGENTS.md`, `opencode.json`, `PROMPT_AGENTE_LOCAL.md` y directorio `temp/`.
- **Archivos existentes relevantes**:
  - `fase_1_infraestructura.md`: Especificación de Fase 1 con Docker, Laravel, migraciones, modelos y auth.
  - `qa_fase_01_infraestructura_base_de_datos_y_autenticación.md`: Plan de pruebas de Fase 1 con 4 casos críticos.
  - `AGENTS.md`: Reglas de arquitectura, calidad y protocolo por fase.
- **Estado de backend**: No existe. No hay `composer.json`, `backend/`, ni código Laravel.
- **Estado de frontend**: No existe. No hay `frontend/`, `package.json`, ni código Vue.
- **Estado de Docker**: No existe `docker-compose.yml` ni `docker/`.
- **Estado de tests**: No existen tests. No existe directorio `tests/`.
- **Compatibilidad**: El proyecto es un repositorio vacío de código de aplicación. Las especificaciones indican Laravel 11, PHP 8.3, MySQL 8, Redis, Vue 3, Vite, Pinia, Axios, PWA, Python 3 para el agente de impresión, y Docker Compose.

## Documentación revisada

- **Especificación de Fase 1**: `fase_1_infraestructura.md` — Cubre Docker, Laravel 11, MySQL 8, Redis, 9 migraciones, modelo User, AuthController y test `PhaseOneArchitectureTest`.
- **QA de Fase 1**: `qa_fase_01_infraestructura_base_de_datos_y_autenticación.md` — 4 casos críticos (QA-F01-001 a QA-F01-004), casos negativos, usabilidad, automatización y criterios de aprobación.
- **Casos EXT-F01**: El documento `pruebas_adicionales_endurecimiento_fases_01_18.md` **no existe** en el repositorio. Se documentará como no disponible y se aplicarán los casos de endurecimiento derivados directamente de AGENTS.md y las especificaciones.
- **ADRs creados**:
  - `docs/adr/0001-multitenancy-y-tenant-context.md`
  - `docs/adr/0002-eventos-outbox-e-idempotencia.md`
  - `docs/adr/0003-dinero-pagos-y-fiscalidad.md`

## Dependencias y bloqueos

- **Dependencias técnicas**:
  - PHP 8.3+ disponible en el host o en el Dockerfile.
  - Composer para instalar Laravel 11.
  - Docker y Docker Compose para levantar MySQL 8.0, Redis y el backend.
  - MySQL 8.0 con motor InnoDB para FK, índices, `CHECK` constraints.
  - Redis para cola y cache.
- **Archivos o credenciales de ejemplo necesarios**:
  - `.env.example` con variables de entorno base (sin secretos).
  - `APP_KEY` generada por `php artisan key:generate`.
- **Riesgos de compatibilidad**:
  - La especificación de Fase 1 menciona `PostgreSQL/MySQL` en el objetivo pero usa MySQL 8.0 en todo el código. Se usa exclusivamente MySQL 8.0.
  - La especificación de Fase 1 usa `APP_URL=http://localhost:8008` pero el puerto expuesto en Docker es `4005:8000`. Se alinea a `http://localhost:4005`.
  - La especificación de Fase 1 indica `APP_DEBUG=true` en `.env`. AGENTS.md prohíbe `APP_DEBUG=true` en producción. Para local/development se acepta, pero se documenta como riesgo.
  - La especificación de Fase 1 crea todas las migraciones de golpe (Fase 1-18). La Fase 1 solo debe implementar las migraciones de su alcance (users, subscriptions, restaurants, tenant_designs como base para Fase 2). Las migraciones de categorías, productos, mesas, pedidos y order_items se dejan preparadas como estructura pero su contenido funcional se implementa en fases posteriores, o bien se crean como migraciones vacías con timestamp futuro para evitar colisiones.
- **Bloqueos que impidan la implementación**: Ninguno. El repositorio está vacío de código de aplicación.

## Alcance exacto de la Fase 1

Según `fase_1_infraestructura.md` y `qa_fase_01_*`:

1. **Infraestructura Docker**: `docker-compose.yml` con MySQL 8.0, Redis y backend PHP 8.3. `docker/backend.Dockerfile`.
2. **Backend Laravel 11**: Proyecto Laravel 11 con PHP 8.3, `laravel/sanctum` instalado.
3. **MySQL y Redis**: Configuración de conexión en `.env`. Redis como queue connection.
4. **Migraciones de Fase 1**:
   - `0001_01_01_000000_create_users_table.php` — users con `restaurant_id` nullable, role enum.
   - `2026_01_01_000001_create_subscriptions_table.php` — subscriptions con owner_id FK.
   - `2026_01_01_000002_create_restaurants_table.php` — restaurants con owner_id FK, y FK de users.restaurant_id.
   - `2026_01_01_000003_create_tenant_designs_table.php` — tenant_designs con restaurant_id unique FK.
   - Las migraciones de categorías, productos, mesas, pedidos y order_items se crean como **placeholders con timestamp futuro** (`2027_01_01_...`) para evitar colisiones de FK en Fase 1, con estructura completa para implementación en fases 2-7.
5. **Modelos**: `User.php` con fillable, hidden, casts.
6. **Laravel Sanctum**: Configurado para API tokens.
7. **Login API**: `AuthController.php` con `login()` y ruta `/api/v1/auth/login`.
8. **Ruta de usuario**: `GET /api/v1/user` protegida con `auth:sanctum`.
9. **Tests**: `PhaseOneArchitectureTest.php` con 2 tests mínimo (migraciones+FK y login con token).

## Archivos a crear o modificar

| Archivo | Acción | Motivo |
|---|---|---|
| `docker-compose.yml` | Crear | Servicios MySQL, Redis, Backend |
| `docker/backend.Dockerfile` | Crear | PHP 8.3-cli-alpine con extensiones |
| `backend/.env.example` | Crear | Variables de entorno sin secretos |
| `backend/composer.json` | Crear (vía `composer create-project`) | Laravel 11 |
| `backend/.env` | Crear | Configuración de entorno local |
| `backend/config/sanctum.php` | Crear (vía `php artisan install:api`) | Sanctum config |
| `backend/routes/api.php` | Crear/Modificar | Rutas auth y user |
| `backend/app/Models/User.php` | Crear/Modificar | Modelo con fillable, hidden, casts |
| `backend/app/Http/Controllers/Api/AuthController.php` | Crear | Login API con Sanctum |
| `backend/database/migrations/0001_01_01_000000_create_users_table.php` | Crear | Tabla users |
| `backend/database/migrations/2026_01_01_000001_create_subscriptions_table.php` | Crear | Tabla subscriptions |
| `backend/database/migrations/2026_01_01_000002_create_restaurants_table.php` | Crear | Tabla restaurants + FK users |
| `backend/database/migrations/2026_01_01_000003_create_tenant_designs_table.php` | Crear | Tabla tenant_designs |
| `backend/database/migrations/2026_01_01_000004_create_categories_table.php` | Crear (placeholder) | Estructura para Fase 3 |
| `backend/database/migrations/2026_01_01_000005_create_products_table.php` | Crear (placeholder) | Estructura para Fase 3 |
| `backend/database/migrations/2026_01_01_000006_create_tables_table.php` | Crear (placeholder) | Estructura para Fase 3 |
| `backend/database/migrations/2026_01_01_000007_create_orders_table.php` | Crear (placeholder) | Estructura para Fase 7 |
| `backend/database/migrations/2026_01_01_000008_create_order_items_table.php` | Crear (placeholder) | Estructura para Fase 7 |
| `backend/tests/Feature/PhaseOneArchitectureTest.php` | Crear | Tests de Fase 1 |
| `docs/adr/0001-multitenancy-y-tenant-context.md` | Crear | ADR multi-tenancy |
| `docs/adr/0002-eventos-outbox-e-idempotencia.md` | Crear | ADR outbox |
| `docs/adr/0003-dinero-pagos-y-fiscalidad.md` | Crear | ADR dinero/pagos |
| `qa_resultados_implementacion/00_plan_fase_01.md` | Crear | Este plan |

## Diseño de datos e integridad

### Tablas y relaciones de Fase 1

**users**
- `id` (BIGINT, PK, auto-increment)
- `restaurant_id` (BIGINT, nullable, indexed) — Referencia a `restaurants.id` (FK se añade en migración de restaurants)
- `name` (VARCHAR 155, NOT NULL)
- `email` (VARCHAR 155, UNIQUE, NOT NULL)
- `password` (VARCHAR 255, NOT NULL, cast `hashed`)
- `role` (ENUM: `superadmin`, `owner`, `waiter`, `kitchen`, `bar`, NOT NULL)
- `remember_token` (VARCHAR 100, nullable)
- `created_at`, `updated_at` (TIMESTAMP)
- FK: `restaurant_id` → `restaurants.id` ON DELETE CASCADE (definida en migración de restaurants)

**subscriptions**
- `id` (BIGINT, PK)
- `owner_id` (BIGINT, NOT NULL) — FK a `users.id` ON DELETE CASCADE
- `plan_name` (VARCHAR 50, NOT NULL)
- `status` (ENUM: `trialing`, `active`, `past_due`, `canceled`, default `trialing`)
- `ends_at` (TIMESTAMP, nullable)
- `created_at`, `updated_at`

**restaurants**
- `id` (BIGINT, PK)
- `owner_id` (BIGINT, NOT NULL) — FK a `users.id` ON DELETE RESTRICT (un owner no se borra si tiene restaurantes)
- `name` (VARCHAR 155, NOT NULL)
- `slug` (VARCHAR 155, UNIQUE, NOT NULL)
- `status` (ENUM: `active`, `suspended`, default `active`)
- `weekend_mode` (BOOLEAN, default false)
- `created_at`, `updated_at`
- Índice: `unique(restaurant_id, slug)` — en realidad `unique(slug)` ya que slug es único por sistema

**tenant_designs**
- `id` (BIGINT, PK)
- `restaurant_id` (BIGINT, UNIQUE, NOT NULL) — FK a `restaurants.id` ON DELETE CASCADE
- `primary_color` (VARCHAR 7, default '#FF5733')
- `secondary_color` (VARCHAR 7, default '#333333')
- `background_color` (VARCHAR 7, default '#FAFAFA')
- `font_family` (VARCHAR 50, default 'Roboto')
- `menu_layout` (ENUM: `grid`, `list`, default `list`)
- `logo_url` (TEXT, nullable)
- `created_at`, `updated_at`

### Índices y restricciones

- `users.email`: UNIQUE.
- `users.restaurant_id`: INDEX, FK a `restaurants.id`.
- `subscriptions.owner_id`: INDEX + FK a `users.id` ON DELETE CASCADE.
- `restaurants.owner_id`: INDEX + FK a `users.id` ON DELETE RESTRICT.
- `restaurants.slug`: UNIQUE.
- `tenant_designs.restaurant_id`: UNIQUE + FK a `restaurants.id` ON DELETE CASCADE.

### Decisiones de tenant aplicables desde Fase 1

- `users.restaurant_id` es nullable porque el `superadmin` no pertenece a un restaurante. Los demás roles sí.
- La FK `users.restaurant_id` → `restaurants.id` ON DELETE CASCADE se crea en la migración de `restaurants` (no en la de `users`) para respetar el orden de creación.
- `owner_id` en `subscriptions` y `restaurants` apunta a `users.id`, no a `users.restaurant_id`. El owner es un usuario global.
- Se prepara el terreno para el modelo de membresías (Fase 2+) sin implementarlo todavía.

### Decisiones de migración compatibles con los ADRs

- Las migraciones de categorías, productos, mesas, pedidos y order_items se crean con timestamp `2027_01_01_` (futuro) para evitar colisiones de FK en la Fase 1, pero con estructura completa según especificación. Esto permite que las fases 2-7 las apliquen sin reescribir.
- Los tipos de precio se dejan como `decimal(10,2)` provisionalmente en las migraciones placeholder; la migración a `*_cents` se hace cuando se implementa la Fase 7 (ADR-0003).
- No se añaden `CHECK` constraints en MySQL 8.0 para ENUMs ya que MySQL las ignora (se validan a nivel de aplicación con Form Requests en fases posteriores).

## API y contratos

| Método | Ruta | Autenticación | Entrada | Respuesta | Errores esperados |
|---|---|---|---|---|---|
| POST | `/api/v1/auth/login` | No | `{ email, password }` | `{ access_token, token_type, user: { id, name, email, role, restaurant_id } }` | 422 (email inválido, campo ausente); 401 (credenciales incorrectas) |
| GET | `/api/v1/user` | Sí (Sanctum Bearer) | — | `{ id, name, email, role, restaurant_id, ... }` | 401 (sin token, token inválido, token expirado) |

### Detalles de entrada/salida

**POST /api/v1/auth/login**
- Request body: `{ "email": "string (email, max 155)", "password": "string" }`
- Success 200: `{ "access_token": "string", "token_type": "Bearer", "user": { "id": int, "name": "string", "email": "string", "role": "enum", "restaurant_id": int|null } }`
- Error 422: `{ "message": "The email field is required.", "errors": { "email": ["..."] } }`
- Error 401: `{ "message": "The provided credentials are incorrect.", "errors": { "email": ["..."] } }`

**GET /api/v1/user**
- Headers: `Authorization: Bearer {token}`
- Success 200: Objeto User serializado por Sanctum
- Error 401: `{ "message": "Unauthenticated." }`

## Plan de implementación por pasos

1. **Crear estructura Docker**: `docker-compose.yml` y `docker/backend.Dockerfile`.
2. **Levantar contenedores**: `docker compose up -d --build` para MySQL y Redis.
3. **Inicializar Laravel 11**: `composer create-project laravel/laravel backend` dentro del contenedor o en el host con mount.
4. **Instalar Sanctum**: `php artisan install:api` dentro del backend.
5. **Configurar `.env`**: Variables de DB, Redis, APP_KEY, APP_URL.
6. **Crear migraciones de Fase 1**: users, subscriptions, restaurants, tenant_designs con índices y FK.
7. **Crear migraciones placeholder (Futuro)**: categories, products, tables, orders, order_items con timestamp `2027_01_01_` y estructura completa.
8. **Configurar modelo User**: fillable, hidden, casts, relaciones básicas.
9. **Crear AuthController**: login con validación, Hash::check, createToken.
10. **Configurar routes/api.php**: POST /api/v1/auth/login, GET /api/v1/user con `auth:sanctum`.
11. **Crear tests**: `PhaseOneArchitectureTest.php` con tests de migración/FK y login.
12. **Ejecutar migraciones en testing**: `php artisan migrate:fresh --env=testing`.
13. **Ejecutar tests**: `php artisan test`.

## Plan de pruebas

### Tests funcionales
- **DB structure**: Verificar que las 4 tablas de Fase 1 se crean correctamente con `RefreshDatabase`. Verificar existencia de FK, índices y UNIQUE constraints consultando `information_schema`.
- **User CRUD**: Crear un usuario owner, verificar que persiste y que `restaurant_id` es nullable.
- **Login API**: Crear usuario, hacer POST a `/api/v1/auth/login` con credenciales correctas, verificar 200 y estructura de respuesta con `access_token`, `token_type`, `user`.
- **User endpoint**: Usar el token recibido para GET `/api/v1/user`, verificar 200 y datos del usuario.
- **Sanctum token**: Verificar que el token se almacena en `personal_access_tokens` y se puede invalidar.

### Tests de seguridad
- **Credenciales erróneas**: POST con email correcto pero password incorrecto → 401. POST con email inexistente → 401 (no revelar si el email existe o no).
- **Sin autenticación**: GET `/api/v1/user` sin token → 401.
- **Token inválido**: GET `/api/v1/user` con token inventado → 401.
- **Payload malformado**: POST /login sin `email`, con `email` no-email, con `password` ausente → 422 con errores por campo.
- **Exposición de secretos**: Verificar que la respuesta de login no incluye `password`, `remember_token`, ni `api_token`.

### Tests de endurecimiento EXT-F01
Dado que `pruebas_adicionales_endurecimiento_fases_01_18.md` no existe, se derivan los casos EXT-F01 de las reglas de AGENTS.md y los criterios de QA-F01:

- **EXT-F01-001**: Verificar que las FK de `users.restaurant_id`, `subscriptions.owner_id`, `restaurants.owner_id` y `tenant_designs.restaurant_id` se crean correctamente en MySQL y respetan ON DELETE CASCADE/RESTRICT.
- **EXT-F01-002**: Verificar que `users.email` es UNIQUE y que un doble registro produce error de duplicidad a nivel de BD.
- **EXT-F01-003**: Verificar que `restaurants.slug` es UNIQUE.
- **EXT-F01-004**: Verificar que el login con credenciales erróneas no filtra si el email existe o no (mismo mensaje de error para ambos casos).
- **EXT-F01-005**: Verificar que la respuesta de login no incluye `password` ni `remember_token`.
- **EXT-F01-006**: Verificar que el modelo User tiene `password` en `$hidden`.

### Criterio de aprobación
- Los 2 tests de `PhaseOneArchitectureTest.php` pasan (OK).
- Los tests de seguridad (credenciales erróneas, sin auth, token inválido, payload malformado) pasan.
- Las 4 tablas de Fase 1 existen con FK, índices y UNIQUE constraints.
- Cero errores de consola, red o base de datos.
- Pruebas reproducibles con código de salida 0.

## Comandos previstos

```bash
# Levantar infraestructura
docker compose up -d --build

# Inicializar Laravel
docker compose exec backend composer create-project laravel/laravel . --no-interaction --prefer-dist

# Instalar Sanctum
docker compose exec backend php artisan install:api

# Generar APP_KEY
docker compose exec backend php artisan key:generate

# Ejecutar migraciones en testing
docker compose exec backend php artisan migrate:fresh --env=testing

# Ejecutar tests
docker compose exec backend php artisan test --filter=PhaseOneArchitectureTest

# Ejecutar todos los tests
docker compose exec backend php artisan test
```

## Riesgos y decisiones que requieren aprobación

| Decisión o riesgo | Alternativas | Recomendación |
|---|---|---|
| **APP_DEBUG=true en local** | APP_DEBUG=false en todos los entornos | Se acepta en local/development. Se documenta como riesgo y se garantiza que `.env` está en `.gitignore` y `APP_DEBUG=false` en `.env.example` de producción. |
| **Migraciones placeholder con timestamp 2027** | No crear migraciones placeholder; crearlas cuando corresponda | Se crean con timestamp futuro para evitar colisiones de FK en Fase 1, pero con estructura completa. Esto evita reescribirlas después y mantiene el orden cronológico. |
| **Precio como decimal(10,2) provisional** | Usar directamente `*_cents` en todas las migraciones | Se usa `decimal(10,2)` provisionalmente en las migraciones placeholder de Fase 1. La migración a `*_cents` se hace en Fase 7 cuando se implementa el ADR-0003. Esto minimiza el cambio en Fase 1. |
| **Nombre de la app: "rapidito_Restaurante" vs nombre genérico** | Nombre genérico del proyecto | La especificación de Fase 1 usa "rapidito_Restaurante". Se mantiene como nombre provisional pero se documenta que es configurable vía `APP_NAME`. |
| **Puerto de la API: 4005 vs 8008** | Usar el puerto indicado en APP_URL (8008) o el puerto expuesto en Docker (4005) | Se alinea `APP_URL` al puerto expuesto en Docker: `http://localhost:4005`. La especificación de Fase 1 tiene una inconsistencia aquí. |
| **Falta de `pruebas_adicionales_endurecimiento_fases_01_18.md`** | No aplicar endurecimiento adicional | Se documenta como ausente. Se aplican los casos EXT-F01 derivados de AGENTS.md y QA-F01. Si el documento aparece después, se añaden los casos correspondientes. |
| **MySQL 8.0 vs PostgreSQL** | Soportar ambos | La especificación menciona "PostgreSQL/MySQL" en el objetivo pero usa MySQL 8.0 en todo el código. Se usa exclusivamente MySQL 8.0. |
| **FK users.restaurant_id ON DELETE CASCADE** | ON DELETE SET NULL | ON DELETE CASCADE es más seguro: si se borra un restaurante, los usuarios sin membership activa se limpian automáticamente. Las memberships (Fase 2+) manejan la relación muchos-a-muchos. |
