import { defineStore } from 'pinia'
import api from '@/services/api'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    orderId: null,
    status: 'local',
    error: null,
    syncing: false,
    lastSync: null,
  }),

  getters: {
    itemCount: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    total: (state) => state.items.reduce((sum, item) => sum + (item.unitPrice * item.quantity), 0),
    isEmpty: (state) => state.items.length === 0,
    hasPendingSync: (state) => state.status === 'pending',
  },

  actions: {
    addItem(product, options = {}) {
      const notes = options.notes || ''
      const existing = this.items.find(
        (item) => item.productId === product.id && item.notes === notes
      )

      if (existing) {
        existing.quantity += 1
      } else {
        this.items.push({
          productId: product.id,
          productName: typeof product.name === 'object' ? product.name.en || Object.values(product.name)[0] || '' : product.name,
          unitPrice: product.price,
          quantity: 1,
          notes: notes,
          allergens: product.allergens || [],
          image: product.image || null,
        })
      }

      this.status = 'local'
      this.error = null
    },

    removeItem(index) {
      this.items.splice(index, 1)
      if (this.items.length === 0) {
        this.status = 'local'
      }
    },

    updateQuantity(index, delta) {
      const item = this.items[index]
      if (!item) return

      item.quantity += delta
      if (item.quantity <= 0) {
        this.items.splice(index, 1)
        if (this.items.length === 0) {
          this.status = 'local'
        }
      }
    },

    updateNotes(index, notes) {
      if (this.items[index]) {
        this.items[index].notes = notes
      }
    },

    clear() {
      this.items = []
      this.orderId = null
      this.status = 'local'
      this.error = null
    },

    async sendOrder() {
      if (this.isEmpty) {
        this.error = 'cart.sendDisabled'
        return { success: false }
      }

      this.syncing = true
      this.error = null

      try {
        const response = await api.post('/v1/client/orders', {
          session_token: null,
          restaurant_slug: null,
        })

        this.orderId = response.data.data.id
        this.status = 'sending'

        const itemsData = this.items.map((item) => ({
          product_id: item.productId,
          quantity: item.quantity,
          unit_price: item.unitPrice,
          notes: item.notes,
        }))

        const itemsResponse = await api.post(`/v1/client/orders/${this.orderId}/items`, {
          items: itemsData,
        })

        this.status = 'confirmed'
        this.lastSync = new Date().toISOString()

        return { success: true, orderId: this.orderId }
      } catch (err) {
        this.status = 'pending'
        this.error = err.response?.data?.message || 'syncError'
        return { success: false, error: this.error }
      } finally {
        this.syncing = false
      }
    },

    async syncPending() {
      if (this.status !== 'pending' || this.isEmpty) {
        return { success: false }
      }

      this.syncing = true
      this.error = null

      try {
        const response = await api.post('/v1/client/orders', {
          session_token: null,
          restaurant_slug: null,
        })

        this.orderId = response.data.data.id
        this.status = 'sending'

        const itemsData = this.items.map((item) => ({
          product_id: item.productId,
          quantity: item.quantity,
          unit_price: item.unitPrice,
          notes: item.notes,
        }))

        await api.post(`/v1/client/orders/${this.orderId}/items`, {
          items: itemsData,
        })

        this.status = 'confirmed'
        this.lastSync = new Date().toISOString()

        return { success: true, orderId: this.orderId }
      } catch (err) {
        this.error = err.response?.data?.message || 'syncError'
        return { success: false, error: this.error }
      } finally {
        this.syncing = false
      }
    },
  },
})
