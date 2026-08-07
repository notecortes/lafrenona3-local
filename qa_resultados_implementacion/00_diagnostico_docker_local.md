# Diagnóstico — Entorno Local Docker

## Estado
DIAGNÓSTICO COMPLETO — Se requieren correcciones antes del primer arranque

## Servicios detectados

| Servicio | Puerto host | Puerto contenedor | Estado actual |
|---|---:|---:|---|
| db (MySQL 8.0) | 43306 | 3306 | ✅ Configurado |
| redis (Redis Alpine) | 46379 | 6379 | ✅ Configurado |
| backend (PHP 8.4) | 4005 | 8000 | ⚠️ Sin frontend, .env con DB_HOST incorrecto |
| frontend (Node/Vite) | 3000 | 3000 | ❌ No existe servicio Docker |

## Versiones requeridas

| Componente | Versión requerida | Versión detectada | Estado |
|---|---|---|---|
| PHP | >= 8.4 | 8.4 (alpine) | ✅ |
| Laravel | ^13.8 | 13.23.0 | ✅ |
| Node.js | 20 LTS | 20 (alpine) | ✅ |
| Vite | ^5.x | ^8.2.1 | ⚠️ Versión muy alta, posible incompatibilidad |
| Vue | ^3.4.0 | ^3.4.0 | ✅ |
| Vitest | ^1.6.x | ^4.1.10 | ⚠️ Versión muy alta |
| @vitejs/plugin-vue | ^5.x | ^6.0.8 | ⚠️ Posible incompatibilidad |

## Fallos reproducibles

### 1. Frontend sin servicio Docker
El docker-compose.yml no incluye un servicio `frontend`. No se puede ejecutar Vite en contenedor.

### 2. Vite proxy apunta a puerto incorrecto
`vite.config.js` tiene `target: 'http://localhost:8000'` pero el backend se expone en `4005`.

### 3. __dirname deprecated en Vite
`vite.config.js` usa `__dirname` que está deprecated en Vite 5+.

### 4. .env.example con valores host incorrectos
- `DB_HOST=127.0.0.1` → debe ser `db` para Docker
- `REDIS_HOST=127.0.0.1` → debe ser `redis` para Docker
- `APP_CORS_ORIGINS` vacío → debe incluir `http://localhost:3000`

### 5. CORS sin soporte de credenciales
`supports_credentials` está en `false` pero Sanctum necesita cookies.

### 6. Backend sin variables de entorno en docker-compose
El servicio backend no tiene `environment:` definido, depende de `.env` montado.

### 7. Vite version mismatch
`package.json` tiene `vite: ^8.2.1` pero Vite 8 aún no existe (la última stable es 5.x).
Esto causará fallo en `npm install`.

### 8. Vitest version mismatch
`package.json` tiene `vitest: ^4.1.10` pero la versión estable actual es 1.x/2.x.

## Riesgos de seguridad

| Riesgo | Severidad | Detalle |
|---|---|---|
| MYSQL_ROOT_PASSWORD en compose | Media | Contraseña por defecto `root_password` |
| DB credentials en .env.example | Baja | Usuario/contraseña visibles en repo |
| APP_KEY vacío | Alta | Se debe generar antes de usar |
| CORS `allowed_headers: ['*']` | Media | Permite todos los headers |

## Volúmenes y datos

| Volumen | Impacto si se borra |
|---|---|
| `lafrenona3_db_data` | **PERDIDA TOTAL** de base de datos |

**NO se deben borrar volúmenes sin aprobación explícita.**

## Plan de corrección

1. ✅ Añadir servicio frontend a docker-compose.yml
2. ✅ Crear frontend/Dockerfile
3. ✅ Corregir vite.config.js (proxy, __dirname)
4. ✅ Corregir backend/.env.example (DB_HOST, REDIS_HOST, CORS)
5. ✅ Actualizar backend/Dockerfile (agregar gd)
6. ✅ Crear .env.local.example para desarrollo host
7. ✅ Actualizar CORS (supports_credentials = true)
8. ⚠️ Corregir versiones de Vite/Vitest en package.json
9. ⚠️ Verificar migraciones de cache/sessions
10. ⚠️ Ejecutar docker compose up -d --build
11. ⚠️ Ejecutar tests en Docker

## Archivos a modificar

| Archivo | Cambio |
|---|---|
| `docker-compose.yml` | Añadir servicio frontend, environment para backend |
| `frontend/Dockerfile` | Crear nuevo |
| `frontend/vite.config.js` | Corregir proxy y __dirname |
| `frontend/package.json` | Corregir vite y vitest versions |
| `frontend/src/services/echo.js` | Crear (ya creado) |
| `backend/.env.example` | Corregir DB_HOST, REDIS_HOST, CORS |
| `backend/.env.local.example` | Crear para desarrollo host |
| `backend/Dockerfile` | Agregar extensión gd |
| `backend/config/cors.php` | supports_credentials = true |
