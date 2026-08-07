# Especificación de Ejecución Autónoma - Fase 8: Resiliencia Offline, Heartbeat de Red Híbrido y Sincronización en Ráfaga

## 1. Objetivo de la Fase
Dotar a la aplicación del personal (Staff / Camareros) de inmunidad total contra microcortes o caídas prolongadas de la red Wi-Fi del establecimiento. Se implementará un mecanismo de detección de red híbrido en el frontend (Heartbeat) que combine `navigator.onLine` con consultas en segundo plano de baja latencia al servidor. Si se confirma el estado offline, las transacciones se encapsularán de manera segura con identificadores UUIDv4 (`idempotency_key`) dentro de IndexedDB (vía Dexie.js). Al restablecerse la conexión, el sistema despachará una ráfaga masiva hacia un endpoint especializado de Laravel 11 que procesará secuencialmente el lote bajo transacciones ACID, descartando duplicados.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Fullstack senior experto en sistemas distribuidos, almacenamiento indexado en navegadores y tolerancia a fallos de infraestructura. Tu misión es dar vida a la Fase 8.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Todo el código Vue 3 debe utilizar la API de Composición (`<script setup>`).
- Queda terminantemente prohibido el uso de comentarios flojos o placeholders; escribe cada lógica transaccional completa de inicio a fin.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos en el proyecto:
```text
backend/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Staff/
│   │               └── SyncOfflineController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseEightOfflineSyncTest.php

frontend/
├── src/
│   ├── config/
│   │   └── db.js
│   └── stores/
│       └── networkSync.js