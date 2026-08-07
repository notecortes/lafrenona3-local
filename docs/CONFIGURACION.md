# Configuración — lafrenona3

Variables de entorno, configuración por servicio y diferencias entre entornos.

## Variables de entorno del backend

Archivo de referencia: `backend/.env.example`

### Aplicación

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `APP_NAME` | Sí | `lafrenona3_Restaurante` | Nombre de la aplicación |
| `APP_ENV` | Sí | `local` | `local`, `testing`, `staging`, `production` |
| `APP_KEY` | Sí | base64 generada | Clave de cifrado de Laravel. Generar con `php artisan key:generate` |
| `APP_DEBUG` | Sí | `false` | **Nunca** `true` en producción. Muestra stack traces |
| `APP_URL` | Sí | `http://localhost:4005` | URL base del backend |
| `APP_LOCALE` | No | `en` | Idioma por defecto |
| `APP_FALLBACK_LOCALE` | No | `en` | Idioma de respaldo |
| `APP_FAKER_LOCALE` | No | `en_US` | Locale de Faker para tests |
| `BCRYPT_ROUNDS` | No | `12` | Coste de bcrypt. 4 en testing |

### Base de datos

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `DB_CONNECTION` | Sí | `mysql` | Motor de base de datos |
| `DB_HOST` | Sí | `127.0.0.1` | Host. En Docker usar `db` (nombre del servicio) |
| `DB_PORT` | Sí | `3306` | Puerto MySQL |
| `DB_DATABASE` | Sí | `lafrenona3_matrix` | Nombre de la base de datos |
| `DB_USERNAME` | Sí | `your_username` | Usuario de BD |
| `DB_PASSWORD` | Sí | `your_password` | Contraseña de BD |

### Sesión y caché

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `SESSION_DRIVER` | Sí | `database` | Driver de sesión |
| `SESSION_LIFETIME` | No | `120` | Minutos de expiración |
| `SESSION_ENCRYPT` | No | `false` | Cifrar cookies de sesión |
| `SESSION_PATH` | No | `/` | Path de la cookie |
| `SESSION_DOMAIN` | No | `null` | Dominio de la cookie |
| `CACHE_STORE` | Sí | `database` | Driver de caché |

### Cola y broadcast

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `QUEUE_CONNECTION` | Sí | `database` | Driver de cola |
| `BROADCAST_CONNECTION` | Sí | `log` | Driver de broadcast. En producción usar `reverb` |

### Redis

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `REDIS_CLIENT` | No | `phpredis` | Cliente Redis |
| `REDIS_HOST` | Sí | `127.0.0.1` | Host. En Docker usar `redis` |
| `REDIS_PASSWORD` | No | `null` | Contraseña Redis |
| `REDIS_PORT` | No | `6379` | Puerto Redis |

### Email

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `MAIL_MAILER` | Sí | `log` | Driver de mail. En producción configurar SMTP |
| `MAIL_HOST` | No | `127.0.0.1` | Host SMTP |
| `MAIL_PORT` | No | `2525` | Puerto SMTP |
| `MAIL_USERNAME` | No | `null` | Usuario SMTP |
| `MAIL_PASSWORD` | No | `null` | Contraseña SMTP |
| `MAIL_FROM_ADDRESS` | No | `hello@example.com` | Email remitente |
| `MAIL_FROM_NAME` | No | `${APP_NAME}` | Nombre remitente |

### Laravel Reverb (WebSockets)

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `REVERB_APP_KEY` | No | `null` | App Key de Reverb |
| `REVERB_APP_SECRET` | No | `null` | App Secret de Reverb |
| `REVERB_APP_ID` | No | `null` | App ID de Reverb |
| `REVERB_HOST` | No | `localhost` | Host de Reverb |
| `REVERB_PORT` | No | `6001` | Puerto de Reverb |
| `REVERB_SCHEME` | No | `http` | Esquema (http/https) |

### Laravel Sanctum

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | No | `localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1` | Dominios autorizados para auth stateful |
| `SANCTUM_TOKEN_PREFIX` | No | `` | Prefix para tokens (secret scanning) |

### CORS

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `APP_CORS_ORIGINS` | No | `` | Orígenes permitidos separados por coma |

Configuración en `config/cors.php`:
- Paths: `api/*`, `sanctum/csrf-cookie`
- Allowed methods: `*`
- Allowed headers: `*`
- Supports credentials: `false`

### Almacenamiento S3 (opcional)

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `AWS_ACCESS_KEY_ID` | No | `` | Clave AWS |
| `AWS_SECRET_ACCESS_KEY` | No | `` | Secreto AWS |
| `AWS_DEFAULT_REGION` | No | `us-east-1` | Región AWS |
| `AWS_BUCKET` | No | `` | Bucket S3 |
| `AWS_USE_PATH_STYLE_ENDPOINT` | No | `false` | Usar endpoint estilo path |

### Stripe (requiere configuración externa)

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `STRIPE_SECRET` | No | `` | Secret key de Stripe |
| `STRIPE_PUBLISHABLE_KEY` | No | `` | Publishable key de Stripe |
| `STRIPE_WEBHOOK_SECRET` | No | `` | Webhook secret para verificación de firma |

### Vite/Frontend

| Variable | Obligatorio | Default | Descripción |
|---|---|---|---|
| `VITE_APP_NAME` | No | `${APP_NAME}` | Nombre expuesto al frontend |

## Entornos

### Local (desarrollo)

```
APP_ENV=local
APP_DEBUG=false
BROADCAST_CONNECTION=log
MAIL_MAILER=log
QUEUE_CONNECTION=database
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
```

### Testing

Configurado automáticamente en `phpunit.xml`:

```
APP_ENV=testing
BROADCAST_CONNECTION=null
CACHE_STORE=array
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
```

### Producción (referencia)

```
APP_ENV=production
APP_DEBUG=false
BROADCAST_CONNECTION=reverb
MAIL_MAILER=smtp
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
SESSION_ENCRYPT=true
DB_HOST=mysql-service
REDIS_HOST=redis-service
REVERB_SCHEME=https
```

## Gestión de secretos

- **Nunca** commitear el archivo `.env`. Está en `.gitignore`.
- Usar `.env.example` como plantilla sin secretos.
- En producción, usar variables de entorno del sistema o un service de secretos (AWS Secrets Manager, HashiCorp Vault).
- Rotar `APP_KEY` si se expone en un repositorio público.

## Feature flags

El proyecto no implementa un sistema de feature flags con base de datos. Las funcionalidades se activan/desactivan mediante:

- `BROADCAST_CONNECTION=log` vs `reverb` — desactiva WebSockets reales
- `MAIL_MAILER=log` — desactiva envío real de emails
- `QUEUE_CONNECTION=database` vs `redis` — cambia driver de cola
- Estado del restaurante (`status`): `active`/`suspended` — bloquea acceso de tenants suspendidos
- Estado de suscripción: `active`/`past_due`/`canceled` — bloquea acceso según suscripción

## Archivos de configuración

| Archivo | Contenido |
|---|---|
| `backend/config/sanctum.php` | Configuración de autenticación API |
| `backend/config/broadcasting.php` | Reverb, Pusher, Ably, Redis, Log |
| `backend/config/queue.php` | Configuración de cola |
| `backend/config/session.php` | Sesiones |
| `backend/config/cache.php` | Caché |
| `backend/config/mail.php` | Email |
| `backend/config/database.php` | MySQL, SQLite |
| `backend/config/services.php` | Servicios externos |
| `backend/config/cors.php` | CORS (nuevo) |

## Middlewares de seguridad

| Middleware | Archivo | Función |
|---|---|---|
| `SecurityHeaders` | `app/Http/Middleware/SecurityHeaders.php` | CSP, X-Frame-Options, HSTS, Referrer-Policy, Permissions-Policy |
| `EnsureTenantContext` | `app/Http/Middleware/EnsureTenantContext.php` | Establece contexto de tenant |
| `CheckOwnerRestaurant` | `app/Http/Middleware/CheckOwnerRestaurant.php` | Verifica ownership del restaurante |
| `CheckSubscription` | `app/Http/Middleware/CheckSubscription.php` | Verifica suscripción activa |
| `EnsureSuperAdmin` | `app/Http/Middleware/EnsureSuperAdmin.php` | Verifica rol superadmin |

## Rate limiters

| Limiter | Rutas | Límite |
|---|---|---|
| `auth_login` | `/v1/auth/login` | 10/min por email o IP |
| `client_routes` | `/v1/client/*` | 60/min por IP |
| `default` | Todas las demás | 60/min por IP |
| `offline_sync` | `/v1/staff/sync/offline` | 30/min por tenant |
| `superadmin` | `/v1/superadmin/*` | 100/min por IP |
