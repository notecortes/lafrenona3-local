# Especificación de Ejecución Autónoma - Fase 15: Gestión de Inventario Automatizado, Escandallos (Recetas) y Alertas de Stock Crítico

## 1. Objetivo de la Fase
Implementar el motor de control de existencias e ingeniería de menús. Se estructurará el inventario base del restaurante a nivel de ingredientes y se creará la tabla relacional de **Escandallos** (las recetas técnicas que vinculan un producto de la carta con los ingredientes y cantidades exactas necesarios para su elaboración). Se desarrollará un servicio interconectado con los WebSockets de cocina (Fase 5) para que, de forma automática y asíncrona, se deduzcan las existencias físicas de la bodega en el momento en que un plato pasa a estado de preparación (`cooking`), registrando el movimiento en un historial de auditoría de inventario y activando alertas si el stock cae por debajo del umbral mínimo.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Backend senior experto en sistemas ERP, optimización de cadenas de suministro, bases de datos relacionales y eventos asíncronos. Tu misión es dar vida a la Fase 15.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Las operaciones de descuento de inventario masivo deben ejecutarse bajo transacciones SQL (`DB::transaction`) y utilizar bloqueos de fila de base de datos (`lockForUpdate`) para evitar condiciones de carrera (*Race Conditions*) si varias mesas piden el mismo plato simultáneamente.
- Queda terminantemente prohibido dejar placeholders o código parcial; escribe cada archivo en su totalidad.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── database/
│   └── migrations/
│       ├── 2026_01_01_000012_create_ingredients_table.php
│       ├── 2026_01_01_000013_create_product_ingredient_pivot_table.php
│       └── 2026_01_01_000014_create_inventory_adjustments_table.php
├── app/
│   ├── Models/
│   │   ├── Ingredient.php
│   │   └── InventoryAdjustment.php
│   ├── Services/
│   │   └── InventoryStockService.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Owner/
│   │               └── InventoryController.php
│   ├── Listeners/
│   │   └── ProcessInventoryDeduction.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseFifteenInventoryEscandalloTest.php