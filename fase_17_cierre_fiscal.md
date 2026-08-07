# Especificación de Ejecución Autónoma - Fase 17: Arqueo de Caja General (Informe Z) y Módulo Fiscal Antifraude

## 1. Objetivo de la Fase
Implementar la última capa de control financiero, auditoría de barra/sala y cumplimiento legal. Se creará la infraestructura para gestionar **Sesiones de Caja (Turnos)**, permitiendo controlar el efectivo de apertura, el esperado frente al real, y los cierres de jornada (**Informe Z**). Asimismo, se desarrollará un **Módulo Fiscal Antifraude** basado en encadenamiento criptográfico (*Hash Chaining* tipo VeriFactu/TicketBAI): cada comanda facturada generará un registro fiscal inmutable que firmará digitalmente el contenido del ticket y lo encadenará al hash del ticket anterior, impidiendo cualquier alteración o borrado retroactivo de las ventas.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software principal experto en sistemas ERP contables, criptografía aplicada y auditoría fiscal del estado. Tu misión es dar vida a la Fase 17.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Las operaciones de firma fiscal y cierre de caja deben ser puramente atómicas y estar envueltas en transacciones SQL (`DB::transaction`).
- El cálculo de totales e informes Z debe computarse al 100% en el motor de base de datos para evitar desbordamientos de memoria.
- Queda prohibido dejar placeholders o lógica fragmentada; escribe todos los archivos completos.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── database/
│   └── migrations/
│       ├── 2026_01_01_000017_create_cash_sessions_table.php
│       └── 2026_01_01_000018_create_fiscal_records_table.php
├── app/
│   ├── Models/
│   │   ├── CashSession.php
│   │   └── FiscalRecord.php
│   ├── Services/
│   │   └── FiscalChainingService.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Staff/
│   │               ├── CashSessionController.php
│   │               └── FiscalInvoiceController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseSeventeenFiscalCloseTest.php