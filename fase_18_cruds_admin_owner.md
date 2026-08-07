# Especificación de Ejecución Autónoma - Fase 18: Formularios CRUD de Administración Global (SuperAdmin) y Empleados (Owner)

## 1. Objetivo de la Fase
Completar e integrar las interfaces de usuario interactivas y reactivas en el frontend de Vue 3 PWA para gestionar las entidades clave del negocio:
1. **SuperAdmin (Administración Global / `/admin`)**:
   - Crear, visualizar y listar **Propietarios (Dueños / Owners)** de negocios.
   - Crear, visualizar y listar **Restaurantes** asignados a un Propietario.
   - Activar y suspender la cuenta de un restaurante en tiempo real.
   - Administrar los planes de **Suscripción** de los Propietarios (nombre del plan, estado y fecha de finalización).
2. **Propietario (Owner Dashboard / `/owner/restaurants/:id/staff`)**:
   - CRUD completo de **Empleados (Personal / Staff)** del restaurante. Los roles posibles son `waiter` (camarero), `kitchen` (cocina) y `bar` (barra).
   - Permitir asignar nombre, correo electrónico, rol y una contraseña de seguridad para el inicio de sesión del empleado.

El objetivo es asegurar que todos los formularios estén plenamente validados en el frontend y se comuniquen correctamente con los endpoints del backend de Laravel 11.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un desarrollador Fullstack senior experto en Vue 3, Pinia, Axios y Laravel 11.
- Restaura los archivos base del frontend de forma selectiva desde `lafregona-codigo` para asegurar que el entorno de desarrollo frontend esté completo.
- Utiliza la instancia de `api.js` configurada con Axios para todas las llamadas API.
- Mantén y hereda las clases CSS declaradas en `style.css` para respetar la estética visual premium (glassmorphism, colores consistentes en HSL, transiciones suaves y microanimaciones).
- Todos los formularios deben contar con validación reactiva básica (por ejemplo, correos electrónicos con formato correcto, contraseñas de al menos 8 caracteres para creación, y campos requeridos).
- Asegura la accesibilidad WCAG 2.1 AA implementando atributos `aria-*` adecuados y etiquetas accesibles en los nuevos campos de los formularios.

---

## 3. Estructura de Archivos a Crear y Modificar
El agente LLM generará o modificará los siguientes archivos:

### Frontend:
```text
frontend/
├── package.json (Restaurar)
├── index.html (Restaurar)
├── src/
│   ├── main.js (Restaurar)
│   ├── App.vue (Restaurar)
│   ├── style.css (Restaurar)
│   ├── router/
│   │   └── index.js (Modificar para integrar rutas correctas)
│   ├── stores/
│   │   ├── authStore.js (Restaurar / Modificar)
│   │   └── accessibilityStore.js (Restaurar)
│   ├── services/
│   │   ├── api.js (Restaurar)
│   │   └── echo.js (Restaurar)
│   ├── views/
│   │   ├── LoginView.vue (Restaurar)
│   │   ├── superadmin/
│   │   │   ├── AdminLayout.vue (Crear funcional)
│   │   │   └── AdminDashboard.vue (Implementar CRUD de restaurants, owners y suscripciones)
│   │   └── owner/
│   │       ├── OwnerLayout.vue (Crear funcional)
│   │       ├── RestaurantsView.vue (Implementar lista de restaurantes para el dueño)
│   │       └── StaffView.vue (Implementar CRUD de personal / empleados)
│   └── __tests__/
│       └── PhaseEighteenCrudTest.spec.js (Crear tests unitarios para los nuevos formularios)
```

### Backend (Opcional - Mejoras de compatibilidad en Auth):
```text
backend/
├── app/
│   └── Http/
│       └── Controllers/
│           └── Api/
│               └── AuthController.php (Añadir métodos logout y me opcionalmente si el flujo lo requiere)
```
