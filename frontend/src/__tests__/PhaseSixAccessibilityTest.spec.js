import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import ClientMenuView from '@/views/client/ClientMenuView.vue'
import { useClientMenuStore } from '@/stores/clientMenu'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn()
  }
}))

const mockApi = (await import('@/services/api')).default

describe('ClientMenuView — Accessibility and WCAG 2.1 AA', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const createRouterAndMount = async (path = '/client/menu') => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path, name: 'ClientMenu', component: ClientMenuView }]
    })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(ClientMenuView, { global: { plugins: [router] } })
    return wrapper
  }

  it('renders with root aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test Restaurant', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const main = wrapper.find('[role="main"]')
    expect(main.attributes('aria-label')).toBe('Restaurant Menu')
  })

  it('shows loading state with aria-live', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}))

    const wrapper = await createRouterAndMount('/client/menu')
    await nextTick()

    const loading = wrapper.find('[role="status"]')
    expect(loading.exists()).toBe(true)
    expect(loading.attributes('aria-live')).toBe('polite')
  })

  it('shows error state with role alert', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'))

    const wrapper = await createRouterAndMount('/client/menu')
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
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const emptyState = wrapper.find('[role="status"]')
    expect(emptyState.exists()).toBe(true)
    expect(emptyState.text()).toContain('No menu items')
  })

  it('renders category buttons with aria-pressed', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [
          { id: 1, name: { en: 'Starters', es: 'Entrantes' }, sort_order: 1 },
          { id: 2, name: { en: 'Mains', es: 'Principales' }, sort_order: 2 }
        ],
        products: [],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const buttons = wrapper.findAll('.cat-btn')
    expect(buttons.length).toBe(2)

    buttons.forEach((btn) => {
      expect(btn.attributes('aria-pressed')).toBeDefined()
      expect(btn.attributes('aria-label')).toBeDefined()
    })
  })

  it('renders product cards with price aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [{ id: 1, name: { en: 'Starters' }, sort_order: 1 }],
        products: [
          { id: 1, category_id: 1, name: { en: 'Bruschetta' }, description: { en: 'Bread' }, price: 8.50, allergens: ['gluten'] }
        ],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const priceEl = wrapper.find('.price')
    expect(priceEl.exists()).toBe(true)
    expect(priceEl.text()).toContain('8.50')
  })

  it('renders allergens list with aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [{ id: 1, name: { en: 'Starters' }, sort_order: 1 }],
        products: [
          { id: 1, category_id: 1, name: { en: 'Product' }, price: 10.00, allergens: ['gluten', 'dairy'] }
        ],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const allergensList = wrapper.find('[aria-label="Allergens"]')
    expect(allergensList.exists()).toBe(true)
    expect(allergensList.findAll('.allergen-tag').length).toBe(2)
  })

  it('renders language toggle button with aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const langBtn = wrapper.find('.lang-btn')
    expect(langBtn.exists()).toBe(true)
    expect(langBtn.attributes('aria-label')).toContain('Switch language')
  })

  it('renders table info with role status', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [],
        products: [],
        session_token: 'abc123',
        table_number: 'A-01'
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const tableInfo = wrapper.find('.table-info')
    expect(tableInfo.exists()).toBe(true)
    expect(tableInfo.attributes('role')).toBe('status')
    expect(tableInfo.text()).toContain('A-01')
  })

  it('renders products list with role list', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [{ id: 1, name: { en: 'Starters' }, sort_order: 1 }],
        products: [
          { id: 1, category_id: 1, name: { en: 'Item' }, price: 5.00, allergens: [] }
        ],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const list = wrapper.find('.products-list')
    expect(list.attributes('role')).toBe('list')

    const items = list.findAll('.product-card')
    items.forEach(item => {
      expect(item.attributes('role')).toBe('listitem')
    })
  })

  it('renders category sections with aria-label', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [
          { id: 1, name: { en: 'Starters', es: 'Entrantes' }, sort_order: 1 }
        ],
        products: [],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    const section = wrapper.find('section')
    expect(section.exists()).toBe(true)
    expect(section.attributes('aria-label')).toBe('Starters')
  })

  it('has semantic HTML structure', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test', slug: 'test' },
        categories: [{ id: 1, name: { en: 'Starters' }, sort_order: 1 }],
        products: [{ id: 1, category_id: 1, name: { en: 'Item' }, price: 5.00, allergens: [] }],
        session_token: null,
        table_number: null
      }
    })

    const wrapper = await createRouterAndMount('/client/menu')
    await flushPromises()
    await nextTick()

    expect(wrapper.find('header').exists()).toBe(true)
    expect(wrapper.find('main').exists()).toBe(true)
    expect(wrapper.find('footer').exists()).toBe(true)
  })
})

describe('clientMenu store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const store = useClientMenuStore()

    expect(store.restaurant).toBeNull()
    expect(store.categories).toEqual([])
    expect(store.products).toEqual([])
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
    expect(store.sessionToken).toBeNull()
    expect(store.tableNumber).toBeNull()
  })

  it('translatedName handles string names', () => {
    const store = useClientMenuStore()
    store.locale = 'en'

    expect(store.translatedName('Simple Name')).toBe('Simple Name')
  })

  it('translatedName handles object names with fallback', () => {
    const store = useClientMenuStore()
    store.locale = 'es'

    expect(store.translatedName({ en: 'English', es: 'Spanish' })).toBe('Spanish')
    expect(store.translatedName({ en: 'English' })).toBe('English')
    expect(store.translatedName({})).toBe('')
  })

  it('translatedName handles null', () => {
    const store = useClientMenuStore()

    expect(store.translatedName(null)).toBe('')
  })

  it('setLocale updates locale', () => {
    const store = useClientMenuStore()

    store.setLocale('fr')
    expect(store.locale).toBe('fr')
  })

  it('fetchMenu sets loading state', async () => {
    const store = useClientMenuStore()

    const fetchPromise = store.fetchMenu('test-token')

    expect(store.loading).toBe(true)

    await fetchPromise
    expect(store.loading).toBe(false)
  })

  it('fetchMenu populates store data', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        restaurant: { id: 1, name: 'Test Restaurant', slug: 'test' },
        categories: [{ id: 1, name: { en: 'Starters' }, sort_order: 1 }],
        products: [{ id: 1, category_id: 1, name: { en: 'Item' }, price: 5.00 }],
        session_token: 'abc',
        table_number: '1'
      }
    })

    const store = useClientMenuStore()

    await store.fetchMenu()

    expect(store.restaurant.slug).toBe('test')
    expect(store.categories.length).toBe(1)
    expect(store.products.length).toBe(1)
    expect(store.sessionToken).toBe('abc')
    expect(store.tableNumber).toBe('1')
  })

  it('fetchMenu sets error on failure', async () => {
    mockApi.get.mockRejectedValue(new Error('fail'))

    const store = useClientMenuStore()

    await store.fetchMenu()

    expect(store.loading).toBe(false)
    expect(store.error).toBeDefined()
  })
})
