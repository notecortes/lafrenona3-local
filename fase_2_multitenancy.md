# Especificación de Ejecución Autónoma - Fase 2: Núcleo Multi-tenant y Filtros de Suscripción

## 1. Objetivo de la Fase
Implementar el aislamiento de datos lógico (Multi-tenancy) mediante un `Global Scope` automatizado para proteger la información operativa del personal de cada restaurante. Asimismo, desarrollar y registrar los middlewares de seguridad que impiden que un Dueño manipule locales ajenos y bloqueen el acceso de escritura si la suscripción SaaS no está al día.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software backend automatizado de nivel senior. Tu tarea es generar la capa de seguridad, traits arquitectónicos, middlewares y modelos correspondientes a la Fase 2.
- No utilices placeholders ni comentarios incompletos. Todo el código PHP debe ser sintácticamente correcto para PHP 8.2+.
- Registra adecuadamente los nuevos middlewares siguiendo la nueva estructura de configuración de **Laravel 11** (`bootstrap/app.php`).

---

## 3. Estructura de Archivos a Modificar o Crear
El agente LLM debe asegurar la existencia de los siguientes archivos en `/backend`:
```text
backend/
├── app/
│   ├── Http/
│   │   └── Middleware/
│   │       ├── CheckOwnerRestaurant.php
│   │       └── CheckSubscription.php
│   ├── Models/
│   │   ├── Restaurant.php
│   │   ├── Subscription.php
│   │   ├── Category.php
│   │   ├── Scopes/
│   │   │   └── TenantScope.php
│   │   └── Traits/
│   │       └── BelongsToTenant.php
│   └── Providers/
├── bootstrap/
│   └── app.php (Modificar)
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseTwoMultiTenancyTest.php