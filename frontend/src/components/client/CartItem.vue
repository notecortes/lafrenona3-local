<template>
  <div class="cart-item">
    <div class="cart-item__content">
      <div class="cart-item__info">
        <h4 class="cart-item__name">{{ item.productName }}</h4>
        <p v-if="item.notes" class="cart-item__notes">{{ item.notes }}</p>
        <p class="cart-item__price">{{ formatPrice(item.unitPrice) }}€ × {{ item.quantity }}</p>
      </div>

      <div class="cart-item__controls">
        <button
          class="cart-item__control-btn"
          @click="$emit('updateQuantity', -1)"
          :aria-label="`Reducir cantidad de ${item.productName}`"
        >
          −
        </button>
        <span class="cart-item__quantity" aria-live="polite">{{ item.quantity }}</span>
        <button
          class="cart-item__control-btn"
          @click="$emit('updateQuantity', 1)"
          :aria-label="`Aumentar cantidad de ${item.productName}`"
        >
          +
        </button>
      </div>

      <button
        class="cart-item__remove"
        @click="$emit('remove')"
        :aria-label="`Eliminar ${item.productName} del carrito`"
      >
        🗑️
      </button>
    </div>

    <div class="cart-item__notes">
      <label :for="`notes-${index}`" class="cart-item__label">{{ t('cart.notes', locale) }}</label>
      <textarea
        :id="`notes-${index}`"
        v-model="localNotes"
        :placeholder="t('cart.notesPlaceholder', locale)"
        rows="2"
        class="cart-item__textarea"
        @input="$emit('updateNotes', localNotes)"
      ></textarea>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { t } from '@/config/i18n'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    required: true,
  },
  locale: {
    type: String,
    default: 'es',
  },
})

const emit = defineEmits(['updateQuantity', 'remove', 'updateNotes'])

const localNotes = ref(props.item.notes || '')

watch(
  () => props.item.notes,
  (value) => {
    localNotes.value = value || ''
  }
)

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}
</script>

<style scoped>
.cart-item {
  background-color: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-md);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.cart-item__content {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.cart-item__info {
  flex: 1;
  min-width: 0;
}

.cart-item__name {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cart-item__notes {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  margin: 0;
  font-style: italic;
}

.cart-item__price {
  font-size: var(--font-size-xs);
  color: var(--color-success);
  font-weight: var(--font-weight-semibold);
  margin: var(--spacing-xs) 0 0;
}

.cart-item__controls {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.cart-item__control-btn {
  width: var(--touch-target-sm);
  height: var(--touch-target-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-bg-tertiary);
  border-radius: var(--radius-md);
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.cart-item__control-btn:hover {
  background-color: var(--color-border-strong);
}

.cart-item__quantity {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-bold);
  min-width: 2rem;
  text-align: center;
}

.cart-item__remove {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: var(--spacing-xs);
  font-size: var(--font-size-lg);
  opacity: 0.6;
  min-width: var(--touch-target-sm);
  min-height: var(--touch-target-sm);
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-item__remove:hover {
  opacity: 1;
}

.cart-item__notes {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.cart-item__label {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-muted);
}

.cart-item__textarea {
  width: 100%;
  padding: var(--spacing-xs) var(--spacing-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: var(--font-size-xs);
  font-family: inherit;
  resize: vertical;
  min-height: 2.5rem;
}

.cart-item__textarea:focus {
  outline: none;
  border-color: var(--color-focus);
}
</style>
