# Instalación — lafrenona3

Guía paso a paso para instalar el proyecto desde cero en Linux, macOS o Windows con WSL2.

## Requisitos del sistema

### Comunes a todas las plataformas

- **Git**: `git --version` >= 2.30
- **Docker Engine** >= 24.0 o **Docker Desktop** con Docker Compose >= 2.0
- **Node.js** >= 18.0 (para desarrollo frontend)
- **npm** >= 9.0
- **Sistema de archivos**: Soporte para volumes de Docker

### macOS

```bash
# Instalar Docker Desktop (incluye Docker Compose)
brew install --cask docker

# Instalar Git y Node.js
brew install git node

# Verificar
docker --version
node --version
npm --version
```

### Ubuntu/Debian

```bash
# Instalar Docker Engine
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Instalar Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# Añadir usuario al grupo docker (sin sudo)
sudo usermod -aG docker $USER
newgrp docker

# Verificar
docker --version
node --version
npm --version
```

### Windows con WSL2

1. Instalar WSL2 con Ubuntu:
```powershell
wsl --install -d Ubuntu
```

2. Dentro de WSL, seguir los pasos de Ubuntu/Debian.

3. Para Docker Desktop con WSL2:
   - Instalar Docker Desktop para Windows
   - Activar integración con WSL2 en Settings > Resources > WSL Integration

## Clonado del repositorio

```bash
git clone <repo-url>
cd lafrenona3-qwen
```

## Configuración inicial

### Backend

```bash
cp backend/.env.example backend/.env
```

El archivo `.env` debe contener:
- `APP_KEY` generada con `php artisan key:generate`
- Credenciales de base de datos
- `APP_DEBUG=false` en entornos no locales

### Frontend

```bash
cd frontend
npm install
cd ..
```

Dependencias principales:
- Vue 3, Vue Router, Pinia, Axios
- vite-plugin-pwa, workbox-window (PWA)
- Vitest, jsdom (tests)

## Construcción e inicio de contenedores

```bash
# Construir imágenes
docker compose build

# Levantar todos los servicios
docker compose up -d

# Verificar que todo está corriendo
docker compose ps
```

Servicios esperados:

| Servicio | Contenedor | Puerto expuesto |
|---|---|---|
| MySQL 8.0 | `lafrenona3_db` | 43306:3306 |
| Redis | `lafrenona3_redis` | 46379:6379 |
| Backend (PHP 8.3) | `lafrenona3_backend` | 4005:8000 |

## Instalación de dependencias

### Backend (dentro del contenedor)

```bash
docker compose exec backend composer install
docker compose exec backend npm install --ignore-scripts
docker compose exec backend npm run build
```

### Frontend (fuera del contenedor, en el host)

```bash
cd frontend
npm ci
# o
npm install
cd ..
```

## Generación de claves

```bash
docker compose exec backend php artisan key:generate
```

## Migraciones y seeders

```bash
# Aplicar todas las migraciones (incluye indexes)
docker compose exec backend php artisan migrate:fresh

# Si existen seeders
docker compose exec backend php artisan db:seed
```

El proyecto tiene 26 migraciones en `backend/database/migrations/`, incluyendo:
- Migraciones base (users, restaurants, tenants, etc.)
- CRUD (categories, products, tables, staff)
- Pedidos y order items
- Pagos y transacciones
- Reservas, inventario, caja, fiscalidad
- Session tokens para mesas (fase 6)
- Campos de disponibilidad en productos (fase 6)
- **Indexes de rendimiento** (fase de optimización)

## URLs locales después de la instalación

| Servicio | URL |
|---|---|
| Frontend (Vue) | http://localhost:3000 |
| Backend API | http://localhost:4005/api/v1 |
| Backend (puerto PHP) | http://localhost:4005 |
| MySQL (host) | localhost:43306 |
| Redis (host) | localhost:46379 |
| Health check | http://localhost:4005/up |

## Ejecutar tests

### Backend

```bash
docker compose exec backend php artisan test
```

Resultado esperado: **317/317 tests passing**

### Frontend

```bash
cd frontend && npm run test
```

Resultado esperado: **29/29 tests passing**

### Build frontend

```bash
cd frontend && npm run build
```

Resultado esperado: Build exitoso con PWA service worker generado.

## Crear usuario de prueba

```bash
docker compose exec backend php artisan tinker
```

```php
// Crear restaurante y owner
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

## Detener, reiniciar y limpiar

```bash
# Detener contenedores (mantiene datos)
docker compose down

# Detener y eliminar volúmenes (elimina BD)
docker compose down -v

# Detener, eliminar contenedores y volúmenes
docker compose down --remove-orphans -v

# Reiniciar todo
docker compose down -v
docker compose up -d --build
docker compose exec backend php artisan migrate:fresh
```

## Staging

```bash
# Levantar entorno staging
docker compose -f docker-compose.staging.yml up -d --build

# Verificar health
./scripts/health-check.sh

# Sembrar datos de prueba
docker compose -f docker-compose.staging.yml exec backend php artisan db:seed --class=StagingDatabaseSeeder
```

## Instalación en otro equipo

1. Clonar el repositorio.
2. Copiar `.env` desde el ejemplo.
3. Ejecutar `docker compose up -d --build`.
4. Ejecutar migraciones.
5. Instalar dependencias frontend: `cd frontend && npm install`.
6. Generar APP_KEY: `docker compose exec backend php artisan key:generate`.
7. Crear usuario de prueba.

Los datos de la base de vida en el volume `lafrenona3_db_data`. Para migrar datos entre máquinas, exportar/importar la base de datos (ver [BACKUP_Y_RESTAURACION.md](BACKUP_Y_RESTAURACION.md)).

## Notas por plataforma

### macOS con Apple Silicon

- Docker Desktop funciona nativamente con ARM64.
- Las imágenes MySQL 8.0 y Redis Alpine son compatibles.

### Windows WSL2

- Asegurar que Docker Desktop tiene habilitada la integración WSL2.
- Ejecutar todos los comandos Docker desde la terminal WSL.
- Los puertos expuestos (43306, 46379, 4005) son accesibles desde el host Windows.

### Linux

- Asegurar que el usuario actual está en el grupo `docker`.
- Si se usa `docker` en lugar de `docker compose`, usar `docker-compose`.
