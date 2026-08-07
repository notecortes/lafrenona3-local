<template>
  <div class="error-state" role="alert">
    <div class="error-state__icon" aria-hidden="true">⚠️</div>
    <h3 class="error-state__title">{{ title }}</h3>
    <p v-if="description" class="error-state__description">
      {{ description }}
    </p>
    <Button
      v-if="showRetry"
      variant="secondary"
      @click="$emit('retry')"
    >
      {{ retryText }}
    </Button>
  </div>
</template>

<script setup>
import Button from './Button.vue'

defineProps({
  title: {
    type: String,
    default: 'Error',
  },
  description: {
    type: String,
    default: '',
  },
  showRetry: {
    type: Boolean,
    default: true,
  },
  retryText: {
    type: String,
    default: 'Reintentar',
  },
})

defineEmits(['retry'])
</script>

<style scoped>
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-3xl) var(--spacing-lg);
  text-align: center;
}

.error-state__icon {
  font-size: 3rem;
  margin-bottom: var(--spacing-md);
}

.error-state__title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-semibold);
  color: var(--color-error);
  margin-bottom: var(--spacing-sm);
}

.error-state__description {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  max-width: 24rem;
  margin-bottom: var(--spacing-lg);
}
</style>
