# Especificación de Ejecución Autónoma - Fase 16: Sistema de Reservas Online Multi-tenant, Asignación Inteligente de Mesas y Lista de Espera

## 1. Objetivo de la Fase
Desarrollar el motor de reservas y planificación de capacidad del restaurante. Se creará una migración para añadir la capacidad de comensales a la tabla de mesas y se estructurará la tabla de **Reservas**. Se diseñará un servicio algorítmico (`ReservationEngine`) que analice en tiempo real la disponibilidad de mesas libres basándose en la capacidad requerida y en ventanas de tiempo de exclusión (evitando solapamientos de reservas). Si no hay espacio disponible, el sistema asignará la solicitud a una **Lista de Espera** controlada. Finalmente, se integrará el flujo con la sala: al cambiar la reserva a estado "sentado" (`seated`) desde el panel del camarero, la mesa física se bloqueará automáticamente a "ocupada" (`occupied`) y generará su token dinámico de sesión, permitiendo a los comensales escanear el QR y pedir de inmediato.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Backend senior experto en algoritmos de asignación de recursos, gestión de concurrencia y optimización de flujos transaccionales. Tu misión es dar vida a la Fase 16.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Las consultas de disponibilidad horaria deben usar transacciones SQL (`DB::transaction`) y bloqueos de lectura para impedir que dos clientes reserven la misma mesa para la misma hora simultáneamente (*Double Booking*).
- Queda terminantemente prohibido dejar placeholders o código incompleto; escribe cada archivo en su totalidad.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── database/
│   └── migrations/
│       ├── 2026_01_01_000015_add_capacity_to_tables_table.php
│       └── 2026_01_01_000016_create_reservations_table.php
├── app/
│   ├── Models/
│   │   └── Reservation.php
│   ├── Services/
│   │   └── ReservationEngine.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── Client/
│   │           │   └── ClientReservationController.php
│   │           └── Staff/
│   │               └── StaffReservationController.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseSixteenReservationTest.php