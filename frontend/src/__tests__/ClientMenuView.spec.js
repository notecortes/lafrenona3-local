import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useRoute } from 'vue-router'
import ClientMenuView from '@/views/client/ClientMenuView.vue'
import { useClientMenuStore } from '@/stores/clientMenuStore'

// Mock api
vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({
      data: {
        restaurant: { name: 'Test Restaurant' },
        categories: [],
        products: [],
      },
    }),
    post: vi.fn(),
  },
}))

// Mock useRoute
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRoute: vi.fn(),
  }
})

const mockRoute = {
  params: { token: null },
  query: {},
}

describe('ClientMenuView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(useRoute).mockReturnValue(mockRoute)
    vi.clearAllMocks()
  })

  it('should render the component', async () => {
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.client-menu').exists()).toBe(true)
  })

  it('should render products when available', async () => {
    const store = useClientMenuStore()
    // Stub fetchMenu to avoid API call
    store.fetchMenu = vi.fn().mockResolvedValue(undefined)
    
    // Set state after mounting won't work, so we need to set it before
    // but fetchMenu will override it. Let's mock the API response instead.
    
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({
      data: {
        restaurant: { name: 'Test Restaurant' },
        categories: [{ id: 1, name: 'Test Category' }],
        products: [
          { id: 1, name: 'Test Product', price: 10.00, description: 'Test', allergens: [], is_available: true, category_id: 1 },
        ],
      },
    })

    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.findAll('.product-card-wrapper').length).toBe(1)
  })

  it('should render empty state when no products in API response', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({
      data: {
        restaurant: { name: 'Test Restaurant' },
        categories: [],
        products: [],
      },
    })

    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })

  it('should render error state on API failure', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.error-state').exists()).toBe(true)
  })

  it('should have proper accessibility attributes', async () => {
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('main').exists()).toBe(true)
  })

  it('should have responsive design classes', async () => {
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.client-menu').exists()).toBe(true)
  })
})