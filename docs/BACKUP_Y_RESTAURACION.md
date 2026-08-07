# Backup y Restauración — lafrenona3

## Qué datos se respaldan

### Base de datos (MySQL)

| Tabla | Tipo | Frecuencia recomendada |
|---|---|---|
| `users` | Personal + acceso | Diaria |
| `restaurants` | Datos de negocio | Diaria |
| `subscriptions` | Comercial | Diaria |
| `categories` | Carta | Diaria |
| `products` | Carta | Diaria |
| `tables` | Configuración | Diaria |
| `orders` | Transacciones | Diaria (crítico) |
| `order_items` | Transacciones | Diaria (crítico) |
| `payment_transactions` | Financiero | Diaria (crítico) |
| `fiscal_records` | Fiscal (append-only) | Diaria (crítico) |
| `cash_sessions` | Financiero | Diaria (crítico) |
| `reservations` | Operativo | Semanal |
| `ingredients` | Inventario | Diaria |
| `inventory_adjustments` | Inventario | Diaria |
| `offline_operations` | Operativo | Semanal |
| `audit_logs` | Auditoría | Semanal |
| `sessions` | Sesiones | Semanal |
| `jobs` | Colas | Semanal |
| `cache` | Caché | No necesario |

### Archivos de aplicación

- `.env` (contiene secretos — respaldar con cifrado)
- `storage/` (archivos subidos, si se usa filesystem local)

### Lo que NO se respalda

- Imágenes de Docker (se reconstruyen)
- Dependencias `vendor/` y `node_modules/` (se reinstalan)
- Logs (rotados automáticamente)

## Procedimiento de backup

### Backup de base de datos

```bash
# Backup completo
docker compose exec db mysqldump \
  -u lafrenona3_user \
  -plafrenona3_password \
  lafrenona3_matrix \
  > backups/db_lafrenona3_$(date +%Y%m%d_%H%M%S).sql

# Backup con compresión
docker compose exec db mysqldump \
  -u lafrenona3_user \
  -plafrenona3_password \
  lafrenona3_matrix \
  | gzip > backups/db_lafrenona3_$(date +%Y%m%d_%H%M%S).sql.gz

# Backup solo de tablas críticas
docker compose exec db mysqldump \
  -u lafrenona3_user \
  -plafrenona3_password \
  lafrenona3_matrix \
  orders order_items payment_transactions fiscal_records cash_sessions \
  > backups/critical_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Backup del volumen Docker

```bash
# Backup del volumen de datos
docker run --rm \
  -v lafrenona3_db_data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/db_volume_$(date +%Y%m%d_%H%M%S).tar.gz -C /data .

# Restaurar desde volumen
docker run --rm \
  -v lafrenona3_db_data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar xzf /backup/db_volume_YYYYMMDD_HHMMSS.tar.gz -C /data
```

### Backup de .env

```bash
# Backup del archivo de entorno (cifrado)
cp backend/.env backups/.env_$(date +%Y%m%d_%H%M%S)
# Cifrar con GPG
gpg --symmetric --cipher-algo AES256 backups/.env_$(date +%Y%m%d_%H%M%S)
```

## Cifrado y almacenamiento

### Cifrado

Los backups deben cifrarse antes de almacenarse fuera del servidor:

```bash
# Cifrar con GPG
gpg --symmetric --cipher-algo AES256 backups/db_lafrenona3_20260801.sql.gz

# Cifrar con openssl
openssl enc -aes-256-cbc -salt -in backups/db_lafrenona3_20260801.sql.gz \
  -out backups/db_lafrenona3_20260801.sql.gz.enc -pass pass:${BACKUP_ENCRYPTION_KEY}
```

### Almacenamiento

**Estado**: No configurado automáticamente. Se requiere implementación manual.

Recomendaciones:
- Almacenar al menos 3 copias en 2 ubicaciones diferentes.
- Usar almacenamiento S3-compatible con versionado.
- Mantener copias de 7 días (diarias), 4 semanas (semanales), 12 meses (mensuales).
- Rotar copias antiguas automáticamente.

## Restauración completa

### En otro equipo

```bash
# 1. Clonar repositorio
git clone <repo-url>
cd lafrenona3-qwen

# 2. Configurar .env
cp backend/.env.example backend/.env
# Editar con valores correctos

# 3. Levantar infraestructura
docker compose up -d db redis

# 4. Esperar a que MySQL esté listo
docker compose exec db mysqladmin ping -h localhost

# 5. Restaurar base de datos
cat backups/db_lafrenona3_20260801.sql.gz | gunzip | \
  docker compose exec -T db mysql \
  -u lafrenona3_user \
  -plafrenona3_password \
  lafrenona3_matrix

# 6. Iniciar backend
docker compose up -d backend

# 7. Generar APP_KEY si no existe
docker compose exec backend php artisan key:generate

# 8. Verificar
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan test --filter=PhaseOneArchitectureTest
```

### Restauración desde volumen

```bash
# 1. Detener contenedores
docker compose down

# 2. Eliminar volumen existente
docker volume rm lafrenona3_db_data

# 3. Crear volumen nuevo y restaurar
docker volume create lafrenona3_db_data
docker run --rm \
  -v lafrenona3_db_data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar xzf /backup/db_volume_YYYYMMDD_HHMMSS.tar.gz -C /data

# 4. Levantar
docker compose up -d
```

## Verificación posterior a restauración

```bash
# 1. Verificar conexiones
docker compose ps

# 2. Verificar migraciones
docker compose exec backend php artisan migrate:status

# 3. Verificar integridad de datos
docker compose exec backend php artisan tinker
>>> \App\Models\Order::count();
>>> \App\Models\FiscalRecord::count();
>>> \App\Models\FiscalChainingService::resolve()->verifyChain(1);

# 4. Verificar cadena fiscal
docker compose exec backend php artisan tinker
>>> $service = new \App\Services\FiscalChainingService();
>>> $service->verifyChain(1);  // true = cadena intacta

# 5. Ejecutar tests
docker compose exec backend php artisan test --filter=PhaseOneArchitectureTest
docker compose exec backend php artisan test --filter=PhaseTwoMultiTenancyTest

# 6. Verificar health check
curl http://localhost:4005/up
```

## Prueba de restauración

Se recomienda realizar una prueba de restauración completa al menos una vez al mes:

1. Crear un entorno de prueba aislado.
2. Restaurar el backup más reciente.
3. Verificar integridad de datos.
4. Verificar cadena fiscal.
5. Ejecutar tests.
6. Documentar resultados.

## Limitaciones y pasos manuales pendientes

### No implementado

- **Backups automatizados**: No hay cron job ni script de backup automático.
- **Cifrado automático**: Los backups se cifran manualmente.
- **Rotación automática**: No hay política de rotación implementada.
- **Verificación automática de integridad**: No hay script de verificación post-backup.
- **Backup de archivos subidos**: No hay configuración de storage remoto.
- **Backup de Redis**: No se respalda la caché ni sesiones.

### Pasos manuales requeridos

1. Programar backups con cron o un job scheduler.
2. Implementar rotación de backups (eliminar copias > X días).
3. Configurar almacenamiento remoto (S3, rsync, etc.).
4. Implementar verificación automática post-backup.
5. Documentar procedimiento de recuperación ante desastre.
