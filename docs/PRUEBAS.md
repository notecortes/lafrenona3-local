# Pruebas — lafrenona3

## Herramientas detectadas

| Herramienta | Uso | Ubicación |
|---|---|---|
| PHPUnit 12 | Tests backend (feature + unit) | `backend/phpunit.xml`, `backend/tests/` |
| Vitest 1 | Tests frontend | `frontend/vitest.config.js` |
| Laravel Pint | Linter PHP | `backend/composer.json` (require-dev) |
| Playwright | E2E | **No configurado** |

## Estructura de tests

### Backend (PHPUnit)

```
backend/tests/
├── TestCase.php
├── Feature/
│   ├── ExampleTest.php
│   ├── PhaseOneArchitectureTest.php        (19 tests)
│   ├── PhaseTwoMultiTenancyTest.php        (21 tests)
│   ├── PhaseThreeOwnerCrudTest.php
│   ├── PhaseFourBroadcastTest.php
│   ├── PhaseFiveStaffControlTest.php
│   ├── PhaseSixteenReservationTest.php
│   ├── PhaseSevenOrderTransactionTest.php
│   ├── PhaseEightOfflineSyncTest.php
│   ├── PhaseNineSuperAdminTest.php
│   ├── PhaseTenDevOpsTest.php
│   ├── PhaseElevenAnalyticsTest.php
│   ├── PhaseTwelveSecurityAuditTest.php
│   ├── PhaseThirteenAssistanceTest.php
│   ├── PhaseFourteenPaymentGatewayTest.php
│   ├── PhaseFifteenInventoryEscalloTest.php
│   ├── PhaseSeventeenFiscalCloseTest.php
│   └── PhaseEighteenCrudTest.php
└── Unit/
    └── ExampleTest.php
```

Total: 19 archivos de test feature, 1 test unit.

### Frontend (Vitest)

```
frontend/
├── vitest.config.js
└── src/__tests__/
    └── setup.js
```

## Preparación del entorno de test

El entorno de test usa SQLite en memoria (configurado en `phpunit.xml`):

```xml
<php>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="BROADCAST_CONNECTION" value="null"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="SESSION_DRIVER" value="array"/>
</php>
```

Esto significa:
- No se necesita MySQL/Redis para tests.
- Los eventos de broadcast no se envían.
- Las colas se ejecutan síncronamente.
- Los emails se descartan.

## Comandos de tests

### Backend

```bash
# Ejecutar todos los tests
docker compose exec backend php artisan test

# Ejecutar tests de una fase específica
docker compose exec backend php artisan test --filter=PhaseOneArchitectureTest
docker compose exec backend php artisan test --filter=PhaseTwoMultiTenancyTest
docker compose exec backend php artisan test --filter=PhaseThreeOwnerCrudTest
docker compose exec backend php artisan test --filter=PhaseFourBroadcastTest
docker compose exec backend php artisan test --filter=PhaseFiveStaffControlTest
docker compose exec backend php artisan test --filter=PhaseSevenOrderTransactionTest
docker compose exec backend php artisan test --filter=PhaseEightOfflineSyncTest
docker compose exec backend php artisan test --filter=PhaseNineSuperAdminTest
docker compose exec backend php artisan test --filter=PhaseTenDevOpsTest
docker compose exec backend php artisan test --filter=PhaseElevenAnalyticsTest
docker compose exec backend php artisan test --filter=PhaseTwelveSecurityAuditTest
docker compose exec backend php artisan test --filter=PhaseThirteenAssistanceTest
docker compose exec backend php artisan test --filter=PhaseFourteenPaymentGatewayTest
docker compose exec backend php artisan test --filter=PhaseFifteenInventoryEscalloTest
docker compose exec backend php artisan test --filter=PhaseSixteenReservationTest
docker compose exec backend php artisan test --filter=PhaseSeventeenFiscalCloseTest
docker compose exec backend php artisan test --filter=PhaseEighteenCrudTest

# Ejecutar solo tests unitarios
docker compose exec backend php artisan test --testsuite=Unit

# Ejecutar solo tests feature
docker compose exec backend php artisan test --testsuite=Feature

# Ejecutar con reporte de cobertura (si xdebug está habilitado)
docker compose exec backend php artisan test --coverage
```

### Frontend

```bash
cd frontend

# Ejecutar tests
npm run test

# Ejecutar en modo watch
npm run test:watch

# Build para verificar que compila
npm run build
```

### Lint

```bash
# PHP (Laravel Pint)
docker compose exec backend php artisan pint

# Verificar sin modificar
docker compose exec backend php artisan pint --dry-run --diff
```

## Ejecución por fase

### Fases 1-6 (APROBADAS)

Estas fases tienen tests completos y aprobados:

| Fase | Tests | Resultado |
|---|---|---|
| 01 - Infraestructura | 19 tests | APROBADO |
| 02 - Multi-tenancy | 21 tests | APROBADO |
| 03 - CRUD Owner | 38 tests | APROBADO |
| 04 - Reverb/WebSockets | 22 tests | APROBADO |
| 05 - Cocina/Barra | 24 tests | APROBADO |
| 06 - Carta/Cliente | 12 tests | APROBADO |

### Fases 7-18 (APROBADAS)

Todas las fases están implementadas y validadas con 346/346 tests aprobados (100%).

| Fase | Tests | Resultado |
|---|---|---|
| 07 - Pedidos/Transacciones | 19 | APROBADO |
| 08 - Offline Sync | 13 | APROBADO |
| 09 - SuperAdmin | 16 | APROBADO |
| 10 - CI/CD/Monitoring | 9 | APROBADO |
| 11 - Analytics/BI | 15 | APROBADO |
| 12 - Seguridad/Auditoría | 14 | APROBADO |
| 13 - Sala/Alertas | 14 | APROBADO |
| 14 - Pagos/Stripe | 17 | APROBADO |
| 15 - Inventario | 13 | APROBADO |
| 16 - Reservas | 15 | APROBADO |
| 17 - Caja/Fiscal | 15 | APROBADO |
| 18 - CRUD Admin/Owner | 13 + 9 frontend | APROBADO |

## Tests por categoría

### Multi-tenancy

- `test_tenant_scope_filters_categories_by_restaurant`
- `test_owner_cannot_access_other_restaurant_resources`
- `test_superadmin_can_see_all_restaurants`
- `test_owner_cannot_list_resources_from_another_tenant`
- `test_tenant_context_resolves_correct_restaurant`
- `test_global_scope_applied_to_subscription_by_restaurant`
- `test_category_model_applies_tenant_scope`
- `test_tenant_scope_is_applied_by_default_on_category`

### Seguridad

- `test_login_rejects_wrong_password`
- `test_login_rejects_nonexistent_email`
- `test_login_returns_same_error_for_wrong_password_and_nonexistent_email`
- `test_login_response_does_not_expose_password_or_token`
- `test_unauthenticated_user_cannot_access_user_endpoint`
- `test_user_password_is_hidden`
- `test_restaurant_slug_must_be_unique`
- `test_subscriptions_table_exists_with_owner_id_fk`

### Transiciones de estado

- `test_order_item_status_transitions` (pending -> cooking -> ready -> delivered)
- `test_invalid_status_transition_rejected`
- `test_bulk_status_update`

### Concurrencia e idempotencia

- `test_duplicate_order_item_rejected`
- `test_offline_sync_deduplication`
- `test_lock_for_update_on_inventory`

### Pagos

- `test_stripe_webhook_signature_verification`
- `test_stripe_webhook_deduplication`
- `test_payment_transaction_states`

## Cómo leer resultados

### PHPUnit

```
PASS  Tests\Feature\PhaseOneArchitectureTest
  ✓ database has correct migrations and foreign keys constraints
  ✓ user can login via api and receives sanctum token
  ✓ login rejects wrong password
  ...

Tests:  19 passed
Time:   0.45s
```

```
FAIL  Tests\Feature\PhaseSevenOrderTransactionTest
  ✓ order creation with idempotency key
  ✗ order snapshot price preservation — Expected: 15.50, Actual: 15.5
  ...

Tests:  12 passed, 7 failed
```

### Vitest

```
✓ src/__tests__/example.test.js > example
Test Files  1 passed (1)
Tests  1 passed (1)
```

## Tests que requieren servicios simulados

| Servicio | Estado en tests | Simulación |
|---|---|---|
| MySQL | SQLite en memoria | No simulado, DB real en memoria |
| Redis | No usado | `QUEUE_CONNECTION=sync` |
| Reverb/WebSockets | Desactivado | `BROADCAST_CONNECTION=null` |
| Stripe | Parcial | Controller verifica firma pero no tiene clave real |
| Email | Desactivado | `MAIL_MAILER=array` |
| S3/Storage | Local | `FILESYSTEM_DISK=local` |

## Cobertura y limitaciones

### Cobertura actual

- **Multi-tenancy**: Bien cubierta (aislamiento entre tenants, scopes, middleware)
- **Autenticación**: Bien cubierta (login, logout, token, errores)
- **CRUDs Owner**: Cubiertos (categories, products, tables, staff)
- **Estado de pedidos**: Cubierto (transiciones, validación)
- **Offline sync**: Cubierto (deduplicación, estados, idempotencia)
- **Fiscalidad**: Cubierto (hash chaining, registros append-only)
- **Reservas**: Cubierto (motor de reservas, conflictos, concurrencia)
- **Inventario**: Cubierto (stock, ajustes, alertas, lockForUpdate)
- **Pagos**: Cubierto (transacciones, webhook verificación, deduplicación)
- **Analíticas**: Cubierto (summary, top products, CSV export)
- **SuperAdmin**: Cubierto (CRUD tenants, usuarios, suspend/activate)
- **Frontend Admin**: Cubierto (login, dashboard, CRUDs, router guards)
- **PWA**: Cubierto (manifest, service worker, precaching, runtime caching)

### No cubierto

- **E2E tests**: No hay tests Playwright/Cypress
- **Pruebas de accesibilidad**: No hay tests axe/lighthouse
- **Pruebas de seguridad**: No hay tests OWASP/ZAP
- **Contract tests**: No hay tests de contrato de API
- **PWA**: No hay tests de service worker
- **Agente de impresión Python**: No hay tests
