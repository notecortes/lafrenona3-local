<template>
  <div class="staff-room">
    <header class="staff-room__header">
      <h1 class="staff-room__title">{{ t('room.title', locale) }}</h1>
      <div class="staff-room__header-actions">
        <ConnectionStatus :locale="locale" />
        <button
          class="staff-room__refresh-btn"
          @click="fetchTables"
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
        @retry="fetchTables"
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
let echoListener = null

async function fetchTables() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/v1/staff/room')
    tables.value = response.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || t('app.errorLoading', locale.value)
  } finally {
    loading.value = false
  }
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
  return 'info'
}

function formatTime(timestamp) {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function handleTableClick(table) {
  if (table.assistance_status) {
    dismissAssistance(table)
  }
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
}

function disconnectWebSocket() {
  if (echoListener) {
    echoListener?.stop()
    echoListener = null
  }
}

onMounted(() => {
  fetchTables()
  connectWebSocket()
})

onUnmounted(() => {
  disconnectWebSocket()
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
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--spacing-lg);
}

.staff-room__table {
  position: relative;
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-md);
  background-color: var(--color-bg-secondary);
  border: 3px solid var(--color-border);
  border-radius: var(--radius-xl);
  cursor: pointer;
  transition: all var(--transition-fast);
  min-height: var(--touch-target-lg);
}

.staff-room__table:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.staff-room__table--occupied {
  border-color: var(--color-success);
  background-color: var(--color-success-bg);
}

.staff-room__table--assistance {
  animation: assistPulse 1.5s infinite;
  border-color: var(--color-warning);
}

@keyframes assistPulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(217, 119, 6, 0);
  }
}

.staff-room__table-number {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.staff-room__table-status {
  display: flex;
}

.staff-room__assistance-alert {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-xs) var(--spacing-sm);
  background-color: var(--color-warning-bg);
  border-radius: var(--radius-full);
  font-size: var(--font-size-xs);
  color: var(--color-warning);
}

.staff-room__assistance-icon {
  font-size: var(--font-size-lg);
}

.staff-room__assistance-time {
  font-weight: var(--font-weight-semibold);
}

.staff-room__dismiss-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: var(--spacing-xs);
  font-size: var(--font-size-sm);
  min-width: var(--touch-target-sm);
  min-height: var(--touch-target-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-warning);
}

.staff-room__dismiss-btn:hover {
  color: var(--color-error);
}

@media (prefers-reduced-motion: reduce) {
  .staff-room__table--assistance {
    animation-duration: 0.01ms;
  }
}
</style>
