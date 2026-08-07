# AGENTS.md — SaaS Restaurante Multi-tenant

## Rol

Eres el agente principal de ingeniería responsable de implementar un SaaS de restauración multi-tenant, usando:

- Backend: Laravel 11, PHP 8.4+, MySQL 8, Redis y Laravel Sanctum.
- Tiempo real: Laravel Reverb y Laravel Echo.
- Frontend: Vue 3, Vite, Pinia, Axios y PWA.
- Agente de impresión: Python 3, asyncio, websockets y ESC/POS.
- Infraestructura local: Docker Compose.
- Calidad: PHPUnit/Pest, Vitest, Playwright cuando exista configuración compatible.

Tu trabajo consiste en implementar las fases 1 a 18 de forma incremental, en el orden definido por los documentos de especificación.

## Fuentes de verdad

Antes de trabajar en una fase, lee obligatoriamente:

1. La especificación funcional de la fase:
   - `fase_1_*.md` hasta `fase_18_*.md`.

2. Su plan QA principal:
   - `qa_fase_01_*.md` hasta `qa_fase_18_*.md`.

3. Las pruebas adicionales de endurecimiento:
   - `pruebas_adicionales_endurecimiento_fases_01_18.md`.

4. Este archivo `AGENTS.md`.

En caso de contradicción, aplica este orden de prioridad:

1. Seguridad e integridad de datos.
2. Especificación funcional de fase.
3. Pruebas adicionales de endurecimiento.
4. Plan QA base.
5. Este archivo.

Nunca inventes requisitos, endpoints, tablas, roles, secretos, flujos ni comportamientos que contradigan los documentos.

## Regla de avance

Solo puedes empezar la fase N cuando la fase N-1 esté marcada como aprobada.

La fase N se considera aprobada solo si:

- La implementación solicitada por su especificación existe y funciona.
- Todos los tests de la fase pasan.
- Todos los casos de endurecimiento `EXT-FNN-*` aplicables pasan o están documentados como no aplicables con justificación técnica.
- No hay defectos críticos o altos abiertos.
- El build y los tests de regresión afectados pasan.
- Existe un informe en `qa_resultados_implementacion/fase_NN.md`.

No avances automáticamente de fase: al acabar cada fase, detente, muestra el informe y espera confirmación explícita del usuario.

## Orden obligatorio

1. Fase 1 — Infraestructura, Docker, MySQL, Redis, Laravel y Sanctum.
2. Fase 2 — Multi-tenancy, tenant scope y suscripciones.
3. Fase 3 — CRUD de owner.
4. Fase 4 — Reverb y WebSockets.
5. Fase 5 — Cocina, barra e impresión.
6. Fase 6 — Carta pública, PWA y accesibilidad.
7. Fase 7 — Pedidos, transacciones, snapshots e idempotencia.
8. Fase 8 — Operación offline y sincronización.
9. Fase 9 — SuperAdmin y pruebas de estrés.
10. Fase 10 — CI/CD, monitorización y backups.
11. Fase 11 — Analítica y exportación BI.
12. Fase 12 — Seguridad, rate limiting y auditoría.
13. Fase 13 — Sala y alertas.
14. Fase 14 — Pagos, Stripe Connect, propinas y ticket digital.
15. Fase 15 — Inventario, escandallos y alertas.
16. Fase 16 — Reservas, asignación y lista de espera.
17. Fase 17 — Caja, Informe Z y fiscalidad.
18. Fase 18 — Formularios CRUD de SuperAdmin y Owner.

## Arquitectura obligatoria

### Límites de tenant

- Todo dato operativo debe pertenecer explícitamente a un `restaurant_id`.
- El tenant debe resolverse de forma segura desde el usuario autenticado, sesión autorizada o contexto controlado; nunca confiar en un `restaurant_id` recibido desde cliente.
- Aplicar Global Scope, policies, middleware y validación de relaciones.
- Un owner, staff o cliente de Tenant A jamás puede leer, crear, modificar, borrar, exportar o recibir eventos de Tenant B.
- `withoutGlobalScopes()` queda prohibido salvo en servicios internos de superadmin explícitamente autorizados y cubiertos por test.
- Todo job, listener, comando, evento y exportación debe mantener un contexto de tenant explícito.

### Estados de negocio

Implementa transiciones explícitas y validadas para:

- `OrderItem`: `pending -> cooking -> ready -> delivered`; cancelaciones solo bajo reglas autorizadas.
- `Table`: `free -> occupied -> payment_pending -> free`.
- `Reservation`: `pending -> confirmed -> seated -> completed`; cancelación y no-show según fase 16.
- Pagos, inventario, caja y fiscalidad: transición única, auditable e idempotente.

No permitas saltos de estado ni cambios directos por mass assignment.

### Operaciones críticas

Las operaciones de pedidos, pago, reserva, cierre de mesa, movimiento de inventario, cierre de caja y generación fiscal deben:

- Ejecutarse dentro de `DB::transaction()`.
- Bloquear filas cuando sea necesario con `lockForUpdate()`.
- Usar claves de idempotencia donde corresponda.
- Persistir datos antes de emitir eventos.
- No emitir eventos WebSocket ni descontar stock si la transacción revierte.
- Mantener snapshots inmutables de precio, impuestos y otros valores históricos requeridos.

### Outbox y eventos

Cuando una fase conecte una transacción con WebSockets, impresión, inventario, emails o integraciones externas:

- Usa un patrón de outbox o mecanismo equivalente confiable.
- Guarda el evento de dominio dentro de la misma transacción de negocio.
- Procesa efectos secundarios mediante cola/job idempotente.
- Cada consumidor debe deduplicar por `event_id` o clave equivalente.
- Los eventos de UI son derivados de eventos de dominio, no la fuente de verdad.

### Dinero, pagos y fiscalidad

- Trata importes como enteros en céntimos o `decimal` estricto, nunca `float`.
- El backend calcula importes; no acepta precios, totales, restaurant Stripe account o estado financiero manipulable desde cliente.
- Los webhooks de Stripe deben verificar firma, timestamp y deduplicarse por `event_id`.
- No cerrar mesa ni generar factura fiscal hasta confirmación de pago válida, salvo flujo explícito de pago manual auditado.
- Registros de auditoría y fiscalidad son append-only, sin API de edición/borrado.
- La cadena fiscal debe detectar modificación, borrado o reordenación de registros.

### Sesiones QR y cliente público

- Usar tokens opacos de alta entropía.
- Rotar o invalidar sesión al cerrar mesa.
- Aplicar expiración y limitación de peticiones.
- No exponer datos de otros clientes, mesas, empleados, pedidos previos ni secretos internos.
- Distinguir claramente en UX: “guardado localmente” vs. “enviado y confirmado”.

### Offline

- Todas las operaciones offline deben llevar UUID/idempotency key.
- La cola local debe devolver estado por elemento: aceptado, duplicado, rechazado, conflicto o reintentable.
- Una reconexión no puede duplicar pedidos, impresión, deducción de inventario, pago ni facturación.
- No guardar secretos de alto privilegio en IndexedDB.

### Seguridad

- Aplicar validación de requests con Form Requests.
- Aplicar policies/middleware por rol y recurso.
- Bloquear IDOR/BOLA, mass assignment, XSS, SQL injection, CSV injection y payloads excesivos.
- No devolver stack traces, SQL, contraseñas, tokens, secretos, claves privadas ni PII innecesaria.
- Configurar rate limiting para login, QR, pedidos públicos, alertas y webhooks.
- Configurar CORS restrictivo por entorno.
- Mantener `.env` fuera de Git y proporcionar `.env.example` sin secretos.
- No usar `APP_DEBUG=true` en producción.
- Ejecutar análisis de dependencias y secretos en CI.

### Accesibilidad y frontend

- Vue 3 debe usar Composition API y `<script setup>`.
- Formularios con etiquetas visibles/asociadas, validación accesible, foco gestionado y mensajes de error textuales.
- Cumplir WCAG 2.1 AA: contraste, teclado, foco visible, semántica, ARIA solo cuando sea necesario y respeto de `prefers-reduced-motion`.
- Carta pública móvil: fuentes y espaciados en unidades relativas; carga ligera; no cargar bundles administrativos.
- Gestionar estados de carga, error, vacío, offline, reintento y éxito.
- No dejar listeners WebSocket activos al desmontar componentes.

## Reglas de implementación

- PHP: `declare(strict_types=1);` cuando la estructura del proyecto lo permita, tipado estricto y PHP 8.3 compatible.
- No usar pseudocódigo, `TODO`, `FIXME`, stubs vacíos, `throw new Exception('not implemented')` ni placeholders.
- No borrar ni degradar tests existentes.
- No modificar los documentos `fase_*`, `qa_fase_*` ni `pruebas_adicionales_*`.
- No hacer commits, pushes, despliegues ni usar servicios externos reales.
- Usar factories, seeders y fixtures reproducibles.
- Nombres claros, controladores delgados, reglas en servicios/acciones y validación en Form Requests.
- Versionar migraciones nuevas; no reescribir migraciones ya aplicadas salvo que el repositorio siga vacío y la fase lo requiera.
- Añadir índices, FK, `CHECK` y restricciones de unicidad en el nivel de base de datos cuando proceda.

## Protocolo por fase

### A. Análisis

Antes de cambiar archivos:

1. Lee la documentación de la fase.
2. Inspecciona el estado real del repositorio.
3. Identifica dependencias de fases anteriores.
4. Lista archivos que crearás o modificarás.
5. Lista rutas, migraciones, modelos, servicios, eventos y tests implicados.
6. Define los datos de prueba necesarios.
7. Explica brevemente riesgos y decisiones técnicas.

No implementes todavía. Muestra el plan y espera confirmación del usuario.

### B. Implementación

Tras confirmación:

1. Implementa en cambios pequeños y coherentes.
2. Ejecuta lint/sintaxis después de cada bloque relevante.
3. Crea o actualiza tests unitarios, feature/integration y frontend cuando aplique.
4. No uses datos externos ni credenciales reales.
5. Mantén el sistema ejecutable durante todo el proceso.

### C. Verificación

Al terminar la fase:

1. Ejecuta migraciones exclusivamente en base de datos de test.
2. Ejecuta los tests concretos de la fase.
3. Ejecuta los casos de endurecimiento `EXT-FNN-*` aplicables.
4. Ejecuta regresión de módulos afectados.
5. Si se modifica frontend, ejecuta tests frontend, lint y build.
6. Si hay WebSockets, pagos, impresión, backup u offline, usa fakes/mocks/sandbox y prueba errores, reintentos y duplicados.
7. Repite corregir -> verificar hasta obtener resultado reproducible.

Comandos orientativos; detecta los reales antes de usarlos:

```bash
docker compose up -d --build
docker compose exec backend php artisan migrate:fresh --env=testing
docker compose exec backend php artisan test
cd frontend && npm ci && npm run test && npm run build
cd frontend && npx playwright test
```

### D. Informe

Crea:

```text
qa_resultados_implementacion/fase_NN.md
```

Con este formato:

```md
# Implementación y validación — Fase NN

## Estado
APROBADA / APROBADA CON OBSERVACIONES / NO APROBADA / BLOQUEADA

## Alcance implementado
- ...

## Archivos creados o modificados
| Archivo | Cambio | Motivo |
|---|---|---|

## Rutas, migraciones y contratos
| Elemento | Estado | Notas |
|---|---|---|

## Pruebas ejecutadas
| ID o comando | Tipo | Resultado | Evidencia |
|---|---|---|---|

## Seguridad y tenant isolation
| Prueba | Resultado |
|---|---|

## Concurrencia e idempotencia
| Escenario | Resultado |
|---|---|

## Defectos o bloqueos
| Severidad | Descripción | Impacto | Próxima acción |
|---|---|---|---|

## Comandos ejecutados
```bash
# comandos y resultado breve
```

## Decisión
Indica si la fase está aprobada y si puede continuar la siguiente.
```

Al finalizar, detente y espera el mensaje explícito:

```text
Continúa con la fase NN+1.
```

## Definición de terminado global

El proyecto solo está terminado al completar las 18 fases y crear:

```text
qa_resultados_implementacion/99_informe_final.md
```

El informe final debe listar:

- Estado de cada fase.
- Tests ejecutados, aprobados, fallidos y bloqueados.
- Riesgos de seguridad pendientes.
- Deuda técnica.
- Cobertura de multi-tenancy, concurrencia, idempotencia, pagos, fiscalidad, accesibilidad, backup y recuperación.
- Recomendación de aptitud para staging y producción.

## Requisitos de arquitectura adicionales

Estas reglas complementan las especificaciones de las fases y son obligatorias salvo que exista una contradicción explícita en la especificación funcional.

### Modelo de identidad y membresías

No asumir que un usuario solo puede pertenecer a un restaurante.

- Mantener `users` como identidad global.
- Implementar una relación explícita de membresía, por ejemplo `restaurant_memberships`, cuando sea necesario para soportar empleados que trabajan en varios restaurantes.
- El rol operativo debe estar asociado a la membresía del restaurante, no solo al usuario global.
- Los propietarios y administradores deben gestionar altas, bajas, invitaciones y desactivación de membresías sin borrar el historial.
- Si se mantiene un modelo de usuario con `restaurant_id` por compatibilidad de fase, documentar la limitación y diseñar las relaciones para permitir migración posterior sin romper datos.

### Restricciones de base de datos

La validación de Laravel no sustituye la integridad en MySQL.

Añadir restricciones de base de datos cuando proceda:

- Foreign keys en relaciones obligatorias.
- Índices compuestos que comiencen por `restaurant_id` en tablas multi-tenant.
- Índices de unicidad por tenant, por ejemplo:
  - `unique(restaurant_id, number)` para mesas.
  - `unique(restaurant_id, idempotency_key)` cuando la clave no sea global.
- `CHECK` o validación equivalente de base de datos para:
  - Cantidades mayores que cero.
  - Precios e importes no negativos.
  - Capacidad de mesa mayor que cero.
  - Estados permitidos cuando el motor lo soporte.
- Prohibir borrado físico de pedidos, pagos, movimientos de inventario, auditoría y fiscalidad.
- Preferir archivado o desactivación para productos, categorías, empleados y mesas cuando el historial lo requiera.

### Versionado y snapshots históricos

Los cambios futuros no pueden alterar datos históricos.

Persistir snapshots inmutables en el momento de la operación para:

- Nombre, precio, impuestos, descuentos, alérgenos y tarifa aplicados a cada línea de pedido.
- Datos fiscales del restaurante aplicables a cada factura.
- Coste y receta/escandallo aplicados a cada movimiento de inventario.
- Estado o versión de carta relevante para sincronización offline.
- Diseño y datos públicos necesarios para tickets o documentos históricos si son legalmente relevantes.

### Modelo de pagos y conciliación

No almacenar el pago solamente como un estado de pedido.

Crear un registro de transacción de pago inmutable o append-only, por ejemplo:

```text
payment_transactions
- id
- restaurant_id
- order_id
- provider
- provider_payment_id
- webhook_event_id
- idempotency_key
- amount_cents
- tip_cents
- currency
- status
- confirmed_at
- metadata_reference
```

Reglas obligatorias:

- El cliente nunca decide el importe final, moneda, cuenta Stripe Connect ni estado de pago.
- Soportar de manera explícita pagos fallidos, cancelados, repetidos, parcialmente pagados, pagos manuales, propinas, reembolsos y conciliación.
- Todo pago manual debe guardar usuario, método, importe, motivo y auditoría.
- Una mesa solo se libera después de una transición de pago válida o de una acción manual autorizada y auditada.
- El fallo de envío de email o ticket nunca debe deshacer un cobro confirmado.

### Fiscalidad y cumplimiento

No afirmar cumplimiento de Veri*Factu, TicketBAI, SII u otra normativa sin implementar y validar sus requisitos oficiales.

- Implementar el módulo fiscal como registro interno antifraude hasta que exista validación legal específica.
- Separar el registro fiscal interno de la integración normativa oficial.
- Diseñar soporte para facturas rectificativas, anulaciones, abonos y conservación de documentos.
- Mantener hash encadenado usando una representación canónica estable del payload.
- Proteger la cadena contra modificación, borrado y reordenación.
- Documentar zona horaria, reglas de numeración, redondeo e impuestos aplicados.
- Marcar cualquier requisito legal no validado como pendiente de revisión profesional.

### Ciclo de vida de datos y RGPD

Implementar privacidad desde el diseño.

- Definir clasificación de datos: operativo, financiero, fiscal, personal y secreto.
- No guardar datos personales innecesarios en logs, eventos WebSocket, backups o analítica.
- Diseñar política de retención, anonimización y borrado compatible con obligaciones fiscales.
- Incluir exportación y rectificación de datos cuando aplique.
- Proteger datos de reservas y correos electrónicos mediante acceso mínimo por rol.
- Configurar consentimiento y preferencias de comunicación si se envían mensajes no estrictamente transaccionales.
- Documentar proveedores externos y datos transmitidos: pagos, correo, errores, almacenamiento y monitorización.

### Observabilidad y operación

Cada operación distribuida debe poder trazarse sin exponer secretos.

- Generar `request_id` por solicitud.
- Propagar `request_id`, `tenant_id`, `order_id`, `payment_id` y `event_id` por logs, jobs y eventos.
- No escribir tokens, contraseñas, secretos, tarjetas ni payloads completos de Stripe en logs.
- Implementar health checks diferenciados para aplicación, MySQL, Redis, cola, Reverb y dependencias críticas.
- Implementar métricas operativas: pedidos creados, errores, reintentos, colas offline pendientes, impresión fallida, eventos fallidos y latencia.
- Toda copia de seguridad debe ser cifrada, verificable y restaurada periódicamente en un entorno de prueba.
- Definir entornos separados: local, testing, staging y producción.
- Las migraciones de producción deben ser compatibles hacia atrás: expandir, desplegar, migrar datos y contraer.

### Calidad de API

- Versionar rutas bajo `/api/v1`.
- Usar Form Requests, Resources/DTOs y respuestas de error consistentes.
- Generar y mantener una especificación OpenAPI para endpoints públicos e internos relevantes.
- Definir paginación, límites máximos, filtrado y orden permitido.
- Evitar que los mensajes de error revelen existencia de recursos ajenos.
- Añadir pruebas de contrato para rutas críticas: auth, QR, pedidos, pagos, reservas y webhooks.

### Feature flags y despliegue gradual

Los módulos de mayor impacto deben activarse gradualmente por restaurante.

- Crear feature flags por tenant para pagos, fiscalidad, inventario, reservas, offline y hardware de impresión.
- Una bandera debe poder desactivar una integración problemática sin eliminar datos ni romper pedidos existentes.
- Registrar en auditoría cambios de flags y quién los realizó.
- No habilitar pagos reales ni fiscalidad productiva hasta completar pruebas de integración, recuperación y revisión legal.
