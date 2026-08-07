<template>
  <Modal
    :model-value="open"
    :title="productName"
    @update:model-value="$emit('update:open', $event)"
  >
    <div class="product-detail">
      <div class="product-detail__image-container">
        <img
          v-if="product.image"
          :src="product.image"
          :alt="productName"
          class="product-detail__image"
        />
        <div v-else class="product-detail__placeholder">
          🍽️
        </div>
      </div>

      <div class="product-detail__content">
        <p v-if="product.description" class="product-detail__description">
          {{ product.description }}
        </p>

        <div v-if="product.allergens && product.allergens.length" class="product-detail__allergens">
          <h4 class="product-detail__label">{{ t('product.allergens', locale) }}</h4>
          <ul class="product-detail__allergen-list">
            <li v-for="allergen in product.allergens" :key="allergen" class="allergen-tag">
              {{ allergen }}
            </li>
          </ul>
        </div>

        <div class="product-detail__price">
          <span class="product-detail__price-value">{{ formatPrice(product.price) }}€</span>
        </div>

        <div class="product-detail__quantity">
          <label class="product-detail__label">{{ t('product.quantity', locale) }}</label>
          <div class="product-detail__quantity-controls">
            <button
              class="product-detail__quantity-btn"
              @click="quantity = Math.max(1, quantity - 1)"
              :aria-label="`Reducir cantidad`"
            >
              -
            </button>
            <span class="product-detail__quantity-value" aria-live="polite">{{ quantity }}</span>
            <button
              class="product-detail__quantity-btn"
              @click="quantity += 1"
              :aria-label="`Aumentar cantidad`"
            >
              +
            </button>
          </div>
        </div>

        <div class="product-detail__notes">
          <label :for="`notes-${id}`" class="product-detail__label">{{ t('product.notes', locale) }}</label>
          <textarea
            :id="`notes-${id}`"
            v-model="notes"
            :placeholder="t('product.notesPlaceholder', locale)"
            rows="3"
            class="product-detail__textarea"
          ></textarea>
        </div>

        <Button
          v-if="product.is_available"
          variant="primary"
          size="lg"
          fullWidth
          :loading="adding"
          @click="handleAdd"
        >
          {{ adding ? t('cart.sending', locale) : t('product.addToCart', locale) }}
        </Button>

        <StatusBadge v-else variant="error" class="product-detail__unavailable">
          {{ t('product.unavailable', locale) }}
        </StatusBadge>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import Modal from '../ui/Modal.vue'
import Button from '../ui/Button.vue'
import StatusBadge from '../ui/StatusBadge.vue'
import { t } from '@/config/i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  product: {
    type: Object,
    required: true,
  },
  locale: {
    type: String,
    default: 'es',
  },
})

const emit = defineEmits(['update:modelValue', 'add'])

const id = computed(() => `product-${props.product?.id || 'unknown'}`)
const productName = computed(() => {
  if (!props.product) return ''
  if (typeof props.product.name === 'object') {
    return props.product.name.en || Object.values(props.product.name)[0] || ''
  }
  return props.product.name || ''
})

const quantity = ref(1)
const notes = ref('')
const adding = ref(false)

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}

async function handleAdd() {
  adding.value = true
  try {
    emit('add', {
      product: props.product,
      quantity: quantity.value,
      notes: notes.value,
    })
    emit('update:modelValue', false)
    quantity.value = 1
    notes.value = ''
  } finally {
    adding.value = false
  }
}
</script>

<style scoped>
.product-detail__image-container {
  height: 16rem;
  overflow: hidden;
  background-color: var(--color-bg-tertiary);
}

.product-detail__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.product-detail__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 4rem;
  background-color: var(--color-bg-tertiary);
}

.product-detail__content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.product-detail__description {
  font-size: var(--font-size-base);
  color: var(--color-text-muted);
  line-height: var(--line-height-relaxed);
}

.product-detail__allergens {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.product-detail__label {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
}

.product-detail__allergen-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-xs);
  list-style: none;
  padding: 0;
  margin: 0;
}

.allergen-tag {
  font-size: var(--font-size-xs);
  padding: var(--spacing-xs) var(--spacing-sm);
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
  border-radius: var(--radius-full);
  font-weight: var(--font-weight-medium);
}

.product-detail__price {
  padding: var(--spacing-md) 0;
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
}

.product-detail__price-value {
  font-size: var(--font-size-2xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-success);
}

.product-detail__quantity {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.product-detail__quantity-controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-md);
}

.product-detail__quantity-btn {
  width: var(--touch-target-md);
  height: var(--touch-target-md);
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-bg-tertiary);
  border-radius: var(--radius-md);
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
}

.product-detail__quantity-btn:hover {
  background-color: var(--color-border-strong);
}

.product-detail__quantity-value {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  min-width: 3rem;
  text-align: center;
}

.product-detail__notes {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.product-detail__textarea {
  width: 100%;
  padding: var(--spacing-sm) var(--spacing-md);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-family: inherit;
  resize: vertical;
  min-height: var(--touch-target-lg);
}

.product-detail__textarea:focus {
  outline: none;
  border-color: var(--color-focus);
}

.product-detail__unavailable {
  margin-top: var(--spacing-md);
}
</style>
