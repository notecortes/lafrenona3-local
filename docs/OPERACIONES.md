# Operaciones — lafrenona3

## Arranque y parada

### Arranque

```bash
# Levantar todos los servicios
docker compose up -d

# Levantar solo backend (si DB y Redis ya están corriendo)
docker compose up -d backend
```

### Parada

```bash
# Parar contenedores (mantiene datos)
docker compose down

# Parar y eliminar contenedores huérfanos
docker compose down --remove-orphans

# Parar y eliminar volúmenes (elimina BD)
docker compose down -v
```

## Logs

### Logs de contenedores

```bash
# Todos los logs
docker compose logs -f

# Backend
docker compose logs -f backend

# Base de datos
docker compose logs -f db

# Redis
docker compose logs -f redis

# Logs del backend (Laravel)
docker compose exec backend tail -f storage/logs/laravel.log

# Logs de errores
docker compose exec backend tail -f storage/logs/error.log
```

### Logs de aplicaciones

| Servicio | Ruta en contenedor |
|---|---|
| Laravel | `storage/logs/laravel.log` |
| MySQL | `/var/log/mysql/error.log` |
| Redis | stdout del contenedor |

### Consultar logs con filtros

```bash
# Errores de las últimas 24 horas
docker compose logs --since=24h backend | grep -i error

# Peticiones con error 500
docker compose logs backend | grep "\"status\":500"

# Errores de autenticación
docker compose logs backend | grep -i "auth\|unauth"
```

## Estado de contenedores

```bash
# Estado de todos los servicios
docker compose ps

# Estado con más detalles
docker compose ps -a

# Recursos consumidos
docker compose stats

# Inspeccionar contenedor
docker inspect lafrenona3_backend
```

## Workers, scheduler, Reverb y colas

### Workers de cola

El docker-compose.yml actual **no inicia workers de cola automáticamente**. Para producción:

```bash
# Iniciar worker de cola (fuera de docker compose)
docker compose exec backend php artisan queue:work --tries=3 --timeout=90

# Worker con monitor de procesos
docker compose exec backend php artisan queue:work --daemon --tries=3 --timeout=90

# Varios workers
docker compose exec backend php artisan queue:work --workers=4 --tries=3 --timeout=90
```

### Scheduler

```bash
# Ejecutar tareas programadas manualmente
docker compose exec backend php artisan schedule:run

# Ver tareas programadas
docker compose exec backend php artisan schedule:list
```

Para producción, añadir al crontab del host:
```cron
* * * * * cd /path/to/project && docker compose exec -T backend php artisan schedule:run >> /dev/null 2>&1
```

### Reverb (WebSockets)

```bash
# Iniciar Reverb
docker compose exec backend php artisan reverb:start --port=6001

# Iniciar en daemon
docker compose exec backend php artisan reverb:start --port=6001 --debug

# Ver estado
docker compose exec backend php artisan reverb:status
```

### Estado de colas

```bash
# Colas pendientes
docker compose exec backend php artisan queue:monitor

# Trabajos fallidos
docker compose exec backend php artisan queue:failed

# Ver un trabajo fallido
docker compose exec backend php artisan queue:failed-table

# Reintentar trabajo fallido
docker compose exec backend php artisan queue:retry {id}

# Reintentar todos los fallidos
docker compose exec backend php artisan queue:retry --all

# Eliminar trabajo fallido
docker compose exec backend php artisan queue:forget {id}

# Limpiar todos los fallidos
docker compose exec backend php artisan queue:flush
```

## Reintentos y recuperación ante fallos

### Trabajos de cola fallidos

```bash
# Ver trabajos fallidos con detalles
docker compose exec backend php artisan queue:failed

# Reintentar uno específico
docker compose exec backend php artisan queue:retry 1

# Reintentar todos
docker compose exec backend php artisan queue:retry --all
```

### Migraciones fallidas

```bash
# Ver estado de migraciones
docker compose exec backend php artisan migrate:status

# Rollback última migración
docker compose exec backend php artisan migrate:rollback

# Forzar migración (si hay errores de datos)
docker compose exec backend php artisan migrate --force
```

### Base de datos bloqueada

```bash
# Ver procesos activos en MySQL
docker compose exec db mysql -u lafrenona3_user -plafrenona3_password -e "SHOW PROCESSLIST;"

# Ver locks
docker compose exec db mysql -u lafrenona3_user -plafrenona3_password -e "SELECT * FROM information_schema.innodb_locks;"

# Matar proceso bloqueante
docker compose exec db mysql -u lafrenona3_user -plafrenona3_password -e "KILL {process_id};"
```

## Monitorización

### Health check

```bash
# Endpoint de salud
curl http://localhost:4005/up

# Estado de la aplicación
docker compose exec backend php artisan about
```

### Métricas básicas

```bash
# Contar registros por tabla
docker compose exec backend php artisan tinker
>>> \App\Models\Order::count();
>>> \App\Models\OrderItem::count();
>>> \App\Models\User::count();
>>> \App\Models\Table::count();
>>> \App\Models\PaymentTransaction::count();
>>> \App\Models\FiscalRecord::count();

# Colas pendientes
docker compose exec backend php artisan tinker
>>> Illuminate\Support\Facades\Queue::getPayloadSize();
```

### Redis

```bash
# Estado de Redis
docker compose exec redis redis-cli info

# Memoria usada
docker compose exec redis redis-cli info memory

# Clientes conectados
docker compose exec redis redis-cli info clients

# Keys count
docker compose exec redis redis-cli DBSIZE

# Purgar caché
docker compose exec backend php artisan cache:clear
```

## Mantenimiento habitual

### Diaria

```bash
# Verificar que todos los servicios están corriendo
docker compose ps

# Revisar logs de errores
docker compose logs --since=24h backend | grep -i error

# Verificar colas
docker compose exec backend php artisan queue:failed | wc -l
```

### Semanal

```bash
# Limpiar logs antiguos
docker compose exec backend php artisan log:clear

# Limpiar caché
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan route:clear
docker compose exec backend php artisan view:clear

# Verificar espacio en disco
docker system df

# Limpiar imágenes no usadas
docker image prune -f
```

### Mensual

```bash
# Actualizar imágenes Docker
docker compose pull
docker compose up -d --pull always

# Actualizar dependencias PHP
docker compose exec backend composer update --no-dev

# Actualizar dependencias frontend
cd frontend && npm update && cd ..

# Verificar backups
ls -la backups/
```

## Rotación de secretos

### APP_KEY

```bash
docker compose exec backend php artisan key:generate
docker compose restart backend
```

### Contraseñas de base de datos

```bash
# Cambiar contraseña del usuario
docker compose exec db mysql -u root -proot_password -e \
  "ALTER USER 'lafrenona3_user'@'%' IDENTIFIED BY 'nueva_password'; FLUSH PRIVILEGES;"

# Actualizar .env
# docker compose restart backend
```

### Tokens de Stripe

Actualizar en las variables de entorno del servidor y reiniciar el backend.

## Procedimiento de incidencias

### 1. Identificar el problema

```bash
# Ver estado de servicios
docker compose ps

# Ver logs recientes
docker compose logs --tail=100

# Ver health check
curl http://localhost:4005/up
```

### 2. Clasificar severidad

| Severidad | Descripción | Tiempo de respuesta |
|---|---|---|
| Crítica | Sistema caído, datos comprometidos | Inmediato |
| Alta | Funcionalidad principal afectada | 1 hora |
| Media | Funcionalidad secundaria afectada | 4 horas |
| Baja | Problema menor, workaround disponible | 24 horas |

### 3. Contener

```bash
# Si es problema de BD
docker compose restart db

# Si es problema de backend
docker compose restart backend

# Si es problema de Redis
docker compose restart redis

# Si nada funciona, reiniciar todo
docker compose down && docker compose up -d
```

### 4. Resolver

Consultar [SOLUCION_PROBLEMAS.md](SOLUCION_PROBLEMAS.md) para diagnósticos específicos.

### 5. Verificar

```bash
# Ejecutar tests críticos
docker compose exec backend php artisan test --filter=PhaseOneArchitectureTest
docker compose exec backend php artisan test --filter=PhaseTwoMultiTenancyTest

# Verificar health check
curl http://localhost:4005/up

# Verificar login
curl -X POST http://localhost:4005/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

### 6. Documentar

Registrar incidente: fecha, síntoma, causa raíz, resolución, lecciones aprendidas.
