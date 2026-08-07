# Plan de Implementación Frontend — lafrenona3

## 1. Estado actual detectado del frontend

### Lo que existe

| Archivo | Estado | Funcionalidad |
|---|---|---|
| `src/router/index.js` | Básico | 6 rutas (login, client/menu, admin, owner). Guards básicos. |
| `src/services/api.js` | Básico | Axios con token Bearer. Interceptor 401. Timeout 10s. |
| `src/stores/authStore.js` | Funcional | Login, logout (fixado), fetchUser. Getters de rol. |
| `src/stores/clientMenu.js` | Básico | fetchMenu, categorías, productos, locale. |
| `src/views/client/MenuView.vue` | Mínimo | Muestra carta por categorías. Sin carrito, sin búsqueda, sin filtros. |
| `src/views/LoginView.vue` | Funcional | Login con validación. Redirige a admin/owner. |
| `src/views/superadmin/AdminLayout.vue` | Básico | Sidebar + router-view. Navegación a dashboard/restaurants/users. |
| `src/views/superadmin/AdminDashboard.vue` | Funcional | Tabla de restaurantes con CRUD básico. Modal crear. |
| `src/views/owner/OwnerLayout.vue` | Básico | Sidebar + router-view. Navegación a staff/restaurants. |
| `src/views/owner/StaffView.vue` | Funcional | Tabla staff con CRUD. Modal crear/editar. |
| `src/views/owner/RestaurantsView.vue` | Mínimo | Grid de restaurantes. |
| `src/style.css` | Básico | Variables CSS, reset, focus, reduced-motion. |
| `pwa-register.js` | Parcial | PWA con callbacks vacíos. |
| `public/manifest.webmanifest` | Básico | Manifest PWA funcional. |

### Lo que NO existe

| Componente | Estado |
|---|---|
| Carrito de cliente | ❌ No existe |
| Búsqueda/filtros de carta | ❌ No existen |
| Vista "Mi pedido" | ❌ No existe |
| Vista de pago | ❌ No existe |
| Llamar al camarero (UI) | ❌ No existe |
| Pedir cuenta (UI) | ❌ No existe |
| Dashboard de sala (staff) | ❌ No existe |
| KDS (cocina) | ❌ No existe (solo KitchenMonitor.vue suelto) |
| Dashboard Owner | ❌ No existe |
| Gestión de carta (categorías/productos UI) | ❌ No existe |
| Gestión de mesas (UI) | ❌ No existe |
| Inventario (UI) | ❌ No existe |
| Reservas (UI) | ❌ No existe |
| Analíticas (UI) | ❌ No existe |
| Modal de detalle de producto | ❌ No existe |
| Skeleton loaders | ❌ No existen |
| Estado offline visible | ❌ No existe |
| Laravel Echo integration | ❌ No existe |
| Composables reutilizables | ❌ No existen |
| Componentes UI reutilizables | ❌ No existen |

### Problemas actuales

1. **Carta pública sin carrito**: El usuario no puede añadir productos ni revisar el pedido.
2. **Sin búsqueda ni filtros**: Solo navegación por categorías con filtrado en JS (lento si crece).
3. **Sin gestión de pedidos**: No hay vista post-pedido con estados.
4. **Admin sin navegación real**: Routes `/admin/restaurants` y `/admin/users` renderizan el mismo componente.
5. **Owner limitado**: Solo staff y restaurantes. Falta carta, mesas, inventario, analíticas.
6. **Sin KDS**: `KitchenMonitor.vue` existe pero no está en el router.
7. **Sin sala**: No hay dashboard de mesas para camareros.
8. **Estilos genéricos**: Usa variables CSS básicas sin sistema de diseño definido.
9. **Sin lazy loading por área**: Todo se carga junto.
10. **Sin manejo de errores de API**: Solo un mensaje genérico.

---

## 2. Archivos existentes reutilizables

| Archivo | Reutilizar | Notas |
|---|---|---|
| `src/services/api.js` | ✅ Sí | Mejorar interceptores, añadir retry logic |
| `src/stores/authStore.js` | ✅ Sí | Funcional, solo ajustar redirecciones |
| `src/stores/clientMenu.js` | ✅ Sí | Añadir carrito, estados de sync |
| `src/views/LoginView.vue` | ✅ Sí | Reutilizar como base para todos los logins |
| `src/style.css` | ✅ Sí | Expandir sistema de diseño |
| `public/manifest.webmanifest` | ✅ Sí | Actualizar nombre/descripción |
| `pwa-register.js` | ✅ Sí | Ya configurado con vite-plugin-pwa |

---

## 3. Archivos a crear o modificar

### 3.1 Archivos a crear (nuevos)

#### Estructura de directorios

```
frontend/src/
├── app/                          # Nuevo
│   ├── App.vue                   # Modificar (envolver con error boundary)
│   └── main.js                   # Modificar (añadir PWA)
├── router/                       # Nuevo (reemplazar index.js)
│   └── index.js                  # Router con lazy loading por área
├── layouts/                      # Nuevo
│   ├── PublicLayout.vue          # Cliente QR (sin sidebar)
│   ├── StaffLayout.vue           # Sala (responsive)
│   ├── KdsLayout.vue             # Cocina (pantalla completa)
│   ├── AdminLayout.vue           # Owner (sidebar colapsable)
│   └── SuperAdminLayout.vue      # SuperAdmin (sidebar)
├── views/
│   ├── client/                   # Nuevo
│   │   ├── ClientMenuView.vue    # Reemplazar existente
│   │   ├── ClientOrderView.vue   # Seguimiento de pedido
│   │   └── ClientPaymentView.vue # Pago (si disponible)
│   ├── staff/                    # Nuevo
│   │   ├── StaffLoginView.vue    # Reutilizar de LoginView
│   │   ├── StaffRoomView.vue     # Dashboard de sala
│   │   └── StaffTableView.vue    # Detalle de mesa
│   ├── kitchen/                  # Nuevo
│   │   ├── KdsLoginView.vue      # Reutilizar de LoginView
│   │   └── KdsDashboardView.vue  # KDS principal
│   ├── owner/                    # Expandir
│   │   ├── OwnerLoginView.vue    # Reutilizar de LoginView
│   │   ├── OwnerDashboardView.vue
│   │   ├── OwnerMenuView.vue
│   │   ├── OwnerCategoriesView.vue
│   │   ├── OwnerProductsView.vue
│   │   ├── OwnerTablesView.vue
│   │   ├── OwnerInventoryView.vue
│   │   ├── OwnerReservationsView.vue
│   │   ├── OwnerAnalyticsView.vue
│   │   └── OwnerSettingsView.vue
│   └── superadmin/               # Expandir
│       ├── AdminLoginView.vue    # Reutilizar de LoginView
│       ├── AdminDashboardView.vue
│       ├── AdminRestaurantsView.vue
│       ├── AdminUsersView.vue
│       └── AdminSubscriptionsView.vue
├── components/
│   ├── ui/                       # Nuevo
│   │   ├── Button.vue
│   │   ├── Input.vue
│   │   ├── Modal.vue
│   │   ├── Skeleton.vue
│   │   ├── StatusBadge.vue
│   │   ├── EmptyState.vue
│   │   ├── ErrorState.vue
│   │   ├── ConnectionStatus.vue
│   │   └── Toast.vue
│   ├── client/                   # Nuevo
│   │   ├── ProductCard.vue
│   │   ├── ProductDetail.vue
│   │   ├── CartDrawer.vue
│   │   ├── CartItem.vue
│   │   ├── CategoryNav.vue
│   │   ├── OrderTimeline.vue
│   │   └── AssistanceButton.vue
│   ├── staff/                    # Nuevo
│   │   ├── RoomGrid.vue
│   │   ├── TableCard.vue
│   │   └── AlertList.vue
│   ├── kitchen/                  # Nuevo
│   │   ├── KdsColumn.vue
│   │   ├── KdsCard.vue
│   │   └── KdsTimer.vue
│   └── admin/                    # Nuevo
│       ├── DataTable.vue
│       ├── FormField.vue
│       ├── Pagination.vue
│       ├── SearchBar.vue
│       └── StatCard.vue
├── stores/                       # Expandir
│   ├── authStore.js              # Modificar
│   ├── clientMenu.js             # Modificar (añadir carrito)
│   ├── cartStore.js              # Nuevo
│   ├── connectionStore.js        # Nuevo
│   └── alertStore.js             # Nuevo
├── services/                     # Expandir
│   ├── api.js                    # Modificar
│   └── echoService.js            # Nuevo
├── composables/                  # Nuevo
│   ├── useConnection.js
│   ├── useCart.js
│   ├── useAccessibility.js
│   ├── useNotifications.js
│   └── useWebSocket.js
├── config/                       # Nuevo
│   └── designTokens.js
├── utils/                        # Nuevo
│   ├── formatPrice.js
│   ├── formatRole.js
│   ├── statusColors.js
│   └── idempotency.js
└── styles/                       # Nuevo
    ├── design-tokens.css
    ├── base.css
    ├── components.css
    └── utilities.css
```

### 3.2 Archivos a modificar

| Archivo | Cambio |
|---|---|
| `src/router/index.js` | Reemplazar con lazy loading por área, nuevas rutas |
| `src/services/api.js` | Añadir retry, timeout configurable, response normalizer |
| `src/stores/authStore.js` | Ajustar redirecciones por rol |
| `src/stores/clientMenu.js` | Añadir carrito, estados de sync |
| `src/style.css` | Expandir sistema de diseño completo |
| `pwa-register.js` | Ya configurado (sin cambios) |

---

## 4. Orden de implementación recomendado

### Fase 1: Cimientos (semana 1)

1. Sistema de diseño CSS (`design-tokens.css`, `base.css`, `components.css`)
2. Componentes UI base (`Button`, `Input`, `Modal`, `Skeleton`, `StatusBadge`, `EmptyState`, `ErrorState`)
3. Mejorar `api.js` (interceptores, retry, normalización)
4. Composables (`useConnection`, `useCart`, `useAccessibility`)
5. Stores nuevos (`cartStore`, `connectionStore`, `alertStore`)

### Fase 2: Cliente QR (semana 2-3)

6. `ClientMenuView.vue` (carta completa con búsqueda, filtros, categorías sticky)
7. `ProductCard.vue` + `ProductDetail.vue` (bottom sheet modal)
8. `CartDrawer.vue` + `CartItem.vue` (carrito persistente, cantidades, notas)
9. `ClientOrderView.vue` (timeline de estados post-pedido)
10. `AssistanceButton.vue` (llamar camarero / pedir cuenta)
11. `ClientPaymentView.vue` (pago con Stripe, propina, confirmación)

### Fase 3: Sala y Cocina (semana 4-5)

12. `StaffLoginView.vue` + `StaffLayout.vue`
13. `StaffRoomView.vue` (mapa de sala, grid de mesas)
14. `StaffTableView.vue` (detalle de mesa, pedido actual)
15. `KdsLoginView.vue` + `KdsLayout.vue`
16. `KdsDashboardView.vue` (columnas por estado, tarjetas grandes)
17. `RoomGrid.vue`, `TableCard.vue`, `AlertList.vue`
18. `KdsColumn.vue`, `KdsCard.vue`, `KdsTimer.vue`

### Fase 4: Owner y SuperAdmin (semana 6-7)

19. `AdminLayout.vue` (reemplazar existente, sidebar colapsable)
20. `AdminDashboardView.vue` (mejorar con estadísticas)
21. `OwnerDashboardView.vue` (resumen del restaurante)
22. `OwnerMenuView.vue` + `OwnerCategoriesView.vue` + `OwnerProductsView.vue`
23. `OwnerTablesView.vue`, `OwnerInventoryView.vue`, `OwnerReservationsView.vue`
24. `OwnerAnalyticsView.vue`, `OwnerSettingsView.vue`
25. `AdminRestaurantsView.vue`, `AdminUsersView.vue`, `AdminSubscriptionsView.vue`

### Fase 5: Integración y pulido (semana 8)

26. Laravel Echo integration (tiempo real para sala y cocina)
27. Offline sync UI (cola local, estados de sincronización)
28. Testing completo (Vitest, Playwright, axe)
29. Performance optimization (code splitting, lazy loading)
30. Accesibilidad audit (axe, keyboard navigation)

---

## 5. Contratos API requeridos y estado de disponibilidad

### Cliente (público)

| Endpoint | Método | Estado | Uso en UI |
|---|---|---|---|
| `/v1/client/menu` | GET | ✅ Disponible | Carta, categorías, productos |
| `/v1/client/orders` | POST | ✅ Disponible | Crear pedido |
| `/v1/client/orders/{id}/items` | POST | ✅ Disponible | Añadir items |
| `/v1/client/orders/{id}/close` | POST | ✅ Disponible | Cerrar pedido |
| `/v1/client/assistance` | POST | ✅ Disponible | Llamar camarero / pedir cuenta |
| `/v1/client/payments/initiate` | POST | ⚠️ Parcial | Pago (simulado) |
| `/v1/client/reservations` | POST | ✅ Disponible | Crear reserva |
| `/v1/client/reservations/{id}` | GET | ✅ Disponible | Ver reserva |

### Staff

| Endpoint | Método | Estado | Uso en UI |
|---|---|---|---|
| `/v1/staff/order-items/pending` | GET | ✅ Disponible | KDS, sala |
| `/v1/staff/order-items/{id}/status` | PUT | ✅ Disponible | Cambiar estado |
| `/v1/staff/order-items/bulk` | PUT | ✅ Disponible | Actualización masiva |
| `/v1/staff/orders/{id}/close` | POST | ✅ Disponible | Cerrar pedido |
| `/v1/staff/room` | GET | ✅ Disponible | Mapa de sala |
| `/v1/staff/sync/offline` | POST | ✅ Disponible | Sincronización offline |
| `/v1/staff/reservations/{id}/seat` | POST | ✅ Disponible | Asentar reserva |
| `/v1/staff/cash-sessions` | GET/POST | ✅ Disponible | Sesiones de caja |
| `/v1/staff/cash-sessions/{id}/close` | POST | ✅ Disponible | Cerrar caja |
| `/v1/staff/fiscal-records` | GET | ✅ Disponible | Registros fiscales |

### Owner

| Endpoint | Método | Estado | Uso en UI |
|---|---|---|---|
| `/v1/owner/restaurants` | GET | ⚠️ Parcial | Devuelve `restaurant_id` (no lista) |
| `/v1/owner/categories` | GET/POST/PUT/DELETE | ✅ Disponible | CRUD categorías |
| `/v1/owner/products` | GET/POST/PUT/DELETE | ✅ Disponible | CRUD productos |
| `/v1/owner/products/categories` | GET | ✅ Disponible | Categorías de productos |
| `/v1/owner/tables` | GET/POST/PUT/DELETE | ✅ Disponible | CRUD mesas |
| `/v1/owner/staff` | GET/POST/PUT/DELETE | ✅ Disponible | CRUD personal |
| `/v1/owner/analytics/summary` | GET | ✅ Disponible | Analíticas resumen |
| `/v1/owner/analytics/top-products` | GET | ✅ Disponible | Top productos |
| `/v1/owner/analytics/export/csv` | GET | ✅ Disponible | Exportar CSV |
| `/v1/owner/audit-logs` | GET | ✅ Disponible | Logs de auditoría |
| `/v1/owner/inventory` | GET | ✅ Disponible | Inventario |
| `/v1/owner/inventory/adjust` | POST | ✅ Disponible | Ajustar inventario |

### SuperAdmin

| Endpoint | Método | Estado | Uso en UI |
|---|---|---|---|
| `/v1/superadmin/restaurants` | GET/POST | ✅ Disponible | CRUD restaurantes |
| `/v1/superadmin/restaurants/{id}` | GET | ✅ Disponible | Ver restaurante |
| `/v1/superadmin/restaurants/{id}/suspend` | PUT | ✅ Disponible | Suspender |
| `/v1/superadmin/restaurants/{id}/activate` | PUT | ✅ Disponible | Activar |
| `/v1/superadmin/users` | GET/POST | ✅ Disponible | CRUD usuarios |
| `/v1/superadmin/users/{id}/suspend` | PUT | ✅ Disponible | Suspender usuario |

### Depende de backend

| Funcionalidad | Estado | Dependencia |
|---|---|---|
| WebSocket en tiempo real (Reverb) | ⚠️ Configurado pero no activo | `BROADCAST_CONNECTION=reverb` |
| Pagos reales (Stripe Connect) | ❌ No implementado | Integración Stripe |
| Notificaciones push | ❌ No implementado | Service worker + backend |
| Exportación Excel | ❌ Solo CSV disponible | Backend |
| Gráficos de analíticas | ❌ Solo datos raw | Backend + librería de gráficos |
| Endpoint `/v1/owner/restaurants` | ⚠️ Parcial | Devuelve `restaurant_id` en lugar de lista |
| Multi-idioma en UI | ❌ No implementado | Backend debe soportar `Accept-Language` |
| Configuración visual por restaurante | ❌ No implementado | Backend debe guardar `primary_color`, `logo_url` |
| Temas claro/oscuro | ❌ No implementado | Backend debe guardar `theme_preference` |
| Colores primarios por restaurante | ❌ No implementado | Backend debe guardar `primary_color` |

---

## 6. Stores, composables y servicios necesarios

### Stores

| Store | Responsabilidad |
|---|---|
| `authStore` | Autenticación, token, usuario, roles |
| `clientMenuStore` | Carta, categorías, productos, locale |
| `cartStore` | Carrito de cliente (nuevo) |
| `connectionStore` | Estado de conexión, offline/online (nuevo) |
| `alertStore` | Alertas de sala, notificaciones (nuevo) |

### Composables

| Composable | Responsabilidad |
|---|---|
| `useConnection()` | Detectar online/offline, reintentos automáticos |
| `useCart()` | Gestionar carrito, idempotencia, sync |
| `useAccessibility()` | Gestión de foco, aria-live, reduced-motion |
| `useNotifications()` | Toasts, confirmaciones, feedback visual |
| `useWebSocket()` | Suscripción/desuscripción Laravel Echo |

### Servicios

| Servicio | Responsabilidad |
|---|---|
| `api.js` | Axios con interceptores, retry, timeout |
| `echoService.js` | Laravel Echo, canales WebSocket (nuevo) |

---

## 7. Estrategia de testing

### Vitest (unitario e integración)

| Qué probar | Cómo |
|---|---|
| Stores (auth, cart, connection) | Mock API, verificar estado |
| Composables (useCart, useConnection) | Mock dependencies |
| Componentes UI (Button, Modal, ProductCard) | Vue Test Utils |
| Formularios (validación) | Mock submit, verificar errores |
| Filtros y búsqueda (clientMenuStore) | Datos mock, verificar resultados |

### Playwright (E2E) — Si está disponible

| Flujo | Qué probar |
|---|---|
| Cliente QR | Abrir carta → añadir productos → revisar carrito → enviar pedido |
| Sala | Login → ver mapa de salas → ver detalle de mesa |
| Cocina | Login → ver KDS → cambiar estado de pedido |
| Owner | Login → crear producto → ver en carta |
| SuperAdmin | Login → crear restaurante → suspender → activar |

### axe (accesibilidad)

| Qué auditar | Cómo |
|---|---|
| Carta pública | axe.run() en Playwright |
| Formularios | Contraste, labels, focus, navegación teclado |
| KDS | Contraste alto, texto legible, navegación teclado |
| Admin | Tablas accesibles, formularios, modales |

### Pruebas responsive

| Viewport | Qué probar |
|---|---|
| 320px | Móvil pequeño, carta, carrito |
| 480px | Móvil grande, carta, carrito |
| 768px | Tablet, sala, KDS |
| 1024px | Escritorio, admin, owner |

### Pruebas offline

| Escenario | Qué probar |
|---|---|
| Sin conexión al cargar carta | Skeleton → error con reintento |
| Sin conexión al enviar pedido | Guardado local + indicador |
| Reconexión | Sincronización automática |

---

## 8. Comandos que se ejecutarán (no ejecutar todavía)

```bash
# Backend tests
cd backend && php artisan test

# Frontend tests
cd frontend && npm run test

# Frontend build
cd frontend && npm run build

# Frontend lint
cd frontend && npm run lint

# Lint backend
cd backend && php artisan pint

# E2E tests (si Playwright está disponible)
cd frontend && npx playwright test

# Accessibility audit (si axe está disponible)
cd frontend && npx axe devtools
```

---

## 9. Criterios de aceptación UX por experiencia

### Cliente QR

- [ ] Primer pedido en ≤ 90 segundos desde apertura.
- [ ] Carrito visible desde cualquier pantalla (badge + drawer).
- [ ] Búsqueda funciona en ≤ 200ms.
- [ ] Productos agotados distinguibles (gris + texto "Agotado").
- [ ] Confirmación de pedido con `aria-live`.
- [ ] Timeline de estados post-pedido.
- [ ] "Llamar camarero" y "Pedir cuenta" con confirmación.
- [ ] Modo offline claro (indicador + cola local).
- [ ] WCAG 2.1 AA: puntuación axe ≥ 95.
- [ ] LCP ≤ 2.5s en 3G lento.

### Sala

- [ ] Mapa de salas carga en ≤ 3 segundos.
- [ ] Estado de cada mesa visible sin depender solo de color.
- [ ] Alertas priorizadas arriba.
- [ ] Actualización en tiempo real (WebSocket) con fallback.
- [ ] Estado de conexión siempre visible.
- [ ] Funcionalidad offline básica (ver mesas cached).
- [ ] Touch targets ≥ 48px.

### Cocina (KDS)

- [ ] Tarjetas legibles a 2 metros.
- [ ] Cambio de estado en ≤ 2 segundos.
- [ ] Columnas claras: Pendiente → Preparando → Listo.
- [ ] Indicador de retraso (no solo color).
- [ ] Acciones grandes (≥ 64px).
- [ ] Sin animaciones distractores (`prefers-reduced-motion`).
- [ ] Sin datos de otros tenants.

### Owner

- [ ] Navegación lateral clara y colapsable.
- [ ] Tablas con búsqueda, filtros, orden y paginación.
- [ ] Formularios con validación progresiva.
- [ ] Confirmación para acciones destructivas.
- [ ] Estados vacíos con CTA.
- [ ] Errores de API nunca ocultos.
- [ ] Responsive (usable en tablet).

### SuperAdmin

- [ ] Separación visual evidente de Owner.
- [ ] Advertencia reforzada para suspensión/reactivación.
- [ ] Trazabilidad de acciones.
- [ ] Datos globales solo cuando backend autoriza.

---

## 10. Riesgos y decisiones que requieren aprobación

### Riesgos técnicos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Backend sin WebSocket activo | Sala y KDS sin tiempo real | Fallback a polling cada 10s |
| Backend sin pagos reales | Pago simulado en UI | Marcar como "No disponible" |
| Sin gráficos en analíticas | Owner sin visualización | Mostrar datos tabulares |
| Sin exportación Excel | Solo CSV disponible | Documentar limitación |
| Sin notificaciones push | Sin alertas en background | Usar WebSocket/polling |

### Decisiones que necesitan aprobación

1. **Nombre del proyecto en PWA**: ¿"La Frenona 3" o genérico por restaurante?
2. **Color primario configurable**: ¿Permitir al Owner cambiar el color de su restaurante desde la UI?
3. **Librería de gráficos**: ¿Implementar con Chart.js o recharts? (requiere nueva dependencia)
4. **Librería de iconos**: ¿Heroicons (inline SVG) o Lucide? (requiere nueva dependencia)
5. **Estado de "No disponible"**: ¿Mostrar pantallas con CTA "Módulo no disponible" o redirigir?
6. **Lazy loading**: ¿Cargar cada área (client/staff/kitchen/owner/admin) en chunks separados?
7. **Idiomas**: ¿Soporte multi-idioma en UI o solo español/inglés?
8. **Dark mode**: ¿Implementar dark mode para KDS (cocina)?
9. **PWA offline**: ¿Qué datos cachear? (carta sí, datos de sesión no)
10. **Estructura de carpetas**: ¿Separar por feature o por tipo (components/views/stores)?

---

## Resumen del enfoque

### Enfoque visual y de UX

- **Cálido y profesional**: Paleta crema/naranja, no corporativo frío.
- **Mobile-first para cliente**: Botones grandes, pocos toques, carrito siempre visible.
- **Operativo para sala/cocina**: Contraste alto, lectura rápida, acciones grandes.
- **Administrativo para owner/admin**: Sidebar colapsable, tablas con filtros, formularios claros.
- **Accesible WCAG 2.1 AA**: Contraste, teclado, aria-live, foco visible, reduced-motion.
- **Responsive**: 5 breakpoints, nunca scroll horizontal, touch targets ≥ 48px.

### Flujos priorizados

1. **P0**: Cliente QR → Carta → Carrito → Pedido → Seguimiento (flujo crítico de negocio)
2. **P1**: Sala → Mapa de salas → Alertas → Detalle de mesa
3. **P1**: Cocina → KDS → Cambiar estado → Historial
4. **P2**: Owner → Dashboard → Carta → Mesas → Personal → Analíticas
5. **P2**: SuperAdmin → Dashboard → Restaurantes → Usuarios

### Cambios de frontend propuestos

- **Nueva estructura**: 5 áreas de navegación con lazy loading.
- **Nuevos componentes**: 15+ componentes UI reutilizables.
- **Nuevos stores**: 3 stores nuevos (cart, connection, alert).
- **Nuevos composables**: 5 composables (connection, cart, accessibility, notifications, websocket).
- **Nuevas vistas**: 20+ vistas nuevas para las 4 experiencias.
- **Sistema de diseño**: CSS completo con tokens, base, componentes, utilidades.

### Dependencias de backend que faltan

- WebSocket activo (`BROADCAST_CONNECTION=reverb`) para tiempo real.
- Endpoint `/v1/owner/restaurants` devuelve `restaurant_id` en lugar de lista.
- Pagos reales con Stripe Connect (actualmente simulado).
- Gráficos de analíticas (actualmente solo datos raw).
- Exportación Excel (actualmente solo CSV).
- Notificaciones push.

### Decisiones pendientes

Ver sección 10 arriba (10 decisiones que requieren aprobación).
