import { defineStore } from 'pinia'
import api from '@/services/api'

export const useClientMenuStore = defineStore('clientMenu', {
  state: () => ({
    restaurant: null,
    categories: [],
    products: [],
    loading: false,
    error: null,
    sessionToken: null,
    tableNumber: null,
    locale: localStorage.getItem('locale') || 'es',
  }),

  getters: {
    translatedName: (state) => (nameData) => {
      if (!nameData) return ''
      if (typeof nameData === 'string') return nameData
      return nameData[state.locale] || nameData.en || Object.values(nameData)[0] || ''
    },

    filteredProducts: (state) => (categoryId) => {
      if (!categoryId) return state.products
      return state.products.filter(p => p.category_id === categoryId)
    },

    categoryCount: (state) => (categoryId) => {
      return state.products.filter(p => p.category_id === categoryId).length
    },
  },

  actions: {
    async fetchMenu(token = null) {
      this.loading = true
      this.error = null
      try {
        const params = {}
        if (token) params.token = token

        const res = await api.get('/v1/client/menu', { params })

        this.restaurant = res.data.restaurant || null
        this.categories = res.data.categories || []
        this.products = res.data.products || []

        if (res.data.session_token) {
          this.sessionToken = res.data.session_token
          this.tableNumber = res.data.table_number
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load menu'
      } finally {
        this.loading = false
      }
    },

    setLocale(locale) {
      this.locale = locale
      localStorage.setItem('locale', locale)
    },
  },
})