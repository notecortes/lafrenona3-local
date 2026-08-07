# Diseño UX/UI — lafrenona3

## 1. Objetivo de producto y usuarios

### Propósito
Crear la experiencia digital de referencia para la gestión de restaurantes en España y Latinoamérica: rápida, accesible, profesional y que funcione en cualquier dispositivo y condición de red.

### Usuarios prioritarios

| Perfil | Dispositivo principal | Contexto | Prioridad |
|---|---|---|---|
| Cliente final | Móvil (iOS/Android) | Mesa, escanea QR, quiere pedir rápido | P0 — Flujo crítico |
| Camarero/a | Tablet o móvil propio | Sala, camina, necesita ver alertas y pedidos | P1 |
| Cocina/Barra | Pantalla fija horizontal (1024px+) | Ruido, presión, lectura a distancia | P1 |
| Owner/Propietario | Escritorio + móvil ocasional | Gestión, carta, personal, analíticas | P2 |
| SuperAdmin | Escritorio | Plataforma multi-tenant, suscripciones | P2 |

### Métricas de éxito UX

- Cliente: primer pedido en ≤ 90 segundos desde apertura de la carta.
- Cocina: cambio de estado de item en ≤ 2 segundos.
- Sala: ver estado de todas las mesas en ≤ 3 segundos.
- Accesibilidad: puntuación axe ≥ 95 en WCAG 2.1 AA.
- Performance: LCP ≤ 2.5s en 3G lento para la carta pública.

---

## 2. Principios UX

1. **Menos toques = mejor.** Cada pantalla debe poder usarse con una mano en móvil.
2. **Nunca ocultar información crítica.** Precios, alérgenos y disponibilidad siempre visibles.
3. **Feedback inmediato.** Cada acción tiene confirmación visual y `aria-live`.
4. **Offline-first para operación.** Sala y cocina deben seguir funcionando sin red.
5. **Jerarquía por peso, no solo color.** Estados usan forma + texto + color.
6. **Mobile-first para cliente, desktop-first para admin.**
7. **Cero sorpresas.** El usuario siempre sabe dónde está, qué puede hacer y qué pasa después.

---

## 3. Sistema visual

### Paleta de colores base

| Token | Valor | Uso |
|---|---|---|
| `--color-bg` | `#FAF8F5` | Fondo principal (crema cálido) |
| `--color-bg-secondary` | `#FFFFFF` | Tarjetas, modales |
| `--color-bg-tertiary` | `#F0EDE8` | Separadores, fondos secundarios |
| `--color-text` | `#1C1917` | Texto principal |
| `--color-text-muted` | `#57534E` | Texto secundario, descriptivo |
| `--color-text-inverse` | `#FFFFFF` | Texto sobre fondo oscuro |
| `--color-success` | `#16A34A` | Confirmaciones, disponible |
| `--color-warning` | `#D97706` | Atención, pendiente |
| `--color-error` | `#DC2626` | Error, cancelado |
| `--color-info` | `#2563EB` | Información, enlaces |
| `--color-kitchen` | `#EA580C` | Cocina, área de preparación |
| `--color-bar` | `#7C3AED` | Barra, bebidas |
| `--color-focus` | `#0369A1` | Focus visible (alto contraste) |

### Color primario por restaurante

El restaurante puede definir su color primario vía variable CSS en el panel Owner:

```css
--color-primary: #D4600A;  /* Default cálido */
--color-primary-light: #FED7AA;
--color-primary-dark: #9A3412;
```

### Tipografía

| Nivel | Uso | Tamaño | Peso |
|---|---|---|---|
| Display | Títulos de página | 2rem (32px) | 700 |
| H1 | Secciones | 1.5rem (24px) | 600 |
| H2 | Subsecciones | 1.25rem (20px) | 600 |
| Body | Texto general | 1rem (16px) | 400 |
| Small | Etiquetas, hints | 0.875rem (14px) | 400 |
| XSmall | Metadatos | 0.75rem (12px) | 400 |

**Familia:** `system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif`

### Iconografía

- Usar iconos SVG inline con `aria-hidden="true"` + texto visible.
- Nunca usar solo iconos para acciones críticas.
- Biblioteca recomendada: Heroicons (24px default).

### Componentes base

#### Botones

| Variante | Uso | Estilo |
|---|---|---|
| `primary` | Acción principal (enviar pedido, crear) | Fondo `--color-primary`, texto blanco |
| `secondary` | Acción secundaria (cancelar, volver) | Borde 2px `--color-primary`, fondo transparente |
| `danger` | Acción destructiva (eliminar, suspender) | Fondo `--color-error`, texto blanco |
| `ghost` | Acciones terciarias (editar en tabla) | Solo texto, hover con fondo sutil |
| `icon` | Acciones compactas en tablas | Solo icono, mínimo 44x44px touch target |

#### Tarjetas

- Fondo blanco, borde sutil 1px `--color-bg-tertiary`, radio 12px.
- Sombra suave: `0 1px 3px rgba(0,0,0,0.08)`.
- Hover: elevación ligera, nunca cambia funcionalidad.

#### Estados semánticos

| Estado | Color | Forma | Texto |
|---|---|---|---|
| Éxito | Verde `#16A34A` | Checkmark, borde verde | "Confirmado", "Listo" |
| Atención | Ámbar `#D97706` | Triángulo, borde ámbar | "Pendiente", "Preparando" |
| Error | Rojo `#DC2626` | X, borde rojo | "Error", "Cancelado" |
| Info | Azul `#2563EB` | Info, borde azul | "Enviado", "Sincronizando" |
| Cocina | Naranja `#EA580C` | Fuego, borde naranja | "Cocina" |
| Barra | Violeta `#7C3AED` | Copa, borde violeta | "Barra" |

---

## 4. Breakpoints y comportamiento responsive

| Breakpoint | Rango | Dispositivo | Clases CSS |
|---|---|---|---|
| `xs` | 320–479px | Móvil pequeño | `.client-menu` |
| `sm` | 480–767px | Móvil grande | `.client-menu` |
| `md` | 768–1023px | Tablet / Sala | `.staff-dashboard` |
| `lg` | 1024–1279px | Escritorio / Cocina | `.kds-display`, `.admin-layout` |
| `xl` | 1280px+ | Pantallas grandes | `.kds-display` |

**Reglas:**
- Clientes: nunca scroll horizontal, botones táctiles mínimo 48x48px.
- Sala: layout adaptativo, táctil pero con mouse disponible.
- Cocina: solo horizontal, elementos mínimo 64px, texto legible a 2m.
- Admin: sidebar colapsable en pantallas < 1280px.

---

## 5. Arquitectura de información

### Navegación por perfil

```
Cliente QR (público)
├── Carta (landing)
│   ├── Categorías (scroll horizontal sticky)
│   ├── Productos por categoría
│   └── Buscador / Filtros
├── Carrito (drawer desde cualquier pantalla)
│   ├── Líneas de pedido
│   ├── Notas por línea
│   └── Total + Enviar pedido
├── Mi pedido (post-pedido)
│   ├── Timeline de estados
│   ├── Estado por item
│   └── Acciones: llamar camarero, pedir cuenta
└── Pago (si disponible)
    ├── Resumen
    ├── Propina
    └── Confirmación + ticket

Sala (staff)
├── Login
├── Dashboard / Mapa de sala
│   ├── Grid/plano de mesas
│   ├── Alertas activas
│   └── Filtros
├── Detalle de mesa
│   ├── Estado
│   ├── Pedido actual
│   └── Acciones rápidas
├── Reservas
└── Sincronización offline

Cocina / Barra (KDS)
├── Login
├── Vista principal (columnas por estado)
│   ├── Pendiente
│   ├── Preparando
│   └── Listo
├── Historial reciente
└── Configuración

Owner
├── Login
├── Dashboard (resumen)
├── Carta
│   ├── Categorías
│   └── Productos
├── Mesas
├── Personal
├── Inventario
├── Reservas
├── Analíticas
├── Auditoría
└── Configuración

SuperAdmin
├── Login
├── Dashboard global
├── Restaurantes
├── Usuarios
├── Suscripciones
└── Suspensión / Activación
```

---

## 6. Mapa de rutas

### Cliente (público)

| Ruta | Componente | Auth | Descripción |
|---|---|---|---|
| `/client/menu` | `ClientMenuView.vue` | No | Carta del restaurante |
| `/client/menu/:token` | `ClientMenuView.vue` | No | Carta con token de mesa |
| `/client/order/:id` | `ClientOrderView.vue` | No | Seguimiento de pedido |
| `/client/payment/:id` | `ClientPaymentView.vue` | No | Pago (si disponible) |

### Sala (staff)

| Ruta | Componente | Auth | Rol |
|---|---|---|---|
| `/staff/login` | `StaffLoginView.vue` | No | Cualquiera |
| `/staff/room` | `StaffRoomView.vue` | Sí | waiter, kitchen, bar |
| `/staff/tables/:id` | `StaffTableView.vue` | Sí | waiter, kitchen, bar |
| `/staff/reservations` | `StaffReservationsView.vue` | Sí | waiter |

### Cocina (KDS)

| Ruta | Componente | Auth | Rol |
|---|---|---|---|
| `/kds/login` | `KdsLoginView.vue` | No | kitchen, bar |
| `/kds/dashboard` | `KdsDashboardView.vue` | Sí | kitchen, bar |

### Owner

| Ruta | Componente | Auth | Rol |
|---|---|---|---|
| `/owner/login` | `OwnerLoginView.vue` | No | owner |
| `/owner/dashboard` | `OwnerDashboardView.vue` | Sí | owner |
| `/owner/menu` | `OwnerMenuView.vue` | Sí | owner |
| `/owner/menu/categories` | `OwnerCategoriesView.vue` | Sí | owner |
| `/owner/menu/products` | `OwnerProductsView.vue` | Sí | owner |
| `/owner/tables` | `OwnerTablesView.vue` | Sí | owner |
| `/owner/staff` | `OwnerStaffView.vue` | Sí | owner |
| `/owner/inventory` | `OwnerInventoryView.vue` | Sí | owner |
| `/owner/reservations` | `OwnerReservationsView.vue` | Sí | owner |
| `/owner/analytics` | `OwnerAnalyticsView.vue` | Sí | owner |
| `/owner/audit` | `OwnerAuditView.vue` | Sí | owner |
| `/owner/settings` | `OwnerSettingsView.vue` | Sí | owner |

### SuperAdmin

| Ruta | Componente | Auth | Rol |
|---|---|---|---|
| `/admin/login` | `AdminLoginView.vue` | No | superadmin |
| `/admin/dashboard` | `AdminDashboardView.vue` | Sí | superadmin |
| `/admin/restaurants` | `AdminRestaurantsView.vue` | Sí | superadmin |
| `/admin/users` | `AdminUsersView.vue` | Sí | superadmin |
| `/admin/subscriptions` | `AdminSubscriptionsView.vue` | Sí | superadmin |

---

## 7. Flujos principales

### Flujo 1: Cliente QR — Hacer un pedido (P0)

```
Escaneo QR
  ↓
Validación de sesión de mesa
  ↓
Carta del restaurante (categorías + productos)
  ↓
Navegar categorías (scroll horizontal sticky)
  ↓
Ver producto (tarjeta con foto, nombre, precio, alérgenos)
  ↓
Tocar "Añadir" (o "Ver detalle" → Añadir)
  ↓
Carrito se actualiza (drawer inferior o badge)
  ↓
Repetir añadir productos
  ↓
Tocar carrito → Revisar líneas
  ↓
Ajustar cantidades, añadir notas
  ↓
Tocar "Enviar pedido"
  ↓
Confirmación: "Pedido #123 enviado"
  ↓
Vista "Mi pedido" con timeline de estados
  ↓
Acciones secundarias: llamar camarero / pedir cuenta / pagar
```

### Flujo 2: Sala — Ver mapa de sala

```
Login
  ↓
Dashboard sala (grid de mesas)
  ↓
Ver estado de cada mesa (libre, ocupada, alerta, cuenta)
  ↓
Filtrar por estado o zona
  ↓
Tocar mesa → Ver detalle (pedido, tiempo, notas)
  ↓
Acciones: atender alerta, abrir mesa, ver pedido
  ↓
Actualizar en tiempo real vía WebSocket
```

### Flujo 3: Cocina — Cambiar estado de pedido

```
Login
  ↓
KDS Dashboard (columnas: Pendiente → Preparando → Listo)
  ↓
Ver tarjetas grandes (mesa, items, cantidad, notas, tiempo)
  ↓
Priorización por antigüedad (más viejo arriba)
  ↓
Tocar "Preparando" o "Listo"
  ↓
Confirmación visual inmediata
  ↓
Tarjeta se mueve a siguiente columna
```

### Flujo 4: Owner — Gestionar carta

```
Login
  ↓
Dashboard (resumen rápido)
  ↓
Navegar a Carta → Productos
  ↓
Tabla con búsqueda, filtros, orden
  ↓
Tocar "+ Nuevo producto"
  ↓
Formulario con validación progresiva
  ↓
Guardar → Confirmación
  ↓
Producto visible en carta pública inmediatamente
```

---

## 8. Wireframes ASCII

### 8.1 Cliente — Carta (móvil 375px)

```
┌──────────────────────────────┐
│ 🏠 Restaurante X    👤 Mesa 5│  ← Header compacto
│ 📶 Conectado          🛒 3€12│     (nombre + mesa + carrito)
├──────────────────────────────┤
│ [🔍 Buscar...]               │  ← Buscador
├──────────────────────────────┤
│ ◀ Entradas | Principales | Postres ▶│ ← Categorías sticky
├──────────────────────────────┤
│                              │
│ ┌──────────────────────────┐ │
│ │ [IMG]  Paella Valenciana │ │  ← Tarjeta producto
│ │        Arroz, marisco... │ │
│ │        🌾 🐟 🥛          │ │  ← Alérgenos
│ │        14.50€   [+ Añadir]│ │
│ └──────────────────────────┘ │
│                              │
│ ┌──────────────────────────┐ │
│ │ [IMG]  Ensalada César    │ │
│ │        Lechuga, pollo... │ │
│ │        🌿 🥛             │ │
│ │        9.50€    [+ Añadir]│ │
│ └──────────────────────────┘ │
│                              │
│ ┌──────────────────────────┐ │
│ │  Agotado                 │ │  ← Producto no disponible
│ │  Tarta de queso          │ │  (gris, no accionable)
│ │  8.00€                   │ │
│ └──────────────────────────┘ │
│                              │
├──────────────────────────────┤
│ [🛒 Ver carrito (3 items - 12.50€)]│ ← Sticky bottom
└──────────────────────────────┘
```

### 8.2 Cliente — Carrito (drawer)

```
┌──────────────────────────────┐
│  Mi pedido              ✕    │
├──────────────────────────────┤
│                              │
│ ┌──────────────────────────┐ │
│ │ [🥘] Paella Valenciana   │ │
│ │     x2         -1  +1   │ │  ← Ajustar cantidad
│ │     Not