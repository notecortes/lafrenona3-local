# Plan de pruebas — Fase 14: Pagos y propinas

## Objetivo

Validar que la fase cumple sus requisitos funcionales, técnicos, de seguridad y usabilidad. El alcance incluye: Stripe Connect, webhooks firmados, propinas y recibos.

## Precondiciones

- Entorno local levantado con Docker y variables de entorno de prueba.
- Base de datos reinicializable mediante migraciones y fixtures reproducibles.
- Dos restaurantes de prueba, dos propietarios y un superadmin cuando aplique.
- Usuarios de staff: `waiter`, `kitchen` y `bar`; sesión de mesa/QR cuando aplique.
- Navegador Chromium actualizado; DevTools disponible para red, consola y simulación offline.

## Datos mínimos

| Recurso | Tenant A | Tenant B |
|---|---|---|
| Restaurante | Restaurante QA A | Restaurante QA B |
| Propietario | owner.a@example.test | owner.b@example.test |
| Personal | waiter.a@example.test | waiter.b@example.test |
| Mesa | A-01 | B-01 |

## Casos críticos

### QA-F14-001 — Crear intención de pago en la cuenta conectada del restaurante correcto.

**Prioridad:** crítica  
**Tipo:** funcional, API, integración o E2E según corresponda  
**Pasos:**

1. Preparar datos aislados para los tenants A y B.
2. Ejecutar el flujo descrito en el título del caso.
3. Revisar respuesta HTTP, interfaz, registros de base de datos y eventos en tiempo real cuando apliquen.

**Resultado esperado:** la operación concluye de forma consistente; no se exponen ni alteran datos ajenos; los fallos se muestran con mensajes comprensibles y sin datos sensibles.

**Evidencia requerida:** captura o vídeo breve, respuesta API, consulta SQL de comprobación y salida del test automatizado.

### QA-F14-002 — Enviar webhook válido, repetido e inválido; solo el válido debe cambiar el estado una vez.

**Prioridad:** crítica  
**Tipo:** funcional, API, integración o E2E según corresponda  
**Pasos:**

1. Preparar datos aislados para los tenants A y B.
2. Ejecutar el flujo descrito en el título del caso.
3. Revisar respuesta HTTP, interfaz, registros de base de datos y eventos en tiempo real cuando apliquen.

**Resultado esperado:** la operación concluye de forma consistente; no se exponen ni alteran datos ajenos; los fallos se muestran con mensajes comprensibles y sin datos sensibles.

**Evidencia requerida:** captura o vídeo breve, respuesta API, consulta SQL de comprobación y salida del test automatizado.

### QA-F14-003 — Validar importe, propina, cierre de mesa y recibo digital.

**Prioridad:** crítica  
**Tipo:** funcional, API, integración o E2E según corresponda  
**Pasos:**

1. Preparar datos aislados para los tenants A y B.
2. Ejecutar el flujo descrito en el título del caso.
3. Revisar respuesta HTTP, interfaz, registros de base de datos y eventos en tiempo real cuando apliquen.

**Resultado esperado:** la operación concluye de forma consistente; no se exponen ni alteran datos ajenos; los fallos se muestran con mensajes comprensibles y sin datos sensibles.

**Evidencia requerida:** captura o vídeo breve, respuesta API, consulta SQL de comprobación y salida del test automatizado.

## Casos negativos

- Solicitud sin autenticación, token inválido, sesión expirada o rol insuficiente: debe devolver 401 o 403 según proceda.
- Payload malformado, tipos erróneos, campos obligatorios ausentes y valores fuera de rango: debe devolver 422 con errores por campo.
- Intento de acceder a recursos del Tenant B desde el Tenant A: debe devolver 403 o 404 y no filtrar metadatos.
- Repetición de solicitudes mutables: debe mantener consistencia y usar idempotencia cuando la fase la requiera.
- Error inesperado de infraestructura: no debe dejar escrituras parciales ni mostrar trazas al usuario.

## Usabilidad y accesibilidad

- Estados explícitos de carga, éxito, vacío, error y reintento.
- Navegación por teclado, foco visible y orden lógico en los flujos con interfaz.
- Etiquetas asociadas a controles; errores comunicados mediante texto, no solo color.
- Diseño responsive, con interacción viable en pantalla móvil y sin desbordamientos horizontales.
- Ausencia de errores y advertencias relevantes en consola del navegador.

## Automatización

| Nivel | Herramienta | Mínimo esperado |
|---|---|---|
| Backend | PHPUnit o Pest | Casos de éxito, autorización, validación y persistencia |
| Frontend | Vitest | Componentes, estados y validaciones relevantes |
| E2E | Playwright | Flujo crítico completo desde navegador |
| Carga | k6 o Artillery | Solo cuando haya concurrencia, sincronización o WebSockets |

Comandos orientativos:

```bash
cd backend && php artisan test
cd frontend && npm run test
cd frontend && npx playwright test
```

## Criterio de aprobación

- Todos los casos críticos de esta fase aprobados.
- Ningún fallo de aislamiento multi-tenant, autorización o integridad de datos.
- Cero errores bloqueantes de consola, red o base de datos.
- Pruebas automatizadas reproducibles con código de salida 0.
- Incidencias abiertas clasificadas y sin defectos críticos o altos pendientes.

## Registro de ejecución

| Fecha | Entorno/commit | Caso | Resultado | Evidencia | Responsable |
|---|---|---|---|---|---|
| | | | Pendiente | | |
