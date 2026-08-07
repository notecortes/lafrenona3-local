# Especificación de Ejecución Autónoma - Fase 7: Creación de Pedidos, Snapshots de Precios e Idempotencia

## 1. Objetivo de la Fase
Construir el núcleo transaccional y financiero de los pedidos. Se desarrollará el endpoint público para que los clientes añadan platos de forma cooperativa a su mesa, implementando un mecanismo de *Price Snapshot* (guardado estático del precio del plato en el momento exacto del pedido, contemplando tarifas de fin de semana) para blindar los históricos ante futuras modificaciones de la carta. Asimismo, se prohibirá el acceso de modificación/borrado a usuarios con rol cliente y se creará el endpoint de cierre de comanda y liberación de mesa para el personal.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Backend senior especializado en sistemas transaccionales, auditoría financiera y optimización de bases de datos relacionales. Tu misión es dar vida a la Fase 7.
- Todo el código debe estar estrictamente tipado (PHP 8.2+).
- Envuelve las operaciones de escritura múltiple dentro de transacciones de base de datos (`DB::transaction`) para asegurar consistencia ACID estricta.
- Queda terminantemente prohibido dejar placeholders o comentarios parciales; escribe cada método en su totalidad.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── Client/
│   │   │       │   └── ClientOrdersController.php
│   │   │       └── Staff/
│   │   │           └── StaffBillingController.php
│   │   └── Requests/
│   │       └── Client/
│   │           └── AppendOrderItemsRequest.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseSevenOrderTransactionTest.php