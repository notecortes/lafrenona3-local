# Especificación de Ejecución Autónoma - Fase 4: Infraestructura de Tiempo Real con Laravel Reverb

## 1. Objetivo de la Fase
Instalar, configurar e implementar la infraestructura de comunicación bidireccional en tiempo real (WebSockets) utilizando **Laravel Reverb**. Se crearán los canales protegidos para el personal, los canales públicos estructurados para las mesas de los clientes no autenticados y los eventos encargados de propagar los cambios de estado de cocina, nuevas comandas y liberaciones de mesa de forma instantánea.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software backend senior experto en sistemas distribuidos y arquitecturas reactivas en tiempo real. Tu misión es configurar el ecosistema de WebSockets en la Fase 4.
- Todo el código debe estar estrictamente tipado (PHP 8.2+).
- Los eventos deben implementar `Illuminate\Contracts\Broadcasting\ShouldBroadcastNow` para forzar la ejecución inmediata a través del servidor de WebSockets Reverb sin pasar de forma obligatoria por retrasos de colas en desarrollo local.
- No utilices placeholders ni comentarios parciales.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe asegurar o generar de forma autónoma la siguiente estructura dentro de `/backend`:
```text
backend/
├── app/
│   └── Events/
│       ├── OrderItemCreated.php
│       ├── OrderStateChanged.php
│       └── TableCleared.php
├── config/
│   └── broadcasting.php (Modificar/Verificar)
├── routes/
│   └── channels.php (Modificar/Crear)
└── tests/
    └── Feature/
        └── PhaseFourBroadcastTest.php