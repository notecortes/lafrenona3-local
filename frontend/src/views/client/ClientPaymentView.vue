<template>
  <div class="client-payment">
    <header class="client-payment__header">
      <h1 class="client-payment__title">{{ t('payment.title', locale) }}</h1>
    </header>

    <main class="client-payment__main">
      <!-- Module Not Available -->
      <div v-if="!paymentAvailable" class="client-payment__unavailable">
        <EmptyState
          :icon="'💳'"
          :title="t('app.moduleNotAvailable', locale)"
          :description="t('app.moduleNotAvailableDesc', locale)"
        >
          <template #action>
            <Button variant="secondary" @click="$router.push('/client/menu')">
              {{ t('app.goBack', locale) }}
            </Button>
          </template>
        </EmptyState>
      </div>

      <!-- Payment Content -->
      <div v-else class="client-payment__content">
        <!-- Order Summary -->
        <div class="client-payment__summary">
          <h2 class="client-payment__summary-title">{{ t('cart.title', locale) }}</h2>
          <ul class="client-payment__summary-list">
            <li
              v-for="item in orderItems"
              :key="item.id"
              class="client-payment__summary-item"
            >
              <span>{{ item.productName }} x{{ item.quantity }}</span>
              <span>{{ formatPrice(item.total) }}€</span>
            </li>
          </ul>
          <div class="client-payment__summary-total">
            <span>{{ t('cart.total', locale) }}</span>
            <span>{{ formatPrice(orderTotal) }}€</span>
          </div>
        </div>

        <!-- Tip Selection -->
        <div class="client-payment__tip">
          <h3 class="client-payment__tip-title">{{ t('payment.tip', locale) }}</h3>
          <div class="client-payment__tip-options">
            <button
              v-for="option in tipOptions"
              :key="option"
              :class="['client-payment__tip-btn', { 'client-payment__tip-btn--active': selectedTip === option }]"
              @click="selectedTip = option"
            >
              {{ option }}%
            </button>
          </div>
          <div class="client-payment__tip-custom">
            <label :for="tipInputId" class="sr-only">{{ t('payment.customTip', locale) }}</label>
            <input
              :id="tipInputId"
              v-model="customTip"
              type="number"
              :placeholder="t('payment.customTip', locale)"
              class="client-payment__tip-input"
              min="0"
              step="0.01"
            />
          </div>
        </div>

        <!-- Payment Button -->
        <Button
          variant="primary"
          size="lg"
          fullWidth
          :loading="processing"
          :disabled="processing"
          @click="handlePayment"
        >
          {{ processing ? t('payment.processing', locale) : t('payment.payNow', locale, { amount: formatPrice(totalWithTip) }) }}
        </Button>

        <!-- Security Notice -->
        <p class="client-payment__security">
          🔒 {{ t('payment.secure', locale) }}
        </p>
      </div>
    </main>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Toast from '@/components/ui/Toast.vue'
import { t } from '@/config/i18n'

const route = useRoute()
const router = useRouter()
const locale = ref('es')

const paymentAvailable = ref(false)
const processing = ref(false)
const selectedTip = ref(10)
const customTip = ref('')

const tipOptions = [5, 10, 15, 20]
const tipInputId = ref(`tip-input-${Math.random().toString(36).substr(2, 9)}`)

const orderItems = ref([])
const orderTotal = ref(0)

const totalWithTip = computed(() => {
  const tipPercent = customTip.value ? parseFloat(customTip.value) : selectedTip.value
  return orderTotal.value + (orderTotal.value * tipPercent) / 100
})

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}

async function handlePayment() {
  processing.value = true
  try {
    await new Promise((resolve) => setTimeout(resolve, 1500))
    router.push(`/client/order/success`)
  } catch (err) {
    console.error('Payment failed:', err)
  } finally {
    processing.value = false
  }
}
</script>

<style scoped>
.client-payment {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
}

.client-payment__header {
  padding: var(--spacing-lg);
  background-color: var(--color-bg-secondary);
  border-bottom: 1px solid var(--color-border);
}

.client-payment__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.client-payment__main {
  flex: 1;
  padding: var(--spacing-lg);
}

.client-payment__unavailable {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}

.client-payment__content {
  max-width: 48rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xl);
}

.client-payment__summary {
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
}

.client-payment__summary-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0 0 var(--spacing-md);
}

.client-payment__summary-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
  margin-bottom: var(--spacing-md);
}

.client-payment__summary-item {
  display: flex;
  justify-content: space-between;
  font-size: var(--font-size-sm);
  color: var(--color-text);
}

.client-payment__summary-total {
  display: flex;
  justify-content: space-between;
  padding-top: var(--spacing-md);
  border-top: 2px solid var(--color-border);
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.client-payment__tip {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.client-payment__tip-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.client-payment__tip-options {
  display: flex;
  gap: var(--spacing-sm);
}

.client-payment__tip-btn {
  flex: 1;
  padding: var(--spacing-sm);
  background-color: var(--color-bg-secondary);
  color: var(--color-text);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  cursor: pointer;
  min-height: var(--touch-target-md);
}

.client-payment__tip-btn:hover {
  background-color: var(--color-bg-tertiary);
}

.client-payment__tip-btn--active {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  border-color: var(--color-primary);
}

.client-payment__tip-custom {
  margin-top: var(--spacing-sm);
}

.client-payment__tip-input {
  width: 100%;
  padding: var(--spacing-sm) var(--spacing-md);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  background-color: var(--color-bg);
  color: var(--color-text);
  min-height: var(--touch-target-md);
}

.client-payment__tip-input:focus {
  outline: none;
  border-color: var(--color-focus);
}

.client-payment__security {
  text-align: center;
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  margin: 0;
}

@media (max-width: 480px) {
  .client-payment__header {
    padding: var(--spacing-md);
  }

  .client-payment__main {
    padding: var(--spacing-md);
  }
}
</style>
