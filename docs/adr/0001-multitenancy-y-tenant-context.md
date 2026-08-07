# ADR-0001 — Multi-tenancy y Tenant Context

- Estado: Propuesto
- Fecha: 2026-07-30
- Decisores: Equipo de arquitectura

## Contexto

El sistema es un SaaS multi-tenant para gestión de restaurantes. Cada operación debe estar estrictamente aislada por `restaurant_id`. No es aceptable que un owner, staff o cliente de un restaurante acceda, cree, modifique, borre, exporte o reciba eventos de datos de otro restaurante.

La especificación de Fase 1 crea el modelo `User` con un campo `restaurant_id` nullable y una FK a `restaurants`. La Fase 2 introduce el aislamiento con `Global Scope`, middlewares y policies. La Fase 9 necesita que el `superadmin` tenga visibilidad global, lo que requiere excluir los global scopes de forma controlada.

El modelo de identidad debe mantener `users` como identidad global, con una relación de membresía (`restaurant_memberships`) para soportar empleados que trabajan en múltiples restaurantes (requisito de "Modelo de identidad y membresías" en AGENTS.md).

## Decisión

Se adopta una estrategia de defensa en profundidad con cinco capas:

1. **TenantContext (Servicio)**: Un servicio centralizado que resuelve el tenant actual desde el usuario autenticado (`auth()->user()->restaurant_id` o `auth()->user()->currentRestaurantId()` para memberships). Nunca acepta `restaurant_id` de la petición del cliente.

2. **Global Scope (Modelos multi-tenant)**: Todos los modelos que contienen datos operativos (`Restaurant`, `Category`, `Product`, `Table`, `Order`, `OrderItem`, `Subscription`, `TenantDesign`, `AuditLog`, `Ingredient`, `InventoryAdjustment`, `Reservation`, `CashSession`, `FiscalRecord`, `PaymentTransaction`) aplican un `TenantScope` que filtra automáticamente por `restaurant_id` en todas las consultas `SELECT`, `UPDATE` y `DELETE`.

3. **Policies (Autorización)**: Cada modelo tiene un policy que verifica que el usuario tiene membresía activa en el restaurante del recurso antes de permitir cualquier operación.

4. **Middleware (Control de acceso)**: Un middleware `EnsureTenantContext` se aplica a todas las rutas protegidas que requieren tenant. Resuelve y valida el tenant antes de pasar al controlador.

5. **Restricciones SQL (Integridad)**: Índices compuestos `unique(restaurant_id, ...)` en tablas multi-tenant, foreign keys con `ON DELETE RESTRICT` o `CASCADE` según semántica de negocio, y restricciones `CHECK` para estados válidos.

La resolución del tenant es:
- `superadmin`: Puede operar sin tenant scope (explicítamente autorizado).
- `owner`/`staff`: `restaurant_id` se resuelve desde su membresía activa.
- `client` (sesión QR): `restaurant_id` se resuelve desde la `Table` asociada al `session_token`.

`withoutGlobalScopes()` queda prohibido en toda la aplicación salvo en servicios internos de superadmin explícitamente autorizados y cubiertos por test.

## Alternativas consideradas

| Alternativa | Ventajas | Riesgos o motivos de descarte |
|---|---|---|
| Subdominios por tenant (tenant.app.com) | Aislamiento a nivel DNS | Complejidad operativa alta; no necesario para un SaaS B2B con login por API; dificultad para compartir contenido público (carta del cliente) |
| `database_per_tenant` | Aislamiento físico total | Coste de infraestructura prohibitivo; no escalable para SaaS; imposibilita reporting cruzado |
| Solo policies sin global scope | Simplicidad | Riesgo de fugas si un desarrollador olvida aplicar el policy; no protege contra consultas directas a Eloquent sin policy |
| `restaurant_id` desde el cliente | Simplicidad de implementación | **Riesgo crítico de seguridad**: el cliente puede falsificar `restaurant_id` para acceder a datos de otros tenants |

## Consecuencias

### Positivas
- Aislamiento garantizado a múltiples niveles: si una capa falla, las demás protegen.
- Los desarrolladores no necesitan pensar en el aislamiento: los global scopes aplican automáticamente.
- El superadmin tiene visibilidad global de forma controlada y auditada.
- Las restricciones SQL protegen incluso contra consultas directas a la base de datos.

### Costes y riesgos
- Mayor complejidad inicial de configuración.
- Los queries de superadmin requieren `withoutGlobalScopes()` explícito y auditado.
- Los tests deben crear datos multi-tenant (mínimo dos tenants) para verificar aislamiento.
- El modelo de memberships añade complejidad al modelo de usuario.

## Reglas de seguridad e integridad

- Nunca confiar en `restaurant_id` enviado por el cliente en cualquier parámetro, body o header.
- Todo modelo multi-tenant debe aplicar `TenantScope` automáticamente.
- Todo job, listener, comando, evento y exportación debe recibir y propagar `restaurant_id` de forma explícita como parámetro o contexto.
- `withoutGlobalScopes()` solo en servicios de superadmin explícitamente autorizados, documentados y cubiertos por test.
- Todos los endpoints que operen datos multi-tenant deben pasar por el middleware `EnsureTenantContext`.
- Las migraciones deben incluir índices compuestos que comiencen por `restaurant_id` y unicidades por tenant.

## Estrategia de pruebas

- **Unitarias**: Verificar que `TenantScope` filtra correctamente por `restaurant_id`. Verificar que `TenantContext::resolve()` devuelve el tenant correcto según tipo de usuario.
- **Integración**: Crear dos tenants con datos. Verificar que un usuario de Tenant A no puede listar, crear, leer, actualizar o borrar recursos de Tenant B en ninguna ruta.
- **Seguridad**: Intentar acceder a recursos de otro tenant pasando `restaurant_id` en body, query params y headers. Respuesta esperada: 403 o 404 sin filtrado de metadatos.
- **Concurrencia**: Dos usuarios de tenants diferentes operando simultáneamente sobre recursos del mismo tipo.
- **Regresión**: Verificar que la adición de nuevos modelos multi-tenant incluye automáticamente el scope.

## Impacto por fases

| Fases | Impacto |
|---|---|
| Fase 1 | Estructura base: `users.restaurant_id`, tabla `restaurants`, FK. |
| Fase 2 | Implementación principal: `TenantScope`, policies, middleware `EnsureTenantContext`, `TenantContext`. |
| Fase 3 | CRUDs de owner aplican scope y policies. |
| Fase 4 | Eventos WebSocket incluyen `restaurant_id` y se emiten solo en canales scoped por tenant. |
| Fase 5-18 | Todas las fases heredan el aislamiento; cada nueva tabla multi-tenant aplica scope. |
| Fase 9 | SuperAdmin usa `withoutGlobalScopes()` de forma controlada y auditada. |
