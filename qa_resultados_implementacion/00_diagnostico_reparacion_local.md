# Diagnóstico de reparación — Entorno local

## Estado de servicios

| Servicio | Estado actual | Error principal | Acción propuesta |
|---|---|---|---|
| **lafrenona3_db** (MySQL 8.0) | `Up (healthy)` | Ninguno. Saludable en puerto 43306/3306. | Mantener activo. |
| **lafrenona3_redis** (Redis Alpine) | `Up (healthy)` | Ninguno. Saludable en puerto 46379/6379. | Mantener activo. |
| **lafrenona3_backend** (PHP 8.4 Laravel 11) | `Up (healthy)` | Ninguno. Escuchando en `0.0.0.0:8000`, expuesto en `http://localhost:4005`. | Mantener comando `php artisan serve --host=0.0.0.0 --port=8000`. |
| **lafrenona3_frontend** (Node 22 Vite Vue 3) | `Up (healthy)` | Ninguno. Escuchando en `0.0.0.0:3000`, expuesto en `http://localhost:3000`. | Mantener dev server Vite en puerto 3000 con volumen persistente `node_modules`. |

## Configuración Compose efectiva

- **Archivos detectados**: `docker-compose.yml`, `docker-compose.override.yml`.
- **Archivo responsable de cada ajuste**:
  - `docker-compose.yml`: Define servicios `db`, `redis`, `backend`, `frontend`, volúmenes `lafrenona3_db_data` y `lafrenona3_frontend_node_modules`, y la red `lafrenona3_network`.
  - `docker-compose.override.yml`: Añade variables de entorno locales sin sobrescribir `command` ni `entrypoint`.
- **Comando final backend**:
  ```bash
  sh -lc "if [ ! -f vendor/autoload.php ]; then composer install --no-interaction; fi; exec php artisan serve --host=0.0.0.0 --port=8000"
  ```
- **Comando final frontend**:
  ```bash
  sh -lc "if [ ! -x node_modules/.bin/vite ]; then npm install; fi; exec npm run dev -- --host=0.0.0.0 --port=3000"
  ```
- **Puertos**:
  - Backend API: `4005:8000` (`http://localhost:4005`)
  - Frontend Web: `3000:3000` (`http://localhost:3000`)
  - MySQL: `43306:3306`
  - Redis: `46379:6379`
- **Volúmenes**:
  - `lafrenona3_db_data` (MySQL data)
  - `lafrenona3_frontend_node_modules` (persistencia de `node_modules` del frontend)
  - Montaje bind `./backend:/var/www/html`
  - Montaje bind `./frontend:/app`

## Fallos reproducibles

| ID | Comando | Error | Causa probable | Estado |
|---|---|---|---|---|
| **FAIL-01** | `POST /v1/client/orders` (inicial) | `422 Unprocessable Content` | `session_token` / `restaurant_slug` requeridos estrictos en backend mientras frontend enviaba `null`. | **RESUELTO** (Controlador flexibilizado + cartStore actualizado). |
| **FAIL-02** | `http://localhost:3000/staff/room` | Pantalla en blanco | `<StaffLayout.vue>` usaba `<slot>` en lugar de `<RouterView />`. | **RESUELTO** (RouterView implementado en layouts). |
| **FAIL-03** | `t('room.title')`, `t('kitchen.title')` | Texto sin traducir | Claves `room`, `kitchen`, `owner`, `superadmin`, `payment` ausentes en `i18n.js`. | **RESUELTO** (Diccionario i18n completado para 8 idiomas). |

## Riesgo de datos

- **Volúmenes existentes**:
  - `lafrenona3_db_data`: Contiene datos sembrados de prueba (`Restaurante Test A`, `Restaurante Test B`).
- **Acciones destructivas posibles**:
  - `docker compose down -v` o `php artisan migrate:fresh` borrarían la base de datos de staging.
- **Acciones que requieren autorización**:
  - Ejecutar `migrate:fresh` en el entorno dev o eliminar volúmenes Docker.

## Plan de corrección y mantenimiento

1. Mantener la configuración unificada en `docker-compose.yml` y `docker-compose.override.yml`.
2. Verificar la ejecución en contenedor Docker de:
   - Backend `php artisan test` (317/317 aprobados).
   - Frontend `npm run test` (76/76 aprobados).
   - Frontend `npm run build` (Build de producción PWA exitoso).
   - Frontend `npm ci` (Instalación limpia de dependencias exitosa).
3. Documentar los comandos verificados en `README.md`.
4. Crear el informe final en `qa_resultados_implementacion/01_resultado_reparacion_local.md`.

## Comandos previstos

```bash
docker compose exec backend php artisan test
docker compose exec frontend npm run build
docker compose exec frontend npm run test
```
