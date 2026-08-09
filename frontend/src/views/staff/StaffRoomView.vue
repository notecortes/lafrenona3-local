<template>
  <div class="staff-room">
    <header class="staff-room__header">
      <h1 class="staff-room__title">{{ t('room.title', locale) }}</h1>
      <div class="staff-room__header-actions">
        <ConnectionStatus :locale="locale" />
        <button
          class="staff-room__refresh-btn"
          @click="fetchTables(false)"
          :aria-label="t('room.refresh', locale)"
        >
          🔄
        </button>
      </div>
    </header>

    <main class="staff-room__main">
      <!-- Loading State -->
      <div v-if="loading" class="staff-room__loading">
        <Skeleton type="rect" v-for="i in 8" :key="i" class="skeleton-table" />
      </div>

      <!-- Error State -->
      <ErrorState
        v-else-if="error"
        :title="t('app.errorLoading', locale)"
        :description="error"
        :show-retry="true"
        @retry="fetchTables(false)"
      />

      <!-- Tables Grid -->
      <div v-else class="staff-room__tables">
        <div
          v-for="table in tables"
          :key="table.id"
          :class="[
            'staff-room__table',
            `staff-room__table--${table.status}`,
            { 'staff-room__table--assistance': !!table.assistance_status },
            { 'staff-room__table--has-order': !!table.current_order }
          ]"
          @click="handleTableClick(table)"
          :aria-label="`Mesa ${table.number}: ${tableStatusText(table)}`"
          role="button"
          tabindex="0"
          @keydown.enter="handleTableClick(table)"
          @keydown.space.prevent="handleTableClick(table)"
        >
          <div class="staff-room__table-number">{{ table.number }}</div>
          <div class="staff-room__table-status">
            <StatusBadge :variant="tableStatusBadge(table)">
              {{ tableStatusText(table) }}
            </StatusBadge>
          </div>

          <!-- Order Summary Badge on Table Card -->
          <div v-if="table.current_order" class="staff-room__order-summary">
            <span class="staff-room__order-total">{{ formatPrice(table.current_order.total_price) }}</span>
            <span class="staff-room__order-items-count">{{ table.current_order.items.length }} ítems</span>
          </div>

          <!-- Assistance Alert -->
          <div v-if="table.assistance_status" class="staff-room__assistance-alert">
            <span class="staff-room__assistance-icon" aria-hidden="true">
              {{ table.assistance_status === 'waiter_called' ? '🙋' : '🧾' }}
            </span>
            <span class="staff-room__assistance-time">
              {{ formatTime(table.assistance_requested_at) }}
            </span>
            <button
              class="staff-room__dismiss-btn"
              @click.stop="dismissAssistance(table)"
              :aria-label="t('room.dismiss', locale)"
            >
              ✕
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Table Detail Modal for Waiters -->
    <div v-if="selectedTable" class="modal-backdrop" @click.self="selectedTable = null">
      <div class="modal-card">
        <header class="modal-header">
          <div class="modal-title-group">
            <h2>Mesa {{ selectedTable.number }}</h2>
            <StatusBadge :variant="tableStatusBadge(selectedTable)">
              {{ tableStatusText(selectedTable) }}
            </StatusBadge>
          </div>
          <button class="modal-close-btn" @click="selectedTable = null">✕</button>
        </header>

        <!-- Change Table Status Actions -->
        <div class="modal-status-actions">
          <span class="modal-label">Estado de la mesa:</span>
          <div class="status-btn-group">
            <button
              :class="['status-btn', { active: selectedTable.status === 'free' }]"
              @click="changeTableStatus(selectedTable, 'free')"
            >
              ⚪ Libre
            </button>
            <button
              :class="['status-btn', { active: selectedTable.status === 'occupied' }]"
              @click="changeTableStatus(selectedTable, 'occupied')"
            >
              🟢 Ocupada
            </button>
            <button
              :class="['status-btn', { active: selectedTable.status === 'payment_pending' }]"
              @click="changeTableStatus(selectedTable, 'payment_pending')"
            >
              🟡 Cobro Pendiente
            </button>
          </div>
        </div>

        <!-- Current Active Order Details -->
        <div class="modal-order-body">
          <h3>Comanda actual</h3>
          
          <div v-if="!selectedTable.current_order || selectedTable.current_order.items.length === 0" class="no-order">
            <p>No hay comanda abierta en esta mesa.</p>
          </div>

          <div v-else class="order-details">
            <div class="order-items-list">
              <div
                v-for="item in selectedTable.current_order.items"
                :key="item.id"
                :class="['order-item-card', `order-item-card--${item.status}`]"
              >
                <div class="item-main">
                  <span class="item-qty">{{ item.quantity }}x</span>
                  <span class="item-name">{{ item.name }}</span>
                  <span class="item-price">{{ formatPrice(item.unit_price * item.quantity) }}</span>
                </div>
                
                <p v-if="item.notes" class="item-notes">📝 {{ item.notes }}</p>

                <div class="item-footer">
                  <StatusBadge :variant="itemStatusBadge(item.status)">
                    {{ itemStatusText(item.status) }}
                  </StatusBadge>

                  <div v-if="item.status !== 'delivered' && item.status !== 'cancelled'" class="item-actions">
                    <button
                      v-if="item.status === 'pending'"
                      class="status-action-btn status-action-btn--cooking"
                      @click="updateItemStatus(item, 'cooking')"
                    >
                      🍳 Cocinar
                    </button>
                    <button
                      v-if="item.status === 'cooking'"
                      class="status-action-btn status-action-btn--ready"
                      @click="updateItemStatus(item, 'ready')"
                    >
                      ✅ Listo
                    </button>
                    <button
                      v-if="item.status === 'ready'"
                      class="status-action-btn status-action-btn--deliver"
                      @click="updateItemStatus(item, 'delivered')"
                    >
                      ✔ Servir
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Order Total & Close Table Action -->
            <div class="order-footer">
              <div class="total-row">
                <span>Total Comanda:</span>
                <span class="total-price">{{ formatPrice(selectedTable.current_order.total_price) }}</span>
              </div>

              <button
                class="close-order-btn"
                @click="closeOrder(selectedTable.current_order)"
                :disabled="closingOrder"
              >
                {{ closingOrder ? 'Cerrando...' : '💳 Cerrar comanda / Cobrar' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'
import ConnectionStatus from '@/components/ui/ConnectionStatus.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ErrorState from '@/components/ui/ErrorState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Toast from '@/components/ui/Toast.vue'
import { t } from '@/config/i18n'

const locale = ref('es')
const tables = ref([])
const loading = ref(true)
const error = ref('')
const selectedTable = ref(null)
const closingOrder = ref(false)
let echoListener = null
let pollInterval = null

async function fetchTables(silent = false) {
  if (!silent) {
    loading.value = true
  }
  error.value = ''
  try {
    const response = await api.get('/v1/staff/room')
    tables.value = response.data.data || []
    
    // Refresh selected table reference if open
    if (selectedTable.value) {
      const updated = tables.value.find((t) => t.id === selectedTable.value.id)
      if (updated) {
        selectedTable.value = updated
      }
    }
  } catch (err) {
    if (!silent) {
      error.value = err.response?.data?.message || t('app.errorLoading', locale.value)
    }
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

function handleTableClick(table) {
  if (table.assistance_status) {
    dismissAssistance(table)
  }
  selectedTable.value = table
}

async function dismissAssistance(table) {
  try {
    await api.post(`/v1/staff/tables/${table.id}/dismiss-assistance`)
    table.assistance_status = null
    table.assistance_requested_at = null
  } catch (err) {
    console.error('Failed to dismiss assistance:', err)
  }
}

async function changeTableStatus(table, newStatus) {
  try {
    await api.put(`/v1/staff/tables/${table.id}/status`, { status: newStatus })
    table.status = newStatus
  } catch (err) {
    console.error('Failed to update table status:', err)
  }
}

async function updateItemStatus(item, newStatus) {
  try {
    await api.put(`/v1/staff/order-items/${item.id}/status`, { status: newStatus })
    item.status = newStatus
  } catch (err) {
    console.error('Failed to update item status:', err)
  }
}

async function closeOrder(order) {
  if (!order || !order.id) return
  closingOrder.value = true
  try {
    await api.post(`/v1/staff/orders/${order.id}/close`)
    if (selectedTable.value) {
      selectedTable.value.current_order = null
      selectedTable.value.status = 'free'
    }
    await fetchTables(true)
  } catch (err) {
    console.error('Failed to close order:', err)
  } finally {
    closingOrder.value = false
  }
}

function formatPrice(value) {
  const numeric = typeof value === 'number' ? value : parseFloat(value || 0)
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: 'EUR',
  }).format(numeric)
}

function tableStatusText(table) {
  if (table.assistance_status === 'waiter_called') {
    return t('room.waiter', locale.value)
  }
  if (table.assistance_status === 'bill_requested') {
    return t('room.bill', locale.value)
  }
  if (table.status === 'occupied') {
    return t('room.occupied', locale.value)
  }
  if (table.status === 'payment_pending') {
    return 'Pago pendiente'
  }
  return t('room.free', locale.value)
}

function tableStatusBadge(table) {
  if (table.assistance_status === 'waiter_called') {
    return 'warning'
  }
  if (table.assistance_status === 'bill_requested') {
    return 'info'
  }
  if (table.status === 'occupied') {
    return 'success'
  }
  if (table.status === 'payment_pending') {
    return 'warning'
  }
  return 'info'
}

function itemStatusText(status) {
  const map = {
    pending: 'Pendiente',
    cooking: 'En preparación',
    ready: 'Listo para servir',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
  }
  return map[status] || status
}

function itemStatusBadge(status) {
  const map = {
    pending: 'warning',
    cooking: 'kitchen',
    ready: 'success',
    delivered: 'info',
    cancelled: 'error',
  }
  return map[status] || 'info'
}

function formatTime(timestamp) {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function connectWebSocket() {
  if (!window.Echo) return

  echoListener = window.Echo.private(`restaurant.${import.meta.env.VITE_RESTAURANT_ID || '1'}`)
    .listen('.ClientAssistanceRequested', (data) => {
      const table = tables.value.find((t) => t.id === data.table?.id)
      if (table) {
        table.assistance_status = data.assistance_type
        table.assistance_requested_at = new Date().toISOString()
      }
    })
    .listen('.OrderStateChanged', () => {
      fetchTables(true)
    })
}

function disconnectWebSocket() {
  if (echoListener) {
    echoListener?.stop()
    echoListener = null
  }
}

onMounted(() => {
  fetchTables(false)
  connectWebSocket()
  pollInterval = setInterval(() => {
    fetchTables(true)
  }, 4000)
})

onUnmounted(() => {
  disconnectWebSocket()
  if (pollInterval) {
    clearInterval(pollInterval)
  }
})
</script>

<style scoped>
.staff-room {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.staff-room__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
}

.staff-room__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.staff-room__header-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.staff-room__refresh-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: var(--font-size-xl);
  padding: var(--spacing-sm);
  min-width: var(--touch-target-md);
  min-height: var(--touch-target-md);
  display: flex;
  align-items: center;
  justify-content: center;
}

.staff-room__refresh-btn:hover {
  opacity: 0.7;
}

.staff-room__main {
  flex: 1;
  padding: var(--spacing-lg);
}

.staff-room__loading {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--spacing-lg);
}

.skeleton-table {
  height: 12rem;
  border-radius: var(--radius-lg);
}

.staff-room__tables {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: var(--spacing-lg);
}

.staff-room__table {
  position: relative;
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-sm);
  background-color: var(--color-bg-secondary);
  border: 3px solid var(--color-border);
  border-radius: var(--radius-xl);
  cursor: pointer;
  transition: all var(--transition-fast);
  min-height: 130px;
}

.staff-room__table:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.staff-room__table--occupied {
  border-color: var(--color-success);
  background-color: var(--color-success-bg);
}

.staff-room__table--has-order {
  border-width: 4px;
}

.staff-room__table--assistance {
  animation: assistPulse 1.5s infinite;
  border-color: var(--color-warning);
}

@keyframes assistPulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(217, 119, 6, 0);
  }
}

.staff-room__table-number {
  font-size: var(--font-size-2xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.staff-room__table-status {
  display: flex;
}

.staff-room__order-summary {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: var(--spacing-xs);
  padding: 2px var(--spacing-xs);
  background-color: rgba(0, 0, 0, 0.05);
  border-radius: var(--radius-md);
}

.staff-room__order-total {
  font-weight: var(--font-weight-bold);
  font-size: var(--font-size-sm);
  color: var(--color-text);
}

.staff-room__order-items-count {
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
}

.staff-room__assistance-alert {
  position: absolute;
  top: -8px;
  right: -8px;
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-xs) var(--spacing-sm);
  background-color: var(--color-warning-bg);
  border: 1px solid var(--color-warning);
  border-radius: var(--radius-full);
  font-size: var(--font-size-xs);
  color: var(--color-warning);
}

.staff-room__assistance-icon {
  font-size: var(--font-size-sm);
}

.staff-room__assistance-time {
  font-weight: var(--font-weight-semibold);
}

.staff-room__dismiss-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  font-size: var(--font-size-xs);
  color: var(--color-warning);
}

/* Modal styles */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: var(--spacing-md);
}

.modal-card {
  background-color: var(--color-bg-secondary);
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 540px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--color-border);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-md) var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
}

.modal-title-group {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.modal-title-group h2 {
  margin: 0;
  font-size: var(--font-size-xl);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: var(--font-size-lg);
  cursor: pointer;
  color: var(--color-text-secondary);
}

.modal-status-actions {
  padding: var(--spacing-md) var(--spacing-lg);
  background-color: var(--color-bg);
  border-bottom: 1px solid var(--color-border);
}

.modal-label {
  display: block;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-semibold);
  margin-bottom: var(--spacing-xs);
  color: var(--color-text-secondary);
}

.status-btn-group {
  display: flex;
  gap: var(--spacing-xs);
}

.status-btn {
  flex: 1;
  padding: var(--spacing-xs) var(--spacing-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background-color: var(--color-bg-secondary);
  cursor: pointer;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  transition: all var(--transition-fast);
}

.status-btn.active {
  border-color: var(--color-primary);
  background-color: var(--color-primary-bg, rgba(37, 99, 235, 0.1));
  font-weight: var(--font-weight-bold);
}

.modal-order-body {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-lg);
}

.modal-order-body h3 {
  margin-top: 0;
  margin-bottom: var(--spacing-md);
  font-size: var(--font-size-md);
}

.no-order {
  padding: var(--spacing-lg);
  text-align: center;
  color: var(--color-text-secondary);
}

.order-items-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.order-item-card {
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background-color: var(--color-bg);
}

.item-main {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.item-qty {
  font-weight: var(--font-weight-bold);
  color: var(--color-primary);
}

.item-name {
  flex: 1;
  font-weight: var(--font-weight-medium);
}

.item-price {
  font-weight: var(--font-weight-semibold);
}

.item-notes {
  font-size: var(--font-size-xs);
  color: var(--color-warning);
  margin: var(--spacing-xs) 0 0 0;
}

.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: var(--spacing-xs);
  padding-top: var(--spacing-xs);
  border-top: 1px dashed var(--color-border);
}

.deliver-btn {
  background-color: var(--color-success);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  padding: var(--spacing-xs) var(--spacing-sm);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
  cursor: pointer;
}

.order-footer {
  margin-top: var(--spacing-lg);
  padding-top: var(--spacing-md);
  border-top: 2px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.total-row {
  display: flex;
  justify-content: space-between;
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
}

.close-order-btn {
  width: 100%;
  padding: var(--spacing-md);
  background-color: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-bold);
  cursor: pointer;
}

.close-order-btn:hover {
  opacity: 0.9;
}

.close-order-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.item-actions {
  display: flex;
  gap: var(--spacing-xs);
}

.status-action-btn {
  border: none;
  border-radius: var(--radius-sm);
  padding: var(--spacing-xs) var(--spacing-sm);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
  cursor: pointer;
  color: white;
}

.status-action-btn--cooking {
  background-color: var(--color-warning, #d97706);
}

.status-action-btn--ready {
  background-color: #2563eb;
}

.status-action-btn--deliver {
  background-color: var(--color-success, #16a34a);
}

@media (prefers-reduced-motion: reduce) {
  .staff-room__table--assistance {
    animation-duration: 0.01ms;
  }
}
</style>
