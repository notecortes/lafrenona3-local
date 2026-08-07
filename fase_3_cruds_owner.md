# Especificación de Ejecución Autónoma - Fase 3: CRUDs de Configuración y Menús Multi-idioma del Dueño

## 1. Objetivo de la Fase
Desarrollar el panel de control administrativo (API Endpoints) para el rol de `owner`. Esto incluye la creación de validadores estrictos (*Form Requests*) y controladores para gestionar las categorías traducibles, el catálogo de platos (con alérgenos según Reg. UE 1169/2011 y tarifas de fin de semana), el parque de mesas con tokens criptográficos auto-generados y el alta segura del personal operativo del local (`waiter`, `kitchen`, `bar`).

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software backend senior especializado en arquitecturas API REST robustas. Tu misión es escribir el código correspondiente a la Fase 3.
- Todo el código debe estar estrictamente tipado (PHP 8.2+).
- Utiliza *Form Requests* independientes para aislar la lógica de validación de los controladores.
- Implementa *API Resources* para formatear las salidas JSON de manera uniforme y predecible.
- Los campos de texto mutables (nombre y descripción) deben manejarse como estructuras de arreglos nativos debido al cast `json` definido en las migraciones de la Fase 2.

---

## 3. Estructura de Archivos a Crear
El agente LLM debe generar de forma autónoma la siguiente estructura dentro de `/backend`:
```text
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── Owner/
│   │   │           ├── CategoriesController.php
│   │   │           ├── ProductsController.php
│   │   │           ├── TablesController.php
│   │   │           └── StaffController.php
│   │   ├── Requests/
│   │   │   └── Owner/
│   │   │       ├── StoreCategoryRequest.php
│   │   │       ├── StoreProductRequest.php
│   │   │       ├── StoreTableRequest.php
│   │   │       └── StoreStaffRequest.php
│   │   └── Resources/
│   │       └── Owner/
│   │           ├── CategoryResource.php
│   │           ├── ProductResource.php
│   │           ├── TableResource.php
│   │           └── StaffResource.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseThreeOwnerCrudTest.php