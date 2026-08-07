<template>
  <div class="order-timeline">
    <h3 class="order-timeline__title">{{ t('order.timeline', locale) }}</h3>

    <div class="order-timeline__steps">
      <div
        v-for="step in steps"
        :key="step.key"
        :class="[
          'order-timeline__step',
          { 'order-timeline__step--active': step.active },
          { 'order-timeline__step--completed': step.completed },
          { 'order-timeline__step--current': step.current },
        ]"
      >
        <div class="order-timeline__step-header">
          <div class="order-timeline__step-icon" aria-hidden="true">
            <span v-if="step.completed">✓</span>
            <span v-else-if="step.current">●</span>
            <span v-else>○</span>
          </div>
          <div class="order-timeline__step-content">
            <h4 class="order-timeline__step-title">{{ step.title }}</h4>
            <p class="order-timeline__step-desc">{{ step.description }}</p>
          </div>
        </div>

        <div
          v-if="step.next"
          class="order-timeline__connector"
          aria-hidden="true"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@/config/i18n'

const props = defineProps({
  status: {
    type: String,
    default: 'pending',
  },
  locale: {
    type: String,
    default: 'es',
  },
})

const steps = computed(() => {
  const statuses = ['pending', 'accepted', 'cooking', 'ready', 'delivered']
  const currentIndex = statuses.indexOf(props.status)

  return statuses.map((status, index) => ({
    key: status,
    title: t(`order.statuses.${status}`, props.locale),
    description: t(`order.statuses.${status}Desc`, props.locale),
    completed: index < currentIndex,
    current: index === currentIndex,
    next: index < statuses.length - 1,
  }))
})
</script>

<style scoped>
.order-timeline {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.order-timeline__title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin-bottom: var(--spacing-sm);
}

.order-timeline__steps {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.order-timeline__step {
  display: flex;
  flex-direction: column;
}

.order-timeline__step-header {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-md);
}

.order-timeline__step-icon {
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background-color: var(--color-bg-tertiary);
  color: var(--color-text-muted);
  font-size: var(--font-size-sm);
  flex-shrink: 0;
}

.order-timeline__step--completed .order-timeline__step-icon {
  background-color: var(--color-success);
  color: var(--color-text-inverse);
}

.order-timeline__step--current .order-timeline__step-icon {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

.order-timeline__step-content {
  flex: 1;
  padding-top: var(--spacing-xs);
}

.order-timeline__step-title {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin: 0;
}

.order-timeline__step--completed .order-timeline__step-title {
  color: var(--color-success);
}

.order-timeline__step--current .order-timeline__step-title {
  color: var(--color-primary);
}

.order-timeline__step-desc {
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
  margin: var(--spacing-xs) 0 0;
}

.order-timeline__connector {
  width: 2px;
  height: 1rem;
  background-color: var(--color-border);
  margin-left: 1rem;
  margin-top: var(--spacing-xs);
}

.order-timeline__step--completed .order-timeline__connector {
  background-color: var(--color-success);
}

@media (prefers-reduced-motion: reduce) {
  .order-timeline__step--current .order-timeline__step-icon {
    animation-duration: 0.01ms;
  }
}
</style>
