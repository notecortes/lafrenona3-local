<template>
  <article
    :class="[
      'product-card',
      { 'product-card--unavailable': !product.is_available },
    ]"
    :aria-label="productName"
  >
    <div class="product-card__image-container">
      <img
        v-if="product.image"
        :src="product.image"
        :alt="productName"
        class="product-card__image"
        loading="lazy"
      />
      <div v-else class="product-card__placeholder" aria-hidden="true">
        🍽️
      </div>
    </div>

    <div class="product-card__content">
      <h3 class="product-card__name">{{ productName }}</h3>
      
      <p v-if="product.description" class="product-card__description">
        {{ product.description }}
      </p>

      <ul v-if="product.allergens && product.allergens.length" class="product-card__allergens" aria-label="Alérgenos">
        <li v-for="allergen in product.allergens" :key="allergen" class="allergen-tag">
          {{ allergen }}
        </li>
      </ul>

      <div class="product-card__footer">
        <span class="product-card__price" aria-label="Precio: {{ formatPrice(product.price) }} euros">
          {{ formatPrice(product.price) }}€
        </span>

        <Button
          v-if="product.is_available"
          variant="primary"
          size="sm"
          @click="$emit('add', product)"
          :aria-label="`Añadir ${productName} al pedido`"
        >
          + Añadir
        </Button>

        <StatusBadge v-else variant="error" class="product-card__badge">
          Agotado
        </StatusBadge>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import Button from '../ui/Button.vue'
import StatusBadge from '../ui/StatusBadge.vue'

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
})

defineEmits(['add'])

const productName = computed(() => {
  if (typeof props.product.name === 'object') {
    return props.product.name.en || Object.values(props.product.name)[0] || ''
  }
  return props.product.name || ''
})

function formatPrice(price) {
  return parseFloat(price).toFixed(2)
}
</script>

<style scoped>
.product-card {
  background-color: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all var(--transition-fast);
}

.product-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.product-card--unavailable {
  opacity: 0.6;
}

.product-card--unavailable:hover {
  transform: none;
  box-shadow: none;
}

.product-card__image-container {
  height: 12rem;
  overflow: hidden;
  background-color: var(--color-bg-tertiary);
}

.product-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.product-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  background-color: var(--color-bg-tertiary);
}

.product-card__content {
  padding: var(--spacing-md);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.product-card__name {
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin: 0;
}

.product-card__description {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-card__allergens {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-xs);
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

.product-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--spacing-sm);
  margin-top: auto;
}

.product-card__price {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-success);
}

.product-card__badge {
  margin-left: auto;
}

@media (max-width: 480px) {
  .product-card__image-container {
    height: 10rem;
  }

  .product-card__content {
    padding: var(--spacing-sm);
  }
}
</style>
