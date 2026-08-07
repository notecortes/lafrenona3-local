<template>
  <div class="empty-state" role="status">
    <div v-if="icon" class="empty-state__icon" aria-hidden="true">
      {{ icon }}
    </div>
    <h3 class="empty-state__title">{{ title }}</h3>
    <p v-if="description" class="empty-state__description">
      {{ description }}
    </p>
    <slot name="action"></slot>
  </div>
</template>

<script setup>
defineProps({
  icon: {
    type: String,
    default: '📭',
  },
  title: {
    type: String,
    default: 'No hay contenido',
  },
  description: {
    type: String,
    default: '',
  },
})
</script>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-3xl) var(--spacing-lg);
  text-align: center;
}

.empty-state__icon {
  font-size: 3rem;
  margin-bottom: var(--spacing-md);
  opacity: 0.6;
}

.empty-state__title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin-bottom: var(--spacing-sm);
}

.empty-state__description {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  max-width: 24rem;
}
</style>
