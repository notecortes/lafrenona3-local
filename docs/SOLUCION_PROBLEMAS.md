# Solución de Problemas — lafrenona3

## Contenedores no arrancan

### Síntoma
`docker compose up` se queda colgado o los contenedores se reinician continuamente.

### Diagnóstico
```bash
# Ver estado
docker compose ps

# Ver logs
docker compose logs backend
docker compose logs db
docker compose logs redis

# Verificar puertos
docker compose port backend 8000
docker compose port db 3306
docker compose port redis 6379
```

### Soluciones

**MySQL no arranca:**
```bash
# Verificar espacio en disco
df -h

# Verificar permisos del volumen
docker volume inspect lafrenona3_db_data

# Reiniciar con volumen limpio (pierde datos)
docker compose down -v
docker compose up -d db
```

**Backend no arranca:**
```bash
# Verificar que composer install se ejecutó
docker compose exec backend ls -la vendor/autoload.php

# Verificar APP_KEY
docker compose exec backend php artisan key:generate

# Verificar migraciones
docker compose exec backend php artisan migrate:status
```

## Puerto ocupado

### Síntoma
Error: `port is already allocated` o `Address already in use`.

### Diagnóstico
```bash
# Ver qué usa el puerto
lsof -i :4005
lsof -i :43306
lsof -i :46379
lsof -i :3000
```

### Solución
```bash
# Detener contenedores que usan el puerto
docker compose down

# Si el puerto lo usa otro proceso, matarlo
kill -9 $(lsof -t -i :4005)

# O cambiar el puerto en docker-compose.yml
# ports:
#   - "5005:8000"  # Cambiar 4005 por 5005
```

## Error de conexión MySQL

### Síntoma
`SQLSTATE[HY000] [2002] Connection refused` o `SQLSTATE[HY000] [1045] Access denied`.

### Diagnóstico
```bash
# Verificar que MySQL está corriendo
docker compose ps db

# Verificar logs de MySQL
docker compose logs db

# Probar conexión
docker compose exec db mysql -u lafrenona3_user -plafrenona3_password -e "SELECT 1;"

# Verificar que el backend puede alcanzar MySQL
docker compose exec backend bash -c "echo > /dev/tcp/db/3306 && echo OK || echo FAIL"
```

### Soluciones

**MySQL no disponible:**
```bash
docker compose restart db
# Esperar a que el healthcheck pase
docker compose ps db  # Debe mostrar "healthy"
```

**Credenciales incorrectas:**
```bash
# Verificar .env
cat backend/.env | grep DB_

# Asegurar que las variables coinciden con docker-compose.yml
# DB_HOST debe ser "db" (nombre del servicio Docker), no "127.0.0.1"
```

**Base de datos no existe:**
```bash
docker compose exec db mysql -u root -proot_password -e "CREATE DATABASE IF NOT EXISTS lafrenona3_matrix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Error de conexión Redis

### Síntoma
`Connection refused` en operaciones de cache/queue/session.

### Diagnóstico
```bash
docker compose ps redis
docker compose exec redis redis-cli ping
docker compose exec backend bash -c "echo > /dev/tcp/redis/6379 && echo OK || echo FAIL"
```

### Solución
```bash
docker compose restart redis
# Verificar que REDIS_HOST en .env es "redis" (nombre del servicio)
```

## Migraciones fallidas

### Síntoma
`Migration table created successfully` pero `SQLSTATE[42S01]: Base table already exists`.

### Diagnóstico
```bash
docker compose exec backend php artisan migrate:status
```

### Soluciones

**Migraciones parcialmente aplicadas:**
```bash
# Ver última migración aplicada
docker compose exec backend php artisan migrate:status | grep -A1 "Up"

# Rollback y reintentar
docker compose exec backend php artisan migrate:rollback
docker compose exec backend php artisan migrate
```

**Migraciones corruptas:**
```bash
# Eliminar tabla de migraciones y recrear
docker compose exec backend php artisan migrate:fresh
```

**Dependencias de datos:**
```bash
# Algunas migraciones pueden requerir datos previos
# Verificar orden de migraciones
ls -la backend/database/migrations/
```

## Dependencias Composer o npm

### Síntoma
`Class not found`, `Package not found`, o errores de autoloading.

### Diagnóstico
```bash
# Verificar autoload
docker compose exec backend ls -la vendor/autoload.php

# Verificar composer
docker compose exec backend composer diagnose

# Verificar npm
docker compose exec backend ls -la node_modules/package.json
```

### Soluciones

```bash
# Limpiar y reinstalar
docker compose exec backend rm -rf vendor/
docker compose exec backend composer install --no-dev

# Limpiar caché de composer
docker compose exec backend composer clear-cache

# Reinstalar npm
docker compose exec backend rm -rf node_modules/
docker compose exec backend npm install --ignore-scripts
docker compose exec backend npm run build
```

## Permisos de archivos

### Síntoma
`Permission denied` al escribir en storage o bootstrap/cache.

### Solución
```bash
# Asegurar permisos correctos
docker compose exec backend chown -R www-data:www-data /var/www/html/storage
docker compose exec backend chown -R www-data:www-data /var/www/html/bootstrap/cache

# O más simple (no recomendado en producción)
docker compose exec backend chmod -R 777 storage bootstrap/cache
```

## CORS / Sanctum

### Síntoma
`CORS policy` en el navegador o `Unauthenticated` desde frontend.

### Diagnóstico
```bash
# Verificar SANCTUM_STATEFUL_DOMAINS en .env
cat backend/.env | grep SANCTUM

# Verificar config/sanctum.php
docker compose exec backend cat config/sanctum.php
```

### Soluciones

**Frontend en localhost:3000:**
```
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1
```

**Frontend en subdominio:**
```
SANCTUM_STATEFUL_DOMAINS=app.example.com,localhost,localhost:3000
```

**Clear config:**
```bash
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan cache:clear
docker compose restart backend
```

## Reverb / WebSocket

### Síntoma
No hay actualizaciones en tiempo real, eventos no se reciben.

### Diagnóstico
```bash
# Verificar que Reverb está corriendo
docker compose exec backend php artisan reverb:status

# Verificar BROADCAST_CONNECTION
cat backend/.env | grep BROADCAST

# Verificar logs de Reverb
docker compose exec backend php artisan reverb:start --debug
```

### Soluciones

**Reverb no instalado:**
```bash
docker compose exec backend composer require laravel/reverb
docker compose exec backend php artisan reverb:install
```

**Configuración incorrecta:**
```
BROADCAST_CONNECTION=reverb
REVERB_HOST=localhost
REVERB_PORT=6001
REVERB_SCHEME=http
```

**Frontend no conecta:**
- Verificar que el frontend usa el URL correcto de Reverb.
- Verificar que el puerto 6001 es accesible.

## PWA / Service Worker

### Síntoma
La PWA no se instala, no funciona offline.

### Diagnóstico
```bash
# Verificar manifest.webmanifest
curl http://localhost:3000/manifest.webmanifest

# Verificar que existen los iconos
curl http://localhost:3000/icons/icon-192x192.png
curl http://localhost:3000/icons/icon-512x512.png
```

### Estado actual

El proyecto tiene un `manifest.webmanifest` básico pero:
- **No hay service worker implementado** (solo `pwa-register.js` como referencia).
- **No hay iconos en `/icons/`**.
- **No hay soporte offline real**.

### Para implementar

1. Crear directorio `frontend/public/icons/` con iconos en los tamaños necesarios.
2. Implementar service worker con Workbox o similar.
3. Registrar service worker en `main.js`.

## Playwright

### Síntoma
`npx playwright test` falla con errores de navegador.

### Estado actual

**Playwright no está configurado en el proyecto.** No existen tests E2E ni configuración de Playwright.

### Para añadir

```bash
cd frontend
npm init playwright@latest
```

## Variables de entorno

### Síntoma
`InvalidArgumentException: A valid base64 string is required` o `No application encryption key has been specified`.

### Solución
```bash
docker compose exec backend php artisan key:generate
```

### Síntoma
`SQLSTATE[HY000] [2002] No such file or directory` en desarrollo local (fuera de Docker).

### Solución
```bash
# En .env, cambiar DB_HOST a la IP del contenedor
# macOS:
DB_HOST=host.docker.internal

# Linux:
DB_HOST=172.17.0.1

# O usar el nombre del servicio si se ejecuta desde dentro del contenedor
DB_HOST=db
```

## Impresión local

### Síntoma
El agente de impresión no genera tickets.

### Diagnóstico
```bash
# Verificar que Python está disponible
python3 --version

# Verificar dependencias
pip3 show websockets

# Ejecutar agente manualmente
python3 agentes/agente_impresion.py
```

### Estado actual

El agente de impresión:
- Se conecta a Reverb en `localhost:6001`.
- Genera tickets en formato texto (no ESC/POS binario real).
- Tiene reconexión con backoff exponencial.
- **No está integrado en Docker Compose**.

### Para ejecutar

```bash
# Configurar variables en el script
REVERB_HOST=localhost
REVERB_PORT=6001
REVERB_APP_KEY=your_app_key

# Ejecutar
python3 agentes/agente_impresion.py
```

## Stripe / Webhooks de prueba

### Síntoma
Los webhooks de Stripe no se procesan.

### Diagnóstico
```bash
# Verificar STRIPE_WEBHOOK_SECRET
cat backend/.env | grep STRIPE

# Verificar logs del webhook
docker compose logs backend | grep -i "stripe\|webhook"
```

### Estado actual

- El controller existe en `StripeWebhookController.php`.
- Verifica firma Stripe.
- Deduplica por `event_id`.
- **No hay integración real con Stripe** (pagos simulados).
- **No hay cuenta Stripe Connect configurada**.

### Para probar con Stripe CLI

```bash
# Instalar Stripe CLI
brew install stripe/stripe-cli/stripe

# Forward webhooks locales
stripe listen --forward-to localhost:4005/api/v1/webhooks/stripe

# Trigger evento de prueba
stripe trigger payment_intent.succeeded
```

## Errores comunes por fase

| Fase | Error común | Solución |
|---|---|---|
| 1 | APP_KEY no generada | `php artisan key:generate` |
| 2 | Tenant scope no filtra | Verificar `BelongsToTenant` trait |
| 3 | CRUD no funciona | Verificar Form Requests |
| 4 | WebSockets no conectan | Verificar BROADCAST_CONNECTION |
| 5 | Impresión no funciona | Verificar Reverb + agente Python |
| 6 | Carta no carga | Verificar que hay categorías/productos |
| 7 | Snapshots no se guardan | Verificar migración de snapshot fields |
| 8 | Sync offline duplica | Verificar idempotency_key único |
| 9 | SuperAdmin no lista tenants | Verificar `withoutGlobalScopes()` |
| 10 | Deploy falla | Verificar variables de entorno |
| 11 | Analytics devuelve 0 | Verificar que hay orders cerradas |
| 12 | Audit logs vacíos | Verificar AuditLogger service |
| 13 | Assistance no broadcast | Verificar eventos de Reverb |
| 14 | Payment no confirma | Verificar Stripe webhook secret |
| 15 | Inventory no descuenta | Verificar ingredients asociados |
| 16 | Reservation conflict | Verificar julianday en SQLite |
| 17 | Fiscal chain breaks | Verificar hash canonical |
| 18 | CRUD errors | Verificar Form Requests |
