<template>
  <div class="kitchen-monitor">
    <header class="kitchen-monitor__header">
      <h1 class="kitchen-monitor__title">{{ t('kitchen.title', locale) }}</h1>
      <div class="kitchen-monitor__header-actions">
        <div class="kitchen-monitor__area-tabs">
          <button
            v-for="area in areas"
            :key="area.value"
            :class="['kitchen-monitor__area-tab', { 'kitchen-monitor__area-tab--active': currentArea === area.value }]"
            @click="currentArea = area.value"
            :aria-pressed="currentArea === area.value"
          >
            {{ area.label }}
          </button>
        </div>
        <ConnectionStatus :locale="locale" />
      </div>
    </header>

    <main class="kitchen-monitor__main">
      <!-- Stats Bar -->
      <div class="kitchen-monitor__stats">
        <div class="kitchen-monitor__stat">
          <span class="kitchen-monitor__stat-value">{{ pendingCount }}</span>
          <span class="kitchen-monitor__stat-label">{{ t('kitchen.pending', locale) }}</span>
        </div>
        <div class="kitchen-monitor__stat">
          <span class="kitchen-monitor__stat-value">{{ cookingCount }}</span>
          <span class="kitchen-monitor__stat-label">{{ t('kitchen.cooking', locale) }}</span>
        </div>
        <div class="kitchen-monitor__stat">
          <span class="kitchen-monitor__stat-value">{{ readyCount }}</span>
          <span class="kitchen-monitor__stat-label">{{ t('kitchen.ready', locale) }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="kitchen-monitor__loading">
        <Skeleton type="rect" v-for="i in 4" :key="i" class="skeleton-item" />
      </div>

      <!-- Error State -->
      <ErrorState
        v-else-if="error"
        :title="t('app.errorLoading', locale)"
        :description="error"
        :show-retry="true"
        @retry="fetchItems"
      />

      <!-- Empty State -->
      <EmptyState
        v-else-if="items.length === 0"
        :icon="'✅'"
        :title="t('kitchen.empty', locale)"
        :description="t('kitchen.emptyDesc', locale)"
      />

      <!-- Items Grid -->
      <div v-else class="kitchen-monitor__items">
        <div
          v-for="item in sortedItems"
          :key="item.id"
          :class="[
            'kitchen-monitor__item',
            `kitchen-monitor__item--${item.status}`,
          ]"
        >
          <!-- Item Header -->
          <div class="kitchen-monitor__item-header">
            <div class="kitchen-monitor__item-order">
              <span class="kitchen-monitor__item-order-number">#{{ item.order?.number || item.id }}</span>
              <span v-if="item.order?.table" class="kitchen-monitor__item-table">
                {{ t('kitchen.table', locale, { number: item.order.table.number }) }}
              </span>
            </div>
            <StatusBadge :variant="itemStatusBadge(item)">
              {{ itemStatusText(item) }}
            </StatusBadge>
          </div>

          <!-- Item Product -->
          <div class="kitchen-monitor__item-product">
            <h3 class="kitchen-monitor__item-name">{{ itemName(item) }}</h3>
            <p v-if="item.notes" class="kitchen-monitor__item-notes">{{ item.notes }}</p>
          </div>

          <!-- Item Quantity -->
          <div class="kitchen-monitor__item-quantity">
            <span class="kitchen-monitor__item-qty-value">{{ item.quantity }}</span>
          </div>

          <!-- Item Time -->
          <div class="kitchen-monitor__item-time">
            {{ formatTime(item.created_at) }}
          </div>

          <!-- Action Buttons -->
          <div v-if="item.status !== 'delivered' && item.status !== 'cancelled'" class="kitchen-monitor__item-actions">
            <Button
              v-if="item.status === 'pending'"
              variant="primary"
              size="sm"
              @click="updateStatus(item, 'cooking')"
            >
              {{ t('kitchen.start', locale) }}
            </Button>
            <Button
              v-if="item.status === 'cooking'"
              variant="success"
              size="sm"
              @click="updateStatus(item, 'ready')"
            >
              {{ t('kitchen.ready', locale) }}
            </Button>
            <Button
              v-if="item.status === 'ready'"
              variant="secondary"
              size="sm"
              @click="updateStatus(item, 'delivered')"
            >
              {{ t('kitchen.delivered', locale) }}
            </Button>
          </div>
        </div>
      </div>
    </main>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'
import ConnectionStatus from '@/components/ui/ConnectionStatus.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ErrorState from '@/components/ui/ErrorState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Button from '@/components/ui/Button.vue'
import Toast from '@/components/ui/Toast.vue'
import { t } from '@/config/i18n'

const locale = ref('es')
const currentArea = ref('kitchen')
const items = ref([])
const loading = ref(true)
const error = ref('')
let echoListener = null

const areas = [
  { value: 'kitchen', label: '🍳 Cocina' },
  { value: 'bar', label: '🍸 Barra' },
]

const sortedItems = computed(() => {
  return [...items.value].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
})

const pendingCount = computed(() => items.value.filter((i) => i.status === 'pending').length)
const cookingCount = computed(() => items.value.filter((i) => i.status === 'cooking').length)
const readyCount = computed(() => items.value.filter((i) => i.status === 'ready').length)

async function fetchItems() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/v1/staff/order-items/pending', {
      params: { area: currentArea.value },
    })
    items.value = response.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || t('app.errorLoading', locale.value)
  } finally {
    loading.value = false
  }
}

function itemName(item) {
  const name = item.product?.name || ''
  if (typeof name === 'object') {
    return name[locale.value] || name.en || Object.values(name)[0] || ''
  }
  return name
}

function itemStatusText(item) {
  const statusMap = {
    pending: t('kitchen.pending', locale.value),
    cooking: t('kitchen.cooking', locale.value),
    ready: t('kitchen.ready', locale.value),
    delivered: t('kitchen.delivered', locale.value),
    cancelled: t('kitchen.cancelled', locale.value),
  }
  return statusMap[item.status] || item.status
}

function itemStatusBadge(item) {
  const badgeMap = {
    pending: 'warning',
    cooking: 'kitchen',
    ready: 'success',
    delivered: 'info',
    cancelled: 'error',
  }
  return badgeMap[item.status] || 'info'
}

function formatTime(timestamp) {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

async function updateStatus(item, newStatus) {
  try {
    await api.put(`/v1/staff/order-items/${item.id}/status`, { status: newStatus })
    item.status = newStatus
  } catch (err) {
    console.error('Failed to update status:', err)
  }
}

function connectWebSocket() {
  if (!window.Echo) return

  echoListener = window.Echo.private(`restaurant.${import.meta.env.VITE_RESTAURANT_ID || '1'}`)
    .listen('.OrderStateChanged', (data) => {
      const item = items.value.find((i) => i.id === data.order_item?.id)
      if (item) {
        item.status = data.order_item?.status || item.status
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
  fetchItems()
  connectWebSocket()
})

onUnmounted(() => {
  disconnectWebSocket()
})
</script>

<style scoped>
.kitchen-monitor {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.kitchen-monitor__header {
  padding: var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
}

.kitchen-monitor__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0 0 var(--spacing-md);
}

.kitchen-monitor__header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kitchen-monitor__area-tabs {
  display: flex;
  gap: var(--spacing-sm);
}

.kitchen-monitor__area-tab {
  padding: var(--spacing-sm) var(--spacing-md);
  background-color: var(--color-bg);
  color: var(--color-text-muted);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  cursor: pointer;
  min-height: var(--touch-target-md);
}

.kitchen-monitor__area-tab:hover {
  background-color: var(--color-bg-tertiary);
}

.kitchen-monitor__area-tab--active {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}

.kitchen-monitor__main {
  flex: 1;
  padding: var(--spacing-lg);
}

.kitchen-monitor__stats {
  display: flex;
  gap: var(--spacing-lg);
  margin-bottom: var(--spacing-xl);
  padding: var(--spacing-md);
  background-color: var(--color-bg-secondary);
  border-radius: var(--radius-lg);
}

.kitchen-monitor__stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.kitchen-monitor__stat-value {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-primary);
}

.kitchen-monitor__stat-label {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.kitchen-monitor__loading {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--spacing-lg);
}

.skeleton-item {
  height: 16rem;
  border-radius: var(--radius-lg);
}

.kitchen-monitor__items {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--spacing-lg);
}

.kitchen-monitor__item {
  background-color: var(--color-bg-secondary);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  transition: all var(--transition-fast);
}

.kitchen-monitor__item--pending {
  border-left: 4px solid var(--color-warning);
}

.kitchen-monitor__item--cooking {
  border-left: 4px solid var(--color-kitchen);
  background-color: #FFF7ED;
}

.kitchen-monitor__item--ready {
  border-left: 4px solid var(--color-success);
  background-color: var(--color-success-bg);
}

.kitchen-monitor__item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kitchen-monitor__item-order {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.kitchen-monitor__item-order-number {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.kitchen-monitor__item-table {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
}

.kitchen-monitor__item-product {
  flex: 1;
}

.kitchen-monitor__item-name {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin: 0;
}

.kitchen-monitor__item-notes {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: var(--spacing-xs) 0 0;
  font-style: italic;
}

.kitchen-monitor__item-quantity {
  display: flex;
  align-items: center;
  justify-content: center;
}

.kitchen-monitor__item-qty-value {
  font-size: var(--font-size-4xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-primary);
}

.kitchen-monitor__item-time {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
}

.kitchen-monitor__item-actions {
  display: flex;
  gap: var(--spacing-sm);
  margin-top: auto;
}

@media (prefers-reduced-motion: reduce) {
  .kitchen-monitor__item {
    transition-duration: 0.01ms;
  }
}
</style>
