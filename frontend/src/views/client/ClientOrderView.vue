<template>
  <div class="client-order">
    <header class="client-order__header">
      <h1 class="client-order__title">{{ t('order.title', locale) }}</h1>
      <p v-if="orderId" class="client-order__id">
        {{ t('order.orderId', locale, { id: orderId }) }}
      </p>
    </header>

    <main class="client-order__main">
      <!-- Loading State -->
      <div v-if="loading" class="client-order__loading">
        <Skeleton type="rect" class="skeleton-card" />
      </div>

      <!-- Error State -->
      <ErrorState
        v-else-if="error"
        :title="t('app.errorLoading', locale)"
        :description="error"
        :show-retry="true"
        @retry="fetchOrder"
      />

      <!-- Order Content -->
      <div v-else class="client-order__content">
        <!-- Order Timeline -->
        <OrderTimeline :status="orderStatus" :locale="locale" />

        <!-- Order Items -->
        <div v-if="orderItems.length > 0" class="client-order__items">
          <h2 class="client-order__items-title">{{ t('order.items', locale) }}</h2>
          <ul class="client-order__items-list">
            <li
              v-for="item in orderItems"
              :key="item.id"
              class="client-order__item"
            >
              <span class="client-order__item-name">{{ item.productName }}</span>
              <span class="client-order__item-qty">x{{ item.quantity }}</span>
              <span class="client-order__item-price">{{ formatPrice(item.total) }}€</span>
            </li>
          </ul>
        </div>

        <!-- Order Total -->
        <div class="client-order__total">
          <span>{{ t('order.total', locale) }}</span>
          <span class="client-order__total-value">{{ formatPrice(orderTotal) }}€</span>
        </div>

        <!-- Assistance Actions -->
        <AssistanceButton
          :locale="locale"
          @success="handleAssistanceSuccess"
        />
      </div>
    </main>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import OrderTimeline from '@/components/client/OrderTimeline.vue'
import AssistanceButton from '@/components/client/AssistanceButton.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ErrorState from '@/components/ui/ErrorState.vue'
import Toast from '@/components/ui/Toast.vue'
import { t } from '@/config/i18n'

const route = useRoute()
const locale = ref('es')
const loading = ref(true)
const error = ref('')
const orderId = ref(null)
const orderItems = ref([])

const orderStatus = computed(() => {
  if (!orderId.value) return 'pending'
  return 'accepted'
})

const orderTotal = computed(() => {
  return orderItems.value.reduce((sum, item) => sum + item.total, 0)
})

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}

async function fetchOrder() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get(`/v1/client/orders/${route.params.id}`)
    orderId.value = response.data.data.id
    orderItems.value = response.data.data.items || []
  } catch (err) {
    error.value = err.response?.data?.message || t('app.errorLoading', locale.value)
  } finally {
    loading.value = false
  }
}

function handleAssistanceSuccess(type) {
  if (type === 'waiter') {
    console.log('Waiter called')
  } else if (type === 'bill') {
    console.log('Bill requested')
  }
}

onMounted(() => {
  fetchOrder()
})
</script>

<style scoped>
.client-order {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.client-order__header {
  padding: var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
}

.client-order__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.client-order__id {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: var(--spacing-xs) 0 0;
}

.client-order__main {
  flex: 1;
  padding: var(--spacing-lg);
}

.client-order__loading {
  display: flex;
  justify-content: center;
  padding: var(--spacing-3xl);
}

.skeleton-card {
  width: 100%;
  max-width: 32rem;
  height: 24rem;
  border-radius: var(--radius-lg);
}

.client-order__content {
  max-width: 48rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xl);
}

.client-order__items {
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
}

.client-order__items-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0 0 var(--spacing-md);
}

.client-order__items-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.client-order__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-sm) 0;
  border-bottom: 1px solid var(--color-border);
}

.client-order__item:last-child {
  border-bottom: none;
}

.client-order__item-name {
  flex: 1;
  font-size: var(--font-size-base);
  color: var(--color-text);
}

.client-order__item-qty {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: 0 var(--spacing-md);
}

.client-order__item-price {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  color: var(--color-success);
}

.client-order__total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-lg) 0;
  border-top: 2px solid var(--color-border);
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.client-order__total-value {
  color: var(--color-success);
}

@media (max-width: 480px) {
  .client-order__header {
    padding: var(--spacing-md);
  }

  .client-order__main {
    padding: var(--spacing-md);
  }
}
</style>
