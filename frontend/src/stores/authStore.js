import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    loading: false,
    error: null,
    theme: localStorage.getItem('theme') || 'light',
    locale: localStorage.getItem('locale') || 'es',
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isSuperAdmin: (state) => state.user?.role === 'superadmin',
    isOwner: (state) => state.user?.role === 'owner',
    isStaff: (state) => ['waiter', 'kitchen', 'bar'].includes(state.user?.role),
    restaurantId: (state) => state.user?.restaurant_id || null,
    restaurant: (state) => state.user?.restaurant || null,
  },

  actions: {
    async login(email, password) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/v1/auth/login', { email, password })
        const { access_token, user } = response.data
        this.token = access_token
        this.user = user
        localStorage.setItem('auth_token', access_token)
        return { success: true, user }
      } catch (err) {
        this.error = err.response?.data?.message || 'Login failed'
        return { success: false, error: this.error }
      } finally {
        this.loading = false
      }
    },

    async fetchUser() {
      if (!this.token) return
      try {
        const response = await api.get('/v1/user')
        this.user = response.data.data
      } catch {
        this.logout()
      }
    },

    async logout() {
      this.$patch({
        user: null,
        token: null,
        error: null,
      })
      localStorage.removeItem('auth_token')

      try {
        await api.post('/v1/auth/logout')
      } catch {
        // Ignore logout errors
      }
    },

    setTheme(theme) {
      this.theme = theme
      localStorage.setItem('theme', theme)
      document.documentElement.setAttribute('data-theme', theme)
    },

    setLocale(locale) {
      this.locale = locale
      localStorage.setItem('locale', locale)
    },

    initTheme() {
      const savedTheme = localStorage.getItem('theme') || 'light'
      this.theme = savedTheme
      document.documentElement.setAttribute('data-theme', savedTheme)
    },

    initLocale() {
      const savedLocale = localStorage.getItem('locale') || 'es'
      this.locale = savedLocale
    },
  },
})
