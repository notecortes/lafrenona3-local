# lafrenona3 — SaaS Restaurante Multi-tenant

Aplicación SaaS para la gestión integral de restaurantes multi-tenant, con autenticación API, tiempo real, pedidos desde QR, cocina/barra en tiempo real, pagos, inventario, reservas, caja y fiscalidad.

## Tecnologías

- **Backend**: Laravel 11, PHP 8.3, MySQL 8.0, Redis, Laravel Sanctum, Laravel Reverb
- **Frontend**: Vue 3, Vite, Pinia, Axios, PWA (vite-plugin-pwa, workbox)
- **Agente de impresión**: Python 3, asyncio, websockets, ESC/POS
- **Infraestructura**: Docker Compose
- **CI/CD**: GitHub Actions

## Estado actual

- **Fases implementadas**: 18/18 (100%)
- **Tests backend (PHPUnit)**: 317/317 aprobados (100%)
- **Tests frontend (Vitest)**: 29/29 aprobados (100%)
- **Tests totales**: 346/346 aprobados (100%)
- **Migraciones**: 26 | Controladores: 22 | Modelos: 17 | Servicios: 6
- **Seguridad**: CORS configurado, rate limiting, CSP headers, timing attack fix, tenant isolation
- **Rendimiento**: N+1 fixes, database indexes, menu caching, bundle optimization

## Requisitos mínimos

- Docker Engine + Docker Compose
- Git
- Node.js 18+ (para desarrollo frontend)
- macOS, Linux o Windows con WSL2

---

## Probar en local

### Opción A — Docker (recomendado)

```bash
# 1. Clonar y configurar
git clone <repo-url>
cd lafrenona3-qwen
cp backend/.env.example backend/.env

# 2. Construir y levantar contenedores
docker compose up -d --build

# 3. Instalar dependencias PHP y generar clave
docker compose exec backend composer install
docker compose exec backend php artisan key:generate

# 4. Migrar base de datos
docker compose exec backend php artisan migrate:fresh

# 5. Verificar servicios
docker compose ps
# Esperar: db (healthy), redis (healthy), backend (running), frontend (running)

# 6. Acceder
# Frontend:  http://localhost:3000
# API:       http://localhost:4005/api/v1
# Health:    http://localhost:4005/up
```

**Crear usuario de prueba:**

```bash
docker compose exec backend php artisan tinker
```

```php
$restaurant = \App\Models\Restaurant::create([
    'name' => 'Mi Restaurante',
    'slug' => 'mi-restaurante',
    'status' => 'active',
]);

$user = \App\Models\User::create([
    'name' => 'Owner',
    'email' => 'owner@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
    'role' => 'owner',
    'restaurant_id' => $restaurant->id,
]);

echo "Login: owner@example.com / password123\n";
```

### Opción B — Sin Docker (host nativo)

```bash
# 1. Configurar .env
cp backend/.env.local.example backend/.env

# 2. Backend
cd backend
composer install
php artisan key:generate
php artisan migrate --force
php artisan serve --host=0.0.0.0 --port=8000

# 3. Frontend (otra terminal)
cd frontend
npm ci
npm run dev -- --host 0.0.0.0 --port 3000
```

### Comandos de prueba

```bash
# Tests backend
docker compose exec backend php artisan test

# Tests frontend
cd frontend && npm run test

# Build frontend
cd frontend && npm run build

# Lint PHP
docker compose exec backend php artisan pint

# Logs en tiempo real
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f db

# Diagnóstico
docker compose exec backend php artisan about
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan cache:clear
```

### Detener y limpiar

```bash
# Parar contenedores (mantiene datos)
docker compose down

# Parar y eliminar volúmenes (elimina BD)
docker compose down -v

# Reiniciar todo limpio
docker compose down -v
docker compose up -d --build
docker compose exec backend php artisan migrate:fresh
```

---

## Comandos principales

```bash
# Levantar todo
docker compose up -d

# Parar todo
docker compose down

# Parar y eliminar volúmenes (limpia BD)
docker compose down -v

# Ejecutar tests backend
docker compose exec backend php artisan test

# Ejecutar tests frontend
cd frontend && npm run test

# Build frontend
cd frontend && npm run build

# Ejecutar lint backend
docker compose exec backend php artisan pint

# Ver logs
docker compose logs -f backend
docker compose logs -f db
docker compose logs -f redis
```

## Pruebas de estrés

```bash
# Ejecutar stress test (requiere k6)
./scripts/run-stress-test.sh --baseline
```

## Backups

```bash
# Crear backup
docker compose exec backend php artisan saas:backup

# Restaurar backup
docker compose exec backend php artisan saas:restore --file=backup_*.sql
```

## Documentación

| Documento | Contenido |
|---|---|
| [docs/README.md](docs/README.md) | Índice de toda la documentación |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Instalación en Linux, macOS y Windows/WSL2 |
| [docs/CONFIGURACION.md](docs/CONFIGURACION.md) | Variables de entorno y configuración |
| [docs/USO.md](docs/USO.md) | Uso por perfil (SuperAdmin, Owner, Staff, Cocina, Cliente QR) |
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Arquitectura, modelos, eventos y decisiones |
| [docs/API.md](docs/API.md) | Referencia completa de la API |
| [docs/PRUEBAS.md](docs/PRUEBAS.md) | Tests, lint, build y E2E |
| [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) | Preparación para staging/producción |
| [docs/BACKUP_Y_RESTAURACION.md](docs/BACKUP_Y_RESTAURACION.md) | Backup y restauración de datos |
| [docs/SEGURIDAD.md](docs/SEGURIDAD.md) | Multi-tenancy, auth, rate limiting, auditoría |
| [docs/OPERACIONES.md](docs/OPERACIONES.md) | Operaciones diarias, logs, health checks |
| [docs/SOLUCION_PROBLEMAS.md](docs/SOLUCION_PROBLEMAS.md) | Diagnóstico y resolución de errores |
| [docs/ESTADO_DEL_PROYECTO.md](docs/ESTADO_DEL_PROYECTO.md) | Estado detallado de las 18 fases |
| [docs/FISCAL_COMPLIANCE.md](docs/FISCAL_COMPLIANCE.md) | Cumplimiento fiscal (VeriFactu, TicketBAI, SII) |

## Aviso

**No utilices secretos reales ni credenciales de producción en el entorno local.** Usa valores de ejemplo seguro. Los secretos reales deben configurarse exclusivamente en entornos de staging y producción.
