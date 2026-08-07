# Especificación de Ejecución Autónoma - Fase 10: Despliegue Automatizado (CI/CD), Monitoreo de Errores y Copias de Seguridad

## 1. Objetivo de la Fase
Establecer la infraestructura de operaciones (DevOps) para el entorno de producción. Se creará un flujo de integración y despliegue continuo (CI/CD) mediante GitHub Actions que valide los tests y compile los assets automáticamente. Se integrará la captura de excepciones y errores en caliente en Laravel 11 (Simulación de integración con Sentry) y se programará un comando artesanal de consola para automatizar copias de seguridad (Backups) encriptadas de la base de datos y archivos multimedia hacia almacenamiento externo, asegurando la resiliencia del negocio SaaS.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de DevOps y Arquitecto de Infraestructura principal (Principal Cloud Engineer). Tu misión es automatizar la resiliencia y el despliegue en la Fase 10.
- Todo el código PHP debe estar estrictamente tipado (PHP 8.2+).
- Las tareas de programación cronometrada (*Task Scheduling*) deben configurarse siguiendo la nueva estructura de **Laravel 11** utilizando el archivo `routes/console.php`.
- Queda terminantemente prohibido el uso de placeholders o comentarios parciales; escribe cada lógica de inicio a fin.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar de forma autónoma la siguiente estructura de archivos en el proyecto:
```text
raiz-del-proyecto/
└── .github/
    └── workflows/
        └── ci-cd-pipeline.yml

backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── AutoExecuteSaasBackup.php
│   └── Services/
│       └── ErrorMonitoringService.php
├── bootstrap/
│   └── app.php (Modificar)
├── routes/
│   └── console.php (Modificar)
└── tests/
    └── Feature/
        └── PhaseTenDevOpsTest.php