<template>
  <button
    :type="type"
    :class="[
      'btn',
      `btn--${variant}`,
      `btn--${size}`,
      { 'btn--full': fullWidth },
      { 'btn--loading': loading },
      { 'btn--disabled': disabled || loading },
    ]"
    :disabled="disabled || loading"
    :aria-busy="loading"
    :aria-label="ariaLabel"
    v-bind="$attrs"
  >
    <span v-if="loading" class="btn__spinner" aria-hidden="true"></span>
    <slot v-else></slot>
  </button>
</template>

<script setup>
defineProps({
  type: {
    type: String,
    default: 'button',
  },
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'danger', 'ghost', 'icon'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  fullWidth: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  ariaLabel: {
    type: String,
    default: undefined,
  },
})
</script>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-sm);
  font-weight: var(--font-weight-semibold);
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
  cursor: pointer;
  border: 2px solid transparent;
  position: relative;
}

.btn--primary {
  background-color: var(--color-primary);
  color: var(--color-text-inverse);
}

.btn--primary:hover:not(:disabled) {
  background-color: var(--color-primary-dark);
}

.btn--secondary {
  background-color: transparent;
  color: var(--color-primary);
  border-color: var(--color-primary);
}

.btn--secondary:hover:not(:disabled) {
  background-color: var(--color-primary-light);
}

.btn--danger {
  background-color: var(--color-error);
  color: var(--color-text-inverse);
}

.btn--danger:hover:not(:disabled) {
  background-color: #b91c1c;
}

.btn--ghost {
  background-color: transparent;
  color: var(--color-text-muted);
}

.btn--ghost:hover:not(:disabled) {
  background-color: var(--color-bg-tertiary);
  color: var(--color-text);
}

.btn--icon {
  padding: var(--spacing-sm);
  background-color: transparent;
  color: var(--color-text-muted);
}

.btn--icon:hover:not(:disabled) {
  background-color: var(--color-bg-tertiary);
  color: var(--color-text);
}

.btn--sm {
  padding: var(--spacing-xs) var(--spacing-sm);
  font-size: var(--font-size-xs);
  min-height: var(--touch-target-sm);
}

.btn--md {
  padding: var(--spacing-sm) var(--spacing-lg);
  font-size: var(--font-size-sm);
  min-height: var(--touch-target-md);
}

.btn--lg {
  padding: var(--spacing-md) var(--spacing-xl);
  font-size: var(--font-size-base);
  min-height: var(--touch-target-lg);
}

.btn--full {
  width: 100%;
}

.btn--loading,
.btn--disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn__spinner {
  width: 16px;
  height: 16px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .btn__spinner {
    animation-duration: 0.01ms;
  }
}
</style>
