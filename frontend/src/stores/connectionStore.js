import { defineStore } from 'pinia'

export const useConnectionStore = defineStore('connection', {
  state: () => ({
    online: navigator.onLine,
    status: 'connected',
    retryCount: 0,
    lastCheck: null,
  }),

  getters: {
    isOffline: (state) => !state.online,
    isConnecting: (state) => state.status === 'connecting',
    isReconnecting: (state) => state.status === 'reconnecting',
  },

  actions: {
    setOnline() {
      this.online = true
      this.status = 'connected'
      this.retryCount = 0
      this.lastCheck = new Date().toISOString()
    },

    setOffline() {
      this.online = false
      this.status = 'disconnected'
      this.lastCheck = new Date().toISOString()
    },

    setReconnecting() {
      this.status = 'reconnecting'
      this.retryCount += 1
    },

    checkConnection() {
      this.lastCheck = new Date().toISOString()
      if (navigator.onLine) {
        this.setOnline()
      } else {
        this.setOffline()
      }
    },

    init() {
      this.checkConnection()

      window.addEventListener('online', () => {
        this.setOnline()
      })

      window.addEventListener('offline', () => {
        this.setOffline()
      })
    },
  },
})
