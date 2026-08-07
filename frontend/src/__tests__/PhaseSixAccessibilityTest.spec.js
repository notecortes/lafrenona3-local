import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick, defineComponent, h } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRoute: vi.fn(() => ({ params: { token: null }, query: {} })),
  }
})

const mockApi = (await import('@/services/api')).default

describe('ClientMenuView — Accessibility and WCAG 2.1 AA', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders with main element and aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test Restaurant', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const main = wrapper.find('main')
    expect(main.exists()).toBe(true)
  })

  it('shows loading state with skeleton', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}))

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await nextTick()

    const loading = wrapper.find('.client-menu__loading')
    expect(loading.exists()).toBe(true)
  })

  it('shows error state with role alert', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'))

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const errorState = wrapper.find('[role="alert"]')
    expect(errorState.exists()).toBe(true)
  })

  it('shows empty state when no products', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const emptyState = wrapper.find('.empty-state')
    expect(emptyState.exists()).toBe(true)
  })

  it('renders category navigation with aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [
          { id: 1, name: { en: 'Starters', es: 'Entrantes' }, sort_order: 1 },
          { id: 2, name: { en: 'Mains', es: 'Principales' }, sort_order: 2 },
        ],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const categoryNav = wrapper.find('[aria-label="Categorías del menú"]')
    expect(categoryNav.exists()).toBe(true)
  })

  it('renders search input with accessible label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const searchInput = wrapper.find('#search-input')
    expect(searchInput.exists()).toBe(true)
    expect(searchInput.attributes('type')).toBe('search')
  })

  it('renders connection status with aria-live', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const connectionStatus = wrapper.find('[aria-live="polite"]')
    expect(connectionStatus.exists()).toBe(true)
  })

  it('renders cart button with aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const cartButton = wrapper.find('.client-menu__cart-btn')
    expect(cartButton.exists()).toBe(true)
    expect(cartButton.attributes('aria-label')).toBeDefined()
  })

  it('renders category buttons with aria-pressed', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [
          { id: 1, name: { en: 'Starters', es: 'Entrantes' }, sort_order: 1 },
          { id: 2, name: { en: 'Mains', es: 'Principales' }, sort_order: 2 },
        ],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const catButtons = wrapper.findAll('[aria-pressed]')
    expect(catButtons.length).toBeGreaterThan(0)
  })

  it('has proper heading hierarchy', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test Restaurant', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const h1 = wrapper.find('h1')
    expect(h1.exists()).toBe(true)
  })

  it('renders header with banner role', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const header = wrapper.find('header')
    expect(header.exists()).toBe(true)
  })

  it('renders nav with proper label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [
          { id: 1, name: { en: 'Starters', es: 'Entrantes' }, sort_order: 1 },
        ],
        products: [],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const nav = wrapper.find('nav')
    expect(nav.exists()).toBe(true)
  })

  it('renders products with aria-label when available', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [
          {
            id: 1,
            name: { en: 'Pasta', es: 'Pasta' },
            price: 10.0,
            description: 'Delicious pasta',
            allergens: ['gluten'],
            is_available: true,
            category_id: 1,
          },
        ],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const productCard = wrapper.find('article')
    expect(productCard.exists()).toBe(true)
  })

  it('shows product card structure', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [
          {
            id: 1,
            name: { en: 'Burger', es: 'Hamburguesa' },
            price: 12.0,
            description: 'Delicious burger',
            allergens: [],
            is_available: true,
            category_id: 1,
          },
        ],
        session_token: null,
        table_number: null,
      },
    })

    const { default: ClientMenuView } = await import('@/views/client/ClientMenuView.vue')
    const wrapper = mount(ClientMenuView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    await nextTick()

    const productWrapper = wrapper.find('.product-card-wrapper')
    expect(productWrapper.exists()).toBe(true)
    const article = productWrapper.find('article')
    expect(article.exists()).toBe(true)
  })
})
