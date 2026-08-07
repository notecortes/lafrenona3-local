# Especificación de Ejecución Autónoma - Fase 11: Panel de Analíticas, Métricas de Rendimiento y Exportación de Datos (Business Intelligence)

## 1. Objetivo de la Fase
Desarrollar el motor de agregación de datos e inteligencia de negocio (BI) exclusivo para el rol `owner`. Se crearán endpoints optimizados a nivel SQL para calcular en tiempo real métricas clave: facturación total por rangos de fecha, ticket promedio por mesa, horas de mayor afluencia y el top de productos más vendidos. Asimismo, se implementará un sistema de transmisión de datos (*Data Streaming*) para exportar listados financieros masivos en formato CSV sin agotar la memoria RAM del servidor.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de datos y desarrollador Backend senior experto en optimización de consultas SQL complejas, agregaciones y sistemas de streaming de archivos. Tu misión es dar vida a la Fase 11.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Queda terminantemente prohibido realizar cálculos de agregación iterando colecciones en PHP (`foreach` para sumar totales); todo debe computarse directamente en el motor de la base de datos mediante funciones agregadas de SQL (`SUM`, `AVG`, `COUNT`, `GROUP BY`).
- No dejes placeholders o comentarios parciales; escribe cada archivo en su totalidad.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Owner/
│   │               └── AnalyticsController.php
│   └── Http/
│       └── Resources/
│           └── Owner/
│               ├── AnalyticsSummaryResource.php
│               └── MostSoldProductResource.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseElevenAnalyticsTest.php