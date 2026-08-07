<template>
  <div
    :class="[
      'connection-status',
      `connection-status--${status}`,
    ]"
    role="status"
    aria-live="polite"
  >
    <span class="connection-status__dot" aria-hidden="true"></span>
    <span class="connection-status__text">{{ text }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useConnectionStore } from '@/stores/connectionStore'
import { t } from '@/config/i18n'

const props = defineProps({
  locale: {
    type: String,
    default: 'es',
  },
})

const connection = useConnectionStore()

const status = computed(() => {
  if (!connection.online) return 'offline'
  if (connection.status === 'reconnecting') return 'reconnecting'
  return 'online'
})

const text = computed(() => {
  if (status.value === 'offline') {
    return t('status.disconnected', props.locale)
  }
  if (status.value === 'reconnecting') {
    return t('status.reconnecting', props.locale)
  }
  return t('status.connected', props.locale)
})
</script>

<style scoped>
.connection-status {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-xs) var(--spacing-sm);
  border-radius: var(--radius-full);
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
}

.connection-status__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.connection-status__text {
  white-space: nowrap;
}

.connection-status--online {
  background-color: var(--color-success-bg);
  color: var(--color-success);
}

.connection-status--online .connection-status__dot {
  background-color: var(--color-success);
}

.connection-status--offline {
  background-color: var(--color-error-bg);
  color: var(--color-error);
}

.connection-status--offline .connection-status__dot {
  background-color: var(--color-error);
}

.connection-status--reconnecting {
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
}

.connection-status--reconnecting .connection-status__dot {
  background-color: var(--color-warning);
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .connection-status--reconnecting .connection-status__dot {
    animation-duration: 0.01ms;
  }
}
</style>
