<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="modal-overlay"
      @keydown.escape="close"
      role="dialog"
      :aria-modal="true"
      :aria-label="title"
    >
      <div class="modal" ref="modalRef">
        <div class="modal__header">
          <h2 class="modal__title">{{ title }}</h2>
          <button
            class="modal__close"
            @click="close"
            :aria-label="closeLabel"
          >
            ✕
          </button>
        </div>
        
        <div class="modal__body">
          <slot></slot>
        </div>
        
        <div v-if="$slots.footer" class="modal__footer">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAccessibility } from '@/composables/useAccessibility'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  closeLabel: {
    type: String,
    default: 'Cerrar',
  },
})

const emit = defineEmits(['update:modelValue'])

const modalRef = ref(null)
const { trapFocus, releaseFocus } = useAccessibility()

function close() {
  emit('update:modelValue', false)
}

onMounted(() => {
  if (props.modelValue && modalRef.value) {
    const release = trapFocus(modalRef.value)
    onUnmounted(release)
  }
})

onUnmounted(() => {
  releaseFocus()
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background-color: var(--color-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: var(--z-modal);
  padding: var(--spacing-md);
}

.modal {
  background-color: var(--color-bg-secondary);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-xl);
  width: 100%;
  max-width: 32rem;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  animation: modalSlideIn var(--transition-normal);
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
}

.modal__title {
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.modal__close {
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

.modal__close:hover {
  color: var(--color-text);
}

.modal__body {
  padding: var(--spacing-lg);
  overflow-y: auto;
  flex: 1;
}

.modal__footer {
  padding: var(--spacing-lg);
  border-top: 1px solid var(--color-border);
  display: flex;
  gap: var(--spacing-md);
  justify-content: flex-end;
}

@media (prefers-reduced-motion: reduce) {
  .modal {
    animation-duration: 0.01ms;
  }
}
</style>
