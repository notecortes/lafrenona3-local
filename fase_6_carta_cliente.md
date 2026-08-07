# Especificación de Ejecución Autónoma - Fase 6: UI de la Carta del Cliente, Accesibilidad (WCAG 2.1) y PWA

## 1. Objetivo de la Fase
Desarrollar la interfaz pública del comensal (Cliente) en Vue 3. Se configurará el empaquetador Vite para fragmentar el código (*Code Splitting*) y aislar el paquete del cliente público para lograr una carga instantánea (<100kb gzipped) en entornos de baja cobertura celular. La interfaz será una PWA (Progressive Web App) e implementará de forma nativa las directrices de accesibilidad **WCAG 2.1 AA** (textos escalables en `rem`, alto contraste y semántica ARIA) junto con traducción automática multi-idioma basada en el navegador del usuario.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software Frontend senior experto en optimización de rendimiento web, accesibilidad universal (A11y) y desarrollo de Progressive Web Apps. Tu misión es dar vida a la Fase 6.
- El código JavaScript/Vue debe utilizar la API de Composición (`<script setup>`) de Vue 3.
- Los estilos deben ser puros o modulares utilizando variables CSS inyectadas dinámicamente desde el root (`:root`).
- Se prohíbe terminantemente el uso de píxeles (`px`) en fuentes, paddings o márgenes críticos; todo debe computarse en unidades relativas (`rem`, `em`, `%`) para permitir el escalado.
- Todos los elementos interactivos deben poseer etiquetas accesibles explícitas para lectores de pantalla.

---

## 3. Estructura de Archivos a Crear o Modificar
El agente LLM debe generar o modificar de forma autónoma la siguiente estructura de archivos en el `/frontend`:
```text
frontend/
├── vite.config.js (Modificar)
├── src/
│   ├── stores/
│   │   └── clientMenu.js
│   ├── views/
│   │   └── client/
│   │       └── MenuView.vue
│   └── __tests__/
│       └── PhaseSixAccessibilityTest.spec.js