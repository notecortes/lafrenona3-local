# Despliegue — lafrenona3

## Estado actual

El proyecto **NO está listo para producción directa**. Se requieren correcciones antes de desplegar.

### Checklist pre-producción

- [x] Backend API con autenticación y multi-tenancy
- [x] Base de datos con migraciones y restricciones
- [x] Tests de multi-tenancy y auth aprobados
- [ ] Corregir 31 tests fallidos
- [ ] Completar frontend de administración
- [ ] Configurar CI/CD
- [ ] Configurar Reverb para WebSockets reales
- [ ] Configurar Stripe Connect real
- [ ] Configurar SMTP para emails
- [ ] Configurar Redis para cola y cache
- [ ] Auditoría de seguridad externa
- [ ] Revisión legal del módulo fiscal
- [ ] Pruebas de carga y estrés
- [ ] Plan de backups automatizados
- [ ] Health checks en producción
- [ ] Logging centralizado
- [ ] Monitorización (Sentry, Prometheus, etc.)

## Requisitos de servidor

### Mínimos

- **CPU**: 2 cores
- **RAM**: 4 GB
- **Disco**: 20 GB SSD
- **SO**: Ubuntu 22.04 LTS o similar
- **Docker**: Engine 24+ / Desktop con Compose

### Recomendados

- **CPU**: 4 cores
- **RAM**: 8 GB
- **Disco**: 50 GB SSD
- **MySQL**: 8.0 con innodb_buffer_pool_size >= 1GB
- **Redis**: 6+ con maxmemory >= 256MB

## Despliegue con Docker Compose

### docker-compose.yml de producción (referencia)

El `docker-compose.yml` actual es para desarrollo. Para producción se necesita una configuración adaptada:

```yaml
services:
  backend:
    build:
      context: .
      dockerfile: ./docker/backend.Dockerfile
    restart: unless-stopped
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - BROADCAST_CONNECTION=reverb
      - QUEUE_CONNECTION=redis
      - CACHE_STORE=redis
      - SESSION_DRIVER=database
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy

  mysql:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: lafrenona3_production
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_USER: lafrenona3_user
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - redis_data:/data

  # Reverb (WebSockets)
  reverb:
    build:
      context: .
      dockerfile: ./docker/backend.Dockerfile
    command: php artisan reverb:start --port=6001
    environment:
      - REVERB_PORT=6001

volumes:
  db_data:
  redis_data:
```

### Variables de producción

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.lafrenona3.com
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
SESSION_ENCRYPT=true
DB_HOST=mysql
REDIS_HOST=redis
REVERB_SCHEME=https
MAIL_MAILER=smtp
MAIL_HOST=${SMTP_HOST}
MAIL_PORT=${SMTP_PORT}
MAIL_USERNAME=${SMTP_USERNAME}
MAIL_PASSWORD=${SMTP_PASSWORD}
```

**Nunca hardcodear contraseñas.** Usar variables de entorno del sistema o un service de secretos.

## TLS y dominios

- El frontend debe servirse por HTTPS.
- La API debe estar detrás de un reverse proxy (nginx, Caddy) con TLS.
- CORS debe configurarse para los dominios de producción en `config/cors.php` (no existe en el proyecto actual).
- Sanctum `stateful` domains debe incluir los dominios de producción.

## Colas y workers

### Desarrollo actual

El `docker-compose.yml` usa:
```yaml
command: php artisan serve --host=0.0.0.0 --port=8000
```

Esto **no incluye worker de colas**. Para producción:

```yaml
command: php artisan serve --host=0.0.0.0 --port=8000
```

Y ejecutar workers separados:
```bash
docker compose exec backend php artisan queue:work --tries=3 --timeout=90
```

### Scheduler

Laravel Scheduler debe configurarse en el host:
```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Las scheduled tasks están registradas en `AppServiceProvider`.

## Reverb (WebSockets)

Para activar Reverb en producción:

1. Instalar y configurar:
```bash
docker compose exec backend php artisan reverb:start
```

2. Configurar variables:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_PORT=6001
REVERB_SCHEME=https
```

3. El puerto 6001 debe ser accesible desde el frontend.

## Migraciones seguras

### Estrategia expand-deploy-contract

1. **Expand**: Añadir nuevas columnas/tablas sin eliminar las antiguas.
2. **Deploy**: Desplegar código nuevo y viejo simultáneamente.
3. **Migrar datos**: Mover datos de la estructura antigua a la nueva.
4. **Contract**: Eliminar la estructura antigua.

### Comandos

```bash
# Crear migración
docker compose exec backend php artisan make:migration add_column_to_table

# Aplicar migraciones
docker compose exec backend php artisan migrate

# Rollback última migración
docker compose exec backend php artisan migrate:rollback

# Fresh (solo desarrollo/testing)
docker compose exec backend php artisan migrate:fresh
```

## Health checks

### Endpoint

```
GET /up
```

Devuelve 200 si la aplicación, MySQL, Redis y cola están operativos.

### Comandos de verificación

```bash
# Aplicación
curl https://api.lafrenona3.com/up

# MySQL
docker compose exec mysql mysqladmin ping -h localhost

# Redis
docker compose exec redis redis-cli ping

# Colas
docker compose exec backend php artisan queue:health
```

## Rollback

### Con Docker

```bash
# Detener nuevo contenedor
docker compose down

# Iniciar versión anterior
docker compose up -d
```

### Con Base de datos

```bash
# Rollback última migración
docker compose exec backend php artisan migrate:rollback

# Rollback todas las migraciones de una fase
docker compose exec backend php artisan migrate:rollback --step=5
```

**Advertencia**: Las migraciones fiscales (`fiscal_records`) son append-only. Nunca rollbackear migraciones que contengan datos fiscales.

## Checklist antes de producción

### Antes del primer despliegue

- [ ] Corregir tests fallidos
- [ ] Configurar dominio y TLS
- [ ] Configurar variables de entorno seguras
- [ ] Generar nueva APP_KEY
- [ ] Configurar SMTP
- [ ] Configurar Redis
- [ ] Configurar Reverb
- [ ] Configurar Stripe Connect (cuentas de cada tenant)
- [ ] Configurar backups automáticos
- [ ] Configurar monitorización
- [ ] Configurar logging centralizado
- [ ] Revisar CORS
- [ ] Desactivar APP_DEBUG
- [ ] Configurar rate limiting apropiado
- [ ] Verificar multi-tenancy en entorno real
- [ ] Ejecutar pruebas de carga
- [ ] Revisión legal fiscal
- [ ] Plan de recuperación ante desastres

### Después del despliegue

- [ ] Verificar health check
- [ ] Verificar login
- [ ] Verificar multi-tenancy (crear 2 tenants, verificar aislamiento)
- [ ] Verificar colas (crear pedido, verificar que se procesa)
- [ ] Verificar WebSockets (evento en tiempo real)
- [ ] Verificar emails (registro fiscal, factura digital)
- [ ] Verificar backups

## CI/CD

**Estado**: No configurado. No existen archivos en `.github/workflows/`.

Se recomienda implementar:

1. **Lint**: PHP Pint, ESLint (frontend pendiente)
2. **Tests**: PHPUnit + Vitest
3. **Build**: Frontend build verification
4. **Security**: Dependabot, secret scanning
5. **Deploy**: Docker build + push + deploy

## Qué falta para producción

### Crítico

1. **Frontend de administración**: Sin UI, el sistema es API-only.
2. **Tests fallidos**: 31 tests fallidos requieren corrección.
3. **Stripe Connect real**: Solo simulado.
4. **Reverb real**: Solo log driver en desarrollo.

### Importante

5. **CI/CD pipeline**: No existe.
6. **Backups automatizados**: No configurados.
7. **Monitorización**: No implementada.
8. **Logging centralizado**: Solo logs locales.
9. **CORS**: No configurado.
10. **Rate limiting ajustado**: Valores por defecto.

### Recomendado

11. **Pruebas de carga**: No existen.
12. **Auditoría de seguridad externa**: No realizada.
13. **Revisión legal fiscal**: Pendiente.
14. **Feature flags**: No implementados.
15. **Canary deployments**: No configurados.
