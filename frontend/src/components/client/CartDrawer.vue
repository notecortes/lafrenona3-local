<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="cart-drawer-overlay"
      @click="close"
    >
      <div
        class="cart-drawer"
        @click.stop
        role="dialog"
        aria-modal="true"
        aria-label="Carrito de compra"
      >
        <div class="cart-drawer__header">
          <h2 class="cart-drawer__title">{{ t('cart.title', locale) }}</h2>
          <button
            class="cart-drawer__close"
            @click="close"
            :aria-label="t('app.close', locale)"
          >
            ✕
          </button>
        </div>

        <div v-if="cart.isEmpty" class="cart-drawer__empty">
          <EmptyState
            :icon="'🛒'"
            :title="t('cart.empty', locale)"
            :description="t('cart.emptyDesc', locale)"
          />
        </div>

        <div v-else class="cart-drawer__items">
          <CartItem
            v-for="(item, index) in cart.items"
            :key="index"
            :item="item"
            :index="index"
            @update-quantity="(delta) => $emit('updateQuantity', index, delta)"
            @remove="()=> $emit('remove', index)"
            @update-notes="(notes) => $emit('updateNotes', index, notes)"
          />
        </div>

        <div v-if="!cart.isEmpty" class="cart-drawer__footer">
          <div class="cart-drawer__totals">
            <div class="cart-drawer__total-row">
              <span>{{ t('cart.subtotal', locale) }}</span>
              <span>{{ formatPrice(cart.total) }}€</span>
            </div>
            <div class="cart-drawer__total-row cart-drawer__total-row--total">
              <span>{{ t('cart.total', locale) }}</span>
              <span>{{ formatPrice(cart.total) }}€</span>
            </div>
            <p class="cart-drawer__tax">{{ t('cart.tax', locale) }}</p>
          </div>

          <div class="cart-drawer__actions">
            <Button
              variant="ghost"
              size="sm"
              @click="$emit('clear')"
            >
              {{ t('cart.clear', locale) }}
            </Button>
            <Button
              variant="primary"
              size="lg"
              fullWidth
              :loading="cart.syncing"
              :disabled="!canSend"
              @click="$emit('sendOrder')"
            >
              {{ sendButtonText }}
            </Button>
          </div>

          <div v-if="cart.status === 'pending'" class="cart-drawer__pending">
            <StatusBadge variant="warning">
              {{ t('cart.pending', locale) }}
            </StatusBadge>
            <p class="cart-drawer__pending-desc">
              {{ t('cart.pendingDesc', locale) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { useCartStore } from '@/stores/cartStore'
import { useConnectionStore } from '@/stores/connectionStore'
import EmptyState from '../ui/EmptyState.vue'
import CartItem from './CartItem.vue'
import Button from '../ui/Button.vue'
import StatusBadge from '../ui/StatusBadge.vue'
import { t } from '@/config/i18n'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  locale: {
    type: String,
    default: 'es',
  },
  canSend: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:open', 'updateQuantity', 'remove', 'updateNotes', 'clear', 'sendOrder'])

const cart = useCartStore()
const connection = useConnectionStore()

function close() {
  emit('update:open', false)
}

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}

const sendButtonText = computed(() => {
  if (cart.syncing) return t('cart.sending', props.locale)
  if (!props.canSend) return t('cart.sendDisabledOffline', props.locale)
  return t('cart.sendOrder', props.locale)
})
</script>

<style scoped>
.cart-drawer-overlay {
  position: fixed;
  inset: 0;
  background-color: var(--color-overlay);
  z-index: var(--z-overlay);
  display: flex;
  justify-content: flex-end;
}

.cart-drawer {
  width: 100%;
  max-width: 28rem;
  height: 100%;
  background-color: var(--color-bg-secondary);
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-xl);
  animation: slideIn var(--transition-normal);
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}

.cart-drawer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
}

.cart-drawer__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  margin: 0;
}

.cart-drawer__close {
  background: transparent;
  border: none;
  font-size: var(--font-size-xl);
  cursor: pointer;
  padding: var(--spacing-xs);
  color: var(--color-text-muted);
  min-width: var(--touch-target-md);
  min-height: var(--touch-target-md);
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-drawer__close:hover {
  color: var(--color-text);
}

.cart-drawer__empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-drawer__items {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-md);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.cart-drawer__footer {
  padding: var(--spacing-lg);
  border-top: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.cart-drawer__totals {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.cart-drawer__total-row {
  display: flex;
  justify-content: space-between;
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
}

.cart-drawer__total-row--total {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  padding-top: var(--spacing-sm);
  border-top: 1px solid var(--color-border);
}

.cart-drawer__tax {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  margin: 0;
}

.cart-drawer__actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.cart-drawer__pending {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-md);
  background-color: var(--color-warning-bg);
  border-radius: var(--radius-md);
}

.cart-drawer__pending-desc {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  text-align: center;
  margin: 0;
}

@media (prefers-reduced-motion: reduce) {
  .cart-drawer {
    animation-duration: 0.01ms;
  }
}
</style>
