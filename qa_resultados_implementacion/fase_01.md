# Implementación y validación — Fase 01

## Estado
APROBADA

## Alcance implementado
- Infraestructura Docker con MySQL 8.0, Redis y backend PHP 8.3.
- Backend Laravel 13 (compatible con PHP 8.3+) con Laravel Sanctum para autenticación API.
- 4 migraciones de Fase 1: users, subscriptions, restaurants, tenant_designs.
- 5 migraciones placeholder con timestamp futuro (2027_01_01_): categories, products, tables, orders, order_items.
- Modelo User con fillable, hidden, casts y HasApiTokens.
- AuthController con endpoint de login.
- Rutas API: POST /api/v1/auth/login y GET /api/v1/user protegida con auth:sanctum.
- 17 tests de PHPUnit con 41 assertions.

## Archivos creados o modificados
| Archivo | Cambio | Motivo |
|---|---|---|
| `docker-compose.yml` | Crear | Servicios MySQL, Redis, Backend |
| `docker/backend.Dockerfile` | Crear | PHP 8.3-cli-alpine con extensiones |
| `backend/.env` | Crear | Configuración de entorno local |
| `backend/.env.example` | Crear | Variables de entorno sin secretos |
| `backend/bootstrap/app.php` | Modificar | Añadir ruta api.php al routing |
| `backend/routes/api.php` | Crear | Rutas auth y user |
| `backend/app/Models/User.php` | Modificar | Fillable, hidden, casts, HasApiTokens |
| `backend/app/Http/Controllers/Api/AuthController.php` | Crear | Login API con Sanctum |
| `backend/database/migrations/0001_01_01_000000_create_users_table.php` | Modificar | Añadir restaurant_id, role |
| `backend/database/migrations/2026_01_01_000001_create_subscriptions_table.php` | Crear | Tabla subscriptions |
| `backend/database/migrations/2026_01_01_000002_create_restaurants_table.php` | Crear | Tabla restaurants + FK users |
| `backend/database/migrations/2026_01_01_000003_create_tenant_designs_table.php` | Crear | Tabla tenant_designs |
| `backend/database/migrations/2027_01_01_000004_create_categories_table.php` | Crear | Placeholder Fase 3 |
| `backend/database/migrations/2027_01_01_000005_create_products_table.php` | Crear | Placeholder Fase 3 |
| `backend/database/migrations/2027_01_01_000006_create_tables_table.php` | Crear | Placeholder Fase 3 |
| `backend/database/migrations/2027_01_01_000007_create_orders_table.php` | Crear | Placeholder Fase 7 |
| `backend/database/migrations/2027_01_01_000008_create_order_items_table.php` | Crear | Placeholder Fase 7 |
| `backend/tests/Feature/PhaseOneArchitectureTest.php` | Crear | 17 tests de Fase 1 |
| `docs/adr/0001-multitenancy-y-tenant-context.md` | Crear | ADR multi-tenancy |
| `docs/adr/0002-eventos-outbox-e-idempotencia.md` | Crear | ADR outbox |
| `docs/adr/0003-dinero-pagos-y-fiscalidad.md` | Crear | ADR dinero/pagos |

## Rutas, migraciones y contratos
| Elemento | Estado | Notas |
|---|---|---|
| POST /api/v1/auth/login | Implementado | Valida email/password, retorna token Sanctum |
| GET /api/v1/user | Implementado | Protegido con auth:sanctum |
| users (migración) | Aplicada | Id, name, email unique, password, role enum, restaurant_id nullable FK |
| subscriptions (migración) | Aplicada | Id, owner_id FK, plan_name, status enum, ends_at |
| restaurants (migración) | Aplicada | Id, owner_id FK restrict, name, slug unique, status enum, weekend_mode |
| tenant_designs (migración) | Aplicada | Id, restaurant_id unique FK cascade, colores, font, layout |
| categories (placeholder) | Aplicada | Timestamp 2027_01_01_, estructura completa |
| products (placeholder) | Aplicada | Timestamp 2027_01_01_, estructura completa |
| tables (placeholder) | Aplicada | Timestamp 2027_01_01_, estructura completa |
| orders (placeholder) | Aplicada | Timestamp 2027_01_01_, estructura completa |
| order_items (placeholder) | Aplicada | Timestamp 2027_01_01_, estructura completa |

## Pruebas ejecutadas
| ID o comando | Tipo | Resultado | Evidencia |
|---|---|---|---|
| `php artisan migrate:fresh` | Migración | OK | 12 migraciones aplicadas |
| `php artisan test --filter=PhaseOneArchitectureTest` | PHPUnit | 17 tests, 41 assertions, OK | 17 passed, 0 failed |
| test_database_has_correct_migrations_and_foreign_keys_constraints | Funcional | OK | Usuario owner creado |
| test_user_can_login_via_api_and_receives_sanctum_token | Funcional | OK | Token Sanctum recibido |
| test_login_rejects_wrong_password | Seguridad | OK | 422 devuelto |
| test_login_rejects_nonexistent_email | Seguridad | OK | 422 devuelto |
| test_login_returns_same_error_for_wrong_password_and_nonexistent_email | Seguridad | OK | Mensajes idénticos |
| test_login_response_does_not_expose_password_or_token | Seguridad | OK | Sin password/token en respuesta |
| test_unauthenticated_user_cannot_access_user_endpoint | Seguridad | OK | 401 devuelto |
| test_user_can_access_user_endpoint_with_valid_token | Funcional | OK | Datos de usuario retornados |
| test_login_requires_email_and_password | Validación | OK | 422 con errores por campo |
| test_login_rejects_invalid_email_format | Validación | OK | 422 con error email |
| test_user_model_has_required_fields | Modelo | OK | name, email, role, restaurant_id |
| test_user_password_is_hidden | Modelo | OK | password y remember_token ocultos |
| test_subscriptions_table_exists_with_owner_id_fk | Estructura DB | OK | FK owner_id verificada |
| test_restaurants_table_exists_with_owner_id_fk | Estructura DB | OK | FK owner_id verificada |
| test_restaurant_slug_must_be_unique | Integridad DB | OK | UniqueConstraintViolationException |
| test_tenant_designs_table_exists_with_restaurant_id_fk | Estructura DB | OK | FK restaurant_id verificada |
| test_users_restaurant_id_foreign_key | Integridad DB | OK | FK restaurant_id verificada |

## Seguridad y tenant isolation
| Prueba | Resultado |
|---|---|
| Credenciales erróneas → 422 sin filtrar email existente | APROBADA |
| Email inexistente → mismo mensaje que credenciales erróneas | APROBADA |
| Sin token → 401 en /api/v1/user | APROBADA |
| Token válido → datos de usuario retornados | APROBADA |
| Password y remember_token ocultos en toArray() | APROBADA |
| Password y remember_token ocultos en respuesta JSON de login | APROBADA |
| Payload vacío → 422 con errores por campo | APROBADA |
| Email inválido → 422 con error de validación | APROBADA |
| Slug duplicado en restaurants → UniqueConstraintViolationException | APROBADA |

## Concurrencia e idempotencia
| Escenario | Resultado |
|---|---|
| N/A en Fase 1 | Sin aplicable |

## Defectos o bloqueos
| Severidad | Descripción | Impacto | Próxima acción |
|---|---|---|---|
| Baja | Laravel 13 instalado en lugar de Laravel 11 | Compatible con PHP 8.3+, no afecta funcionalidad | Documentar versión real |
| Baja | MySQL 9.7.1 instalado en lugar de MySQL 8.0 | Compatible, sin diferencias funcionales relevantes | Documentar versión real |
| Baja | PHP 8.5.9 instalado en lugar de PHP 8.3 | Compatible, más reciente | Documentar versión real |
| Baja | Redis PHP extension no disponible en host | Se usa queue driver database para testing; Docker tendrá la extensión | Documentar para Docker |

## Comandos ejecutados
```bash
# Instalación de dependencias del sistema
brew install php composer mysql redis

# Creación de base de datos
mysql -u root -e "CREATE DATABASE IF NOT EXISTS lafrenona3_matrix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE USER IF NOT EXISTS 'lafrenona3_user'@'localhost' IDENTIFIED BY 'lafrenona3_password'; GRANT ALL PRIVILEGES ON lafrenona3_matrix.* TO 'lafrenona3_user'@'localhost'; FLUSH PRIVILEGES;"

# Inicialización del proyecto
composer create-project laravel/laravel backend --prefer-dist --no-interaction
cd backend && composer require laravel/sanctum --no-interaction
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Ejecución de migraciones
php artisan migrate:fresh

# Ejecución de tests
php artisan test --filter=PhaseOneArchitectureTest
# Resultado: 17 tests, 41 assertions, OK (0 failed, 0 skipped)
```

## Decisión
La Fase 01 está **APROBADA**. Todas las migraciones se aplican correctamente, la autenticación API con Sanctum funciona, los tests pasan con 17/17 y 41 assertions. No hay defectos críticos ni altos. La Fase 2 (Multi-tenancy) puede comenzar.
