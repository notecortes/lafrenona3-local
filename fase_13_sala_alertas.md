# Especificación de Ejecución Autónoma - Fase 13: Panel Visual de Sala, Alertas del Comensal (Llamar Camarero/Cuenta) y Notificaciones Reactivas

## 1. Objetivo de la Fase
Implementar la interacción directa en tiempo real entre la mesa del cliente y el personal de sala. Se añadirá soporte en la base de datos para registrar estados de auxilio o facturación en las mesas. Se desarrollarán los endpoints públicos para que los comensales (validando su código QR y sesión) puedan "Llamar al Camarero" o "Solicitar la Cuenta" desde sus smartphones. Finalmente, se creará el **Panel Visual de Sala (Room Dashboard)** en Vue 3 para los camareros, el cual mostrará un mapa cuadriculado interactivo de las mesas que parpadeará reactivamente mediante WebSockets ante cualquier llamada de asistencia.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Fullstack senior experto en interfaces reactivas de alta interacción, diseño UX de componentes de sala y sistemas de mensajería asíncrona. Tu misión es dar vida a la Fase 13.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Todo el código Vue 3 debe utilizar la API de Composición (`<script setup>`).
- Las mutaciones de alertas deben propagarse inmediatamente a través de los canales de WebSockets correspondientes definidos en la Fase 4.
- No utilices placeholders ni dejes funciones vacías; escribe todo el código operativo.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos en el proyecto:
```text
backend/
├── database/
│   └── migrations/
│       └── 2026_01_01_000010_add_assistance_status_to_tables_table.php
├── app/
│   ├── Events/
│   │   └── ClientAssistanceRequested.php
│   └── Http/
│       └── Controllers/
│           └── Api/
│               ├── Client/
│               │   └── ClientAssistanceController.php
│               └── Staff/
│                   └── StaffRoomController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseThirteenAssistanceTest.php

frontend/
└── src/
    └── views/
        └── staff/
            └── RoomDashboard.vue