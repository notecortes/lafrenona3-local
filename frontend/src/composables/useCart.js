import { computed } from 'vue'
import { useCartStore } from '@/stores/cartStore'
import { useConnectionStore } from '@/stores/connectionStore'
import { useAlertStore } from '@/stores/alertStore'
import { t } from '@/config/i18n'

export function useCart(locale = 'es') {
  const cart = useCartStore()
  const connection = useConnectionStore()
  const alerts = useAlertStore()

  const canSend = computed(() => {
    return !cart.isEmpty && (connection.online || cart.status === 'pending')
  })

  const sendStatus = computed(() => {
    if (cart.syncing) return 'sending'
    if (cart.status === 'confirmed') return 'confirmed'
    if (cart.status === 'pending') return 'pending'
    return 'ready'
  })

  async function addToCart(product, options = {}) {
    cart.addItem(product, options)
    alerts.success(t('cart.addedToCart', locale))
  }

  async function sendOrder() {
    if (!canSend.value) {
      alerts.error(t('cart.sendDisabled', locale))
      return { success: false }
    }

    const result = await cart.sendOrder()

    if (result.success) {
      alerts.success(t('cart.success', locale, { orderNumber: result.orderId }))
    } else {
      alerts.error(t('cart.syncError', locale))
    }

    return result
  }

  async function syncPending() {
    if (!connection.online || cart.status !== 'pending') {
      return { success: false }
    }

    return await cart.syncPending()
  }

  function updateItemQuantity(index, delta) {
    cart.updateQuantity(index, delta)
  }

  function removeItem(index) {
    cart.removeItem(index)
  }

  function updateItemNotes(index, notes) {
    cart.updateNotes(index, notes)
  }

  function clearCart() {
    cart.clear()
  }

  return {
    cart,
    connection,
    canSend,
    sendStatus,
    addToCart,
    sendOrder,
    syncPending,
    updateItemQuantity,
    removeItem,
    updateItemNotes,
    clearCart,
  }
}
