# Especificación de Ejecución Autónoma - Fase 9: Panel de Control del SuperAdmin, Pruebas de Estrés y QA de Producción

## 1. Objetivo de la Fase
Cerrar el ciclo de desarrollo del ecosistema SaaS Multi-tenant. Se implementará la capa de control global exclusiva para el rol `superadmin` (creación de dueños, suspensión de locales, control de planes y estados de facturación). Además, se desarrollará un comando de consola avanzado de Laravel para simular **pruebas de estrés masivas con colisiones de concurrencia e idempotencia** con el fin de certificar la solidez transaccional del backend antes de su empaquetado final para producción.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de infraestructura y arquitecto de software backend principal (Principal Engineer). Tu misión es dar vida a la Fase 9.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Las consultas del SuperAdmin deben eludir el Global Scope de Tenant, ya que el SuperAdmin posee visibilidad absoluta sobre la base de datos global.
- Queda terminantemente prohibido el uso de comentarios flojos o placeholders; escribe cada lógica transaccional completa de inicio a fin.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── SimulateSaasStressTest.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── SuperAdmin/
│   │               └── TenantManagementController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseNineSuperAdminTest.php