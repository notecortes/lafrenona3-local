<template>
  <div class="assistance-actions">
    <Button
      variant="secondary"
      size="lg"
      fullWidth
      :loading="loading"
      @click="handleCallWaiter"
    >
      <span aria-hidden="true">🙋</span>
      {{ t('order.actions.callWaiter', locale) }}
    </Button>

    <Button
      variant="secondary"
      size="lg"
      fullWidth
      :loading="loading"
      @click="handleRequestBill"
    >
      <span aria-hidden="true">🧾</span>
      {{ t('order.actions.requestBill', locale) }}
    </Button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'
import Button from '../ui/Button.vue'
import { t } from '@/config/i18n'
import { useAlertStore } from '@/stores/alertStore'

const props = defineProps({
  locale: {
    type: String,
    default: 'es',
  },
})

const emit = defineEmits(['success'])

const alertStore = useAlertStore()
const loading = ref(false)

async function handleCallWaiter() {
  loading.value = true
  try {
    await api.post('/v1/client/assistance', {
      type: 'waiter',
    })
    alertStore.success(t('order.actions.callWaiterSuccess', props.locale))
    emit('success', 'waiter')
  } catch (err) {
    alertStore.error(err.response?.data?.message || t('app.error', props.locale))
  } finally {
    loading.value = false
  }
}

async function handleRequestBill() {
  loading.value = true
  try {
    await api.post('/v1/client/assistance', {
      type: 'bill',
    })
    alertStore.success(t('order.actions.requestBillSuccess', props.locale))
    emit('success', 'bill')
  } catch (err) {
    alertStore.error(err.response?.data?.message || t('app.error', props.locale))
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.assistance-actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}
</style>
