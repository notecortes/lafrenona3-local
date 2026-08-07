# Especificación de Ejecución Autónoma - Fase 14: Pasarela de Pagos Multi-tenant (Stripe Connect), Propinas y Ticket Digital

## 1. Objetivo de la Fase
Cerrar el ciclo transaccional permitiendo al comensal pagar la cuenta directamente desde su smartphone a través del código QR. Se implementará una arquitectura de cobros distribuidos mediante **Stripe Connect (Standard/Custom)** para que cada restaurante reciba sus ingresos de forma aislada en su cuenta bancaria local. Se añadirá soporte para propinas opcionales configuradas por el cliente, gestión de Webhooks seguros para procesar la confirmación del pago de forma asíncrona, automatización del cierre de la mesa, y la generación y envío del recibo/ticket digital por correo electrónico.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software senior experto en integraciones de pasarelas de pago internacionales (FinTech), sistemas de Webhooks asíncronos distribuidos y seguridad transaccional. Tu misión es dar vida a la Fase 14.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Todo el procesamiento de dinero debe encapsularse en transacciones ACID sólidas (`DB::transaction`).
- Los Webhooks de Stripe deben verificar obligatoriamente la firma criptográfica (`Stripe-Signature`) para evitar ataques de suplantación de pagos.
- No se permiten placeholders ni código parcial; genera todas las lógicas completas.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos dentro de `/backend`:
```text
backend/
├── database/
│   └── migrations/
│       └── 2026_01_01_000011_add_stripe_and_billing_fields_to_tables.php
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── Client/
│   │           │   └── ClientPaymentController.php
│   │           └── Webhooks/
│   │               └── StripeWebhookController.php
│   └── Mail/
│       └── DigitalInvoiceMail.php
├── routes/
│   └── api.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseFourteenPaymentGatewayTest.php