import { defineStore } from 'pinia'

export const useAlertStore = defineStore('alerts', {
  state: () => ({
    toasts: [],
  }),

  getters: {
    activeToasts: (state) => state.toasts.filter((t) => t.visible),
    errorCount: (state) => state.toasts.filter((t) => t.type === 'error').length,
  },

  actions: {
    addToast(message, type = 'info', duration = 3000) {
      const id = Date.now() + Math.random()
      const toast = {
        id,
        message,
        type,
        visible: true,
        duration,
        createdAt: new Date().toISOString(),
      }

      this.toasts.push(toast)

      if (duration > 0) {
        setTimeout(() => {
          this.removeToast(id)
        }, duration)
      }

      return id
    },

    removeToast(id) {
      const toast = this.toasts.find((t) => t.id === id)
      if (toast) {
        toast.visible = false
        setTimeout(() => {
          this.toasts = this.toasts.filter((t) => t.id !== id)
        }, 300)
      }
    },

    success(message) {
      return this.addToast(message, 'success')
    },

    error(message) {
      return this.addToast(message, 'error')
    },

    warning(message) {
      return this.addToast(message, 'warning')
    },

    info(message) {
      return this.addToast(message, 'info')
    },

    clear() {
      this.toasts = []
    },
  },
})
