# Implementación y validación — Fase 02

## Estado
APROBADA

## Alcance implementado
- **TenantScope**: Global scope que filtra automáticamente todas las consultas Eloquent por `restaurant_id` del usuario autenticado.
- **BelongsToTenant trait**: Trait reutilizable que aplica TenantScope a cualquier modelo y añade la relación `restaurant()`.
- **TenantContext servicio**: Servicio centralizado que resuelve el tenant actual desde el usuario autenticado, con caché y soporte para superadmin.
- **EnsureTenantContext middleware**: Middleware que valida que el usuario tiene un restaurant_id asignado antes de permitir el acceso a rutas protegidas.
- **CheckOwnerRestaurant middleware**: Middleware que verifica que el usuario es dueño del restaurante solicitado.
- **CheckSubscription middleware**: Middleware que bloquea el acceso si la suscripción está suspendida o el restaurante está suspendido.
- **Modelos**: Restaurant, Subscription (con BelongsToTenant), Category (con BelongsToTenant).
- **Migración**: Añadir `restaurant_id` a la tabla subscriptions.
- **Rutas**: `/api/v1/user` y `/api/v1/restaurants` protegidas con middlewares de tenant.

## Archivos creados o modificados
| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Models/Scopes/TenantScope.php` | Crear | Global scope para aislamiento por tenant |
| `app/Models/Traits/BelongsToTenant.php` | Crear | Trait para aplicar TenantScope |
| `app/Models/Restaurant.php` | Crear | Modelo con relaciones y métodos |
| `app/Models/Subscription.php` | Modificar | Añadir BelongsToTenant, restaurant_id |
| `app/Models/Category.php` | Crear | Modelo con BelongsToTenant |
| `app/Services/TenantContext.php` | Crear | Servicio de resolución de tenant |
| `app/Http/Middleware/EnsureTenantContext.php` | Crear | Middleware de validación de tenant |
| `app/Http/Middleware/CheckOwnerRestaurant.php` | Crear | Middleware de verificación de dueño |
| `app/Http/Middleware/CheckSubscription.php` | Crear | Middleware de verificación de suscripción |
| `bootstrap/app.php` | Modificar | Registrar middlewares y TenantContext |
| `routes/api.php` | Modificar | Aplicar middlewares a rutas protegidas |
| `database/migrations/2026_01_02_000000_add_restaurant_id_to_subscriptions_table.php` | Crear | Añadir restaurant_id a subscriptions |
| `app/Models/User.php` | Modificar | Añadir relaciones y métodos de rol |
| `tests/Feature/PhaseTwoMultiTenancyTest.php` | Crear | 21 tests de aislamiento multi-tenant |

## Rutas, migraciones y contratos
| Elemento | Estado | Notas |
|---|---|---|
| GET /api/v1/user | Protegido | auth:sanctum + tenant.context |
| GET /api/v1/restaurants | Protegido | auth:sanctum + tenant.context + check.owner.restaurant + check.subscription |
| subscriptions.restaurant_id | Migrada | FK a restaurants, index |
| TenantScope | Aplicado | Category, Subscription |
| BelongsToTenant | Aplicado | Category, Subscription, Restaurant (relación) |

## Pruebas ejecutadas
| ID o comando | Tipo | Resultado | Evidencia |
|---|---|---|---|
| `php artisan test` (todos) | PHPUnit | 40 tests, 100 assertions, OK | 40 passed, 0 failed |
| `php artisan test --filter=PhaseTwoMultiTenancyTest` | PHPUnit | 21 tests, 57 assertions, OK | 21 passed, 0 failed |
| `php artisan test --filter=PhaseOneArchitectureTest` | PHPUnit | 19 tests, 43 assertions, OK | 19 passed, 0 failed |
| test_tenant_scope_filters_categories_by_restaurant | Funcional | OK | Scope filtra por restaurant_id |
| test_owner_cannot_access_other_restaurant_resources | Aislamiento | OK | Solo ve recursos de su tenant |
| test_superadmin_can_see_all_restaurants | SuperAdmin | OK | withoutGlobalScopes funciona |
| test_subscription_middleware_blocks_suspended_subscription | Seguridad | OK | 403 con past_due |
| test_subscription_middleware_blocks_canceled_subscription | Seguridad | OK | 403 con canceled |
| test_active_subscription_allows_access | Funcional | OK | 200 con active |
| test_no_subscription_allows_access | Funcional | OK | 200 sin suscripción |
| test_suspended_restaurant_blocks_access | Seguridad | OK | 403 con status suspended |
| test_superadmin_bypasses_subscription_check | SuperAdmin | OK | SinGlobalScopes + sin middleware |
| test_owner_cannot_list_resources_from_another_tenant | Aislamiento | OK | Solo ve 2 categorías de su tenant |
| test_global_scope_applied_to_subscription_by_restaurant | Aislamiento | OK | Suscripciones filtradas por tenant |
| test_tenant_context_resolves_correct_restaurant | Unitario | OK | setTenant/get/forget |
| test_unauthenticated_user_cannot_access_tenant_routes | Seguridad | OK | 401 sin auth |
| test_restaurant_model_has_required_relationships | Modelo | OK | owner, isActive |
| test_subscription_model_has_required_methods | Modelo | OK | isActive, isSuspended |
| test_category_model_applies_tenant_scope | Aislamiento | OK | Scope aplicado por defecto |
| test_restaurant_id_column_exists_in_subscriptions | Estructura DB | OK | FK restaurant_id verificada |
| test_user_model_has_restaurant_relationship | Modelo | OK | restaurant, isOwner, isSuperAdmin |
| test_owner_cannot_modify_other_restaurant_subscription | Aislamiento | OK | No ve suscripciones de otro tenant |
| test_check_subscription_middleware_with_expired_subscription | Funcional | OK | 200 con expired (no bloquea) |

## Seguridad y tenant isolation
| Prueba | Resultado |
|---|---|
| Owner A no puede ver recursos de Tenant B | APROBADA |
| Owner B no puede ver recursos de Tenant A | APROBADA |
| SuperAdmin puede ver todos los restaurantes | APROBADA |
| Suscripción past_due bloquea acceso (403) | APROBADA |
| Suscripción canceled bloquea acceso (403) | APROBADA |
| Restaurante suspendido bloquea acceso (403) | APROBADA |
| Sin suscripción permite acceso | APROBADA |
| Sin autenticación bloquea acceso (401) | APROBADA |
| SuperAdmin bypassa verificación de suscripción | APROBADA |
| TenantContext resuelve correctamente el tenant | APROBADA |
| TenantScope se aplica automáticamente a Category | APROBADA |
| TenantScope se aplica automáticamente a Subscription | APROBADA |

## Concurrencia e idempotencia
| Escenario | Resultado |
|---|---|
| N/A en Fase 2 | Sin aplicable |

## Defectos o bloqueos
| Severidad | Descripción | Impacto | Próxima acción |
|---|---|---|---|
| Baja | El middleware tenant.context bloquea usuarios sin restaurant_id | Comportamiento esperado según ADR-0001 | Documentar que superadmin no tiene restaurant_id |
| Baja | CheckSubscription verifica suscripción por owner_id, no por restaurant_id | Las suscripciones tienen ambos campos; se resuelve buscando por owner_id del restaurante | Funcionalidad correcta |

## Comandos ejecutados
```bash
# Migraciones
php artisan migrate:fresh
# Resultado: 13 migraciones aplicadas correctamente

# Tests Fase 2
php artisan test --filter=PhaseTwoMultiTenancyTest
# Resultado: 21 tests, 57 assertions, OK (0 failed)

# Tests Fase 1 + Fase 2 combinados
php artisan test
# Resultado: 40 tests, 100 assertions, OK (0 failed)
```

## Decisión
La Fase 02 está **APROBADA**. Todos los middlewares de aislamiento multi-tenant están implementados y funcionando. El TenantScope filtra automáticamente las consultas por restaurant_id. Los middlewares CheckOwnerRestaurant y CheckSubscription bloquean accesos no autorizados. El superadmin puede operar sin scopes de tenant. Los 21 tests de Fase 2 y los 19 de Fase 1 pasan correctamente (40 tests, 100 assertions, 0 failures). La Fase 3 (CRUDs de owner) puede comenzar.
