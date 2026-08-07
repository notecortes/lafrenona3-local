<template>
  <span
    :class="[
      'status-badge',
      `status-badge--${variant}`,
    ]"
    role="status"
  >
    <span class="status-badge__dot" aria-hidden="true"></span>
    <slot></slot>
  </span>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (value) => [
      'success',
      'warning',
      'error',
      'info',
      'kitchen',
      'bar',
    ].includes(value),
  },
})
</script>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-xs) var(--spacing-sm);
  border-radius: var(--radius-full);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.status-badge__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.status-badge--success {
  background-color: var(--color-success-bg);
  color: var(--color-success);
}

.status-badge--success .status-badge__dot {
  background-color: var(--color-success);
}

.status-badge--warning {
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
}

.status-badge--warning .status-badge__dot {
  background-color: var(--color-warning);
}

.status-badge--error {
  background-color: var(--color-error-bg);
  color: var(--color-error);
}

.status-badge--error .status-badge__dot {
  background-color: var(--color-error);
}

.status-badge--info {
  background-color: var(--color-info-bg);
  color: var(--color-info);
}

.status-badge--info .status-badge__dot {
  background-color: var(--color-info);
}

.status-badge--kitchen {
  background-color: #FFF7ED;
  color: var(--color-kitchen);
}

.status-badge--kitchen .status-badge__dot {
  background-color: var(--color-kitchen);
}

.status-badge--bar {
  background-color: #F3E8FF;
  color: var(--color-bar);
}

.status-badge--bar .status-badge__dot {
  background-color: var(--color-bar);
}

[data-theme="dark"] .status-badge--kitchen {
  background-color: #431407;
}

[data-theme="dark"] .status-badge--bar {
  background-color: #2e1065;
}
</style>
