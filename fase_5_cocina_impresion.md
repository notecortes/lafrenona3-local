# Especificación de Ejecución Autónoma - Fase 5: Monitor de Cocina/Barra y Agente Asíncrono de Impresión Local

## 1. Objetivo de la Fase
Conectar y automatizar el flujo operativo de preparación en el local. Se desarrollará el endpoint en el backend para la mutación de estados de los platos, la interfaz interactiva en Vue 3 (Monitor de Cocina) que reacciona instantáneamente a los WebSockets, y el Agente Autónomo en Python (`agente_impresion.py`) que intercepta las comandas y las envía al spooler de impresión física (ESC/POS) del sistema operativo de forma asíncrona y no bloqueante.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Fullstack senior experto en hardware embebido, scripts asíncronos en Python y frontend reactivo. Tu misión es dar vida a la Fase 5.
- El código PHP debe estar estrictamente tipado (PHP 8.2+).
- El componente de Vue 3 debe utilizar la API de Composición (`<script setup>`) con gestión limpia de ciclos de vida (`onMounted`, `onUnmounted`) para evitar fugas de memoria al suscribir/desuscribir canales de Laravel Echo.
- El script de Python debe ser puramente no bloqueante (`asyncio` + `websockets`) e incluir un mecanismo tolerante a fallos con reconexión infinita.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos:
```text
backend/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Staff/
│   │               └── OrderItemsController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseFiveStaffControlTest.php

frontend/
└── src/
    └── views/
        └── staff/
            └── KitchenMonitor.vue

raiz-del-proyecto/
└── agentes/
    └── agente_impresion.py