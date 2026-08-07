<template>
  <Transition name="toast">
    <div
      v-if="toast.visible"
      :class="[
        'toast',
        `toast--${toast.type}`,
      ]"
      role="alert"
      :aria-live="polite"
    >
      <span class="toast__icon" aria-hidden="true">{{ icon }}</span>
      <span class="toast__message">{{ toast.message }}</span>
      <button
        class="toast__close"
        @click="removeToast(toast.id)"
        :aria-label="'Cerrar notificación'"
      >
        ✕
      </button>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue'
import { useAlertStore } from '@/stores/alertStore'

const alertStore = useAlertStore()

const toast = computed(() => alertStore.activeToasts[0] || { visible: false })
const icon = computed(() => {
  switch (toast.value.type) {
    case 'success':
      return '✓'
    case 'error':
      return '✕'
    case 'warning':
      return '⚠'
    default:
      return 'ℹ'
  }
})

function removeToast(id) {
  alertStore.removeToast(id)
}
</script>

<style scoped>
.toast {
  position: fixed;
  bottom: var(--spacing-lg);
  left: 50%;
  transform: translateX(-50%);
  background-color: var(--color-bg-secondary);
  color: var(--color-text);
  padding: var(--spacing-md) var(--spacing-lg);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  max-width: calc(100vw - var(--spacing-lg) * 2);
  z-index: var(--z-toast);
  border-left: 4px solid var(--color-info);
}

.toast--success {
  border-left-color: var(--color-success);
}

.toast--error {
  border-left-color: var(--color-error);
}

.toast--warning {
  border-left-color: var(--color-warning);
}

.toast__icon {
  font-size: var(--font-size-lg);
  flex-shrink: 0;
}

.toast__message {
  flex: 1;
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
}

.toast__close {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: var(--spacing-xs);
  color: var(--color-text-muted);
  font-size: var(--font-size-lg);
  min-width: var(--touch-target-sm);
  min-height: var(--touch-target-sm);
  display: flex;
  align-items: center;
  justify-content: center;
}

.toast__close:hover {
  color: var(--color-text);
}

.toast-enter-active,
.toast-leave-active {
  transition: all var(--transition-normal);
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(20px);
}

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active {
    transition-duration: 0.01ms;
  }
}
</style>
