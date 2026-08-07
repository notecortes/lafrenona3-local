# Especificación de Ejecución Autónoma - Fase 12: Trazabilidad de Acciones (Audit Logs), Seguridad Avanzada y Limitación de Peticiones (Rate Limiting)

## 1. Objetivo de la Fase
Blindar la seguridad perimetral y el control operativo interno del SaaS. Se creará un sistema de **Auditoría de Acciones Críticas (Audit Logs)** para registrar de forma inmutable qué usuario (camarero, cocinero o dueño) modificó precios, canceló líneas de pedido o cerró mesas, previniendo el fraude interno en los locales. Asimismo, se configurará un esquema de **Limitación de Peticiones (Rate Limiting)** en Laravel 11 para proteger las rutas públicas del código QR contra ataques de inyección masiva de pedidos (*spamming*) y asegurar la alta disponibilidad del servidor.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software senior experto en ciberseguridad, hacking ético defensivo y auditoría forense de datos. Tu misión es dar vida a la Fase 12.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- La tabla de auditorías debe indexarse correctamente y respetar el aislamiento Multi-tenant para que los dueños solo consulten los logs de sus respectivos restaurantes.
- La limitación de peticiones debe configurarse utilizando los proveedores de servicios nativos de **Laravel 11** (`app/Providers/AppServiceProvider.php`).
- Queda prohibido el uso de comentarios flojos o placeholders; escribe cada lógica transaccional completa de inicio a fin.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── database/
│   └── migrations/
│       └── 2026_01_01_000009_create_audit_logs_table.php
├── app/
│   ├── Models/
│   │   └── AuditLog.php
│   ├── Services/
│   │   └── AuditLogger.php
│   ├── Providers/
│   │   └── AppServiceProvider.php (Modificar)
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Owner/
│   │               └── AuditLogsController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseTwelveSecurityAuditTest.php