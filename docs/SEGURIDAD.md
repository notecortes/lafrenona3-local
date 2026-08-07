# Seguridad — lafrenona3

## Multi-tenancy

### Aislamiento por defecto

Todos los modelos operativos aplican `TenantScope` vía el trait `BelongsToTenant`:

```php
class Category extends Model {
    use HasFactory, BelongsToTenant; // Añade Global Scope automáticamente
}
```

El scope filtra todas las consultas por `restaurant_id` del contexto actual.

### Capas de defensa

1. **TenantContext Service**: Resuelve `restaurant_id` desde el usuario autenticado.
2. **Global Scope**: Filtra automáticamente todas las queries.
3. **Middleware `EnsureTenantContext`**: Valida usuario y aplica contexto.
4. **Validación en controladores**: Verifica `user->restaurant_id === resource->restaurant_id`.
5. **Restricciones SQL**: Índices únicos por tenant, foreign keys.

### Reglas

- Nunca confiar en `restaurant_id` enviado por el cliente.
- `withoutGlobalScopes()` solo en servicios de superadmin explícitamente autorizados.
- Todo job, listener, comando debe propagar `restaurant_id` explícitamente.

### Pruebas de aislamiento

```bash
# Verificar que Tenant A no accede a datos de Tenant B
docker compose exec backend php artisan test --filter=PhaseTwoMultiTenancyTest
```

## Autenticación y autorización

### Sanction API Tokens

- Tokens de larga duración para SPA (stateful).
- `hasApiTokens()` en User model.
- Logout elimina el token activo.

### Roles

| Rol | Descripción |
|---|---|
| `superadmin` | Administrador de plataforma |
| `owner` | Propietario de restaurante |
| `waiter` | Camarero/sala |
| `kitchen` | Cocina |
| `bar` | Barra |
| `suspended` | Desactivado |

### Middlewares de seguridad

| Middleware | Ruta | Función |
|---|---|---|
| `auth:sanctum` | Todas las protegidas | Verifica token válido |
| `tenant.context` | Owner, Staff | Resuelve y valida tenant |
| `check.owner.restaurant` | Owner, Staff | Verifica ownership |
| `check.subscription` | Owner, Staff | Verifica suscripción activa |
| `superadmin` | SuperAdmin | Verifica rol superadmin |

### Protección contra IDOR/BOLA

- Cada controlador verifica que el recurso pertenece al restaurant del usuario.
- No se devuelve información de recursos de otros tenants.
- Mensajes de error genéricos (no revelan existencia de recursos).

## Sesiones QR y cliente público

### Tokens

- `secret_token`: Token de 64 bytes hexadecimales (128 bits de entropía) generado automáticamente al crear mesa.
- `session_token`: Token de sesión rotado al iniciar sesión del cliente.

### Protección

- Expiración implícita: la sesión se invalida al cerrar la mesa.
- Rate limiting: 100 requests/minuto por IP en rutas de cliente.
- Validación de `session_token` en cada operación.
- No se exponen datos de otros clientes, pedidos previos ni secretos.

### Rotación de sesión

```php
// Al abrir mesa con token QR
$table->update([
    'status' => 'occupied',
    'session_token' => Str::random(64), // Nuevo token
    'seated_at' => now(),
]);
```

## Rate Limiting

| Named Limiter | Límite | Base |
|---|---|---|
| `auth_login` | 10/min | email o IP |
| `client_routes` | 100/min | IP |
| `default` | 60/min | user.id o IP |
| `offline_sync` | 60/min | user.id o IP |
| `superadmin` | 120/min | user.id o IP |

Configurados en `AppServiceProvider::boot()`.

## Gestión de secretos

### Reglas

- `.env` está en `.gitignore`. Nunca commitear.
- `.env.example` no contiene secretos reales.
- APP_KEY se genera con `php artisan key:generate`.
- No exponer passwords, tokens, claves API en logs o respuestas.

### Rotación

```bash
# Rotar APP_KEY
docker compose exec backend php artisan key:generate

# Rotar contraseñas de BD
docker compose exec db mysql -u root -proot_password -e "ALTER USER 'lafrenona3_user'@'%' IDENTIFIED BY 'nueva_password'; FLUSH PRIVILEGES;"

# Rotar tokens de Stripe
# Configurar nueva clave en variables de entorno del servidor
```

## Logs y privacidad

### Lo que NO se registra

- Passwords (hash bcrypt).
- Tokens de API completos (solo prefix si se configura).
- Datos completos de tarjetas de crédito (Stripe los maneja externamente).
- Payloads completos de eventos de Stripe.
- PII innecesaria.

### Lo que SÍ se registra

- Acciones de auditoría (qué usuario hizo qué en qué recurso).
- Errores de validación y autenticación.
- Eventos de seguridad (intentos fallidos, accesos no autorizados).
- `request_id`, `tenant_id`, `order_id` para trazabilidad.

### Configuración actual

```
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug  # Cambiar a info en producción
```

## Seguridad de pagos y webhooks

### Stripe Webhooks

El `StripeWebhookController` implementa:

1. **Verificación de firma**: `Stripe\Webhook::constructEvent()` con `Stripe-Signature`.
2. **Deduplicación**: Por `provider_payment_id` y `webhook_event_id`.
3. **Transacciones ACID**: Actualización de `PaymentTransaction` dentro de `DB::transaction()`.
4. **Manejo de errores**: Log de errores sin exponer detalles.

### Transacciones de pago

Modelo `PaymentTransaction` con estados:
- `pending` -> `confirmed` / `failed` / `cancelled` / `refunded` / `partially_refunded`
- Append-only: no se editan ni borran.
- Importes en céntimos (`amount_cents`, `tip_cents`).

### Reglas

- El cliente nunca decide el importe final.
- Una mesa solo se libera tras pago confirmado o acción manual auditada.
- El fallo de email no deshace un cobro confirmado.

## Auditoría

### AuditLog

Cada operación crítica se registra en `audit_logs`:

| Campo | Descripción |
|---|---|
| `user_id` | Usuario que realizó la acción |
| `action` | Tipo de acción (ej: `category_updated`) |
| `subject_type` | Modelo afectado |
| `subject_id` | ID del recurso |
| `old_values` | Valores anteriores (JSON) |
| `new_values` | Valores nuevos (JSON) |
| `ip_address` | IP del cliente |
| `user_agent` | User agent |

### Servicio AuditLogger

```php
app(AuditLogger::class)->log(
    action: 'category_updated',
    subjectType: 'Category',
    subjectId: $category->id,
    oldValues: [...],
    newValues: [...],
    userId: $user->id,
    restaurantId: $category->restaurant_id
);
```

## Dependencias y actualizaciones

### Backend (PHP)

```bash
# Verificar dependencias con vulnerabilidades
docker compose exec backend composer audit

# Actualizar dependencias
docker compose exec backend composer update
```

### Frontend

```bash
cd frontend

# Verificar vulnerabilidades
npm audit

# Actualizar dependencias
npm update
```

### Docker

```bash
# Actualizar imágenes
docker compose pull
docker compose up -d --pull always
```

## Checklist de seguridad preproducción

- [ ] APP_DEBUG=false
- [ ] SESSION_ENCRYPT=true
- [ ] HTTPS/TLS configurado
- [ ] CORS restringido a dominios conocidos
- [ ] Rate limiting ajustado
- [ ] Secretos en variables de entorno (no en .env)
- [ ] .env fuera de Git
- [ ] Dependencias actualizadas y sin vulnerabilidades conocidas
- [ ] APP_KEY generada y segura
- [ ] Contraseñas de BD robustas
- [ ] Redis sin contraseña en red pública
- [ ] MySQL solo accesible desde backend
- [ ] Logs sin PII ni secretos
- [ ] Webhooks de Stripe verificados
- [ ] Multi-tenancy verificada en entorno real
- [ ] Backup automatizado y cifrado
- [ ] Monitorización de errores configurada
- [ ] Plan de respuesta a incidentes

## Riesgos conocidos y pendientes

| Riesgo | Severidad | Estado |
|---|---|---|
| No hay sistema de feature flags | Media | Pendiente |
| No hay CORS configurado | Alta | Pendiente |
| No hay CI/CD con análisis de seguridad | Media | Pendiente |
| No hay test de penetración | Alta | Pendiente |
| No hay rate limiting por endpoint individual | Media | Pendiente |
| Modelos de datos no usan `price_cents` (enteros) | Media | Pendiente (ADR-0003) |
| Outbox pattern no implementado | Media | Pendiente (ADR-0002) |
| No hay verificación de integridad de backups | Baja | Pendiente |
| No hay rotación automática de logs | Baja | Pendiente |
| Modelo de membresías no implementado (solo restaurant_id en User) | Media | Pendiente (AGENTS.md) |

## Cumplimiento normativo

**No se afirma cumplimiento de**:
- RGPD (protección de datos)
- PCI DSS (pagos)
- Veri*Factu / TicketBAI / SII (fiscalidad)
- Ninguna normativa oficial

El módulo fiscal es un registro interno antifraude con hash chaining. Se requiere revisión legal profesional para cualquier afirmación de cumplimiento normativo.
