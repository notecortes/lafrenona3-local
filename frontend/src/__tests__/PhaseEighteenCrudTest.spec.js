import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import { useAuthStore } from '@/stores/authStore'

vi.mock('@/services/api', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    put: vi.fn(),
    delete: vi.fn()
  }
}))

const mockApi = (await import('@/services/api')).default

describe('LoginView — Phase 18', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders login form with accessible labels', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/login', name: 'Login', component: LoginView }]
    })
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, { global: { plugins: [router] } })
    await nextTick()

    expect(wrapper.find('h1').text()).toContain('La Frenona 3')

    const emailInput = wrapper.find('#email')
    expect(emailInput.exists()).toBe(true)
    expect(emailInput.attributes('type')).toBe('email')
    expect(emailInput.attributes('autocomplete')).toBe('email')

    const passwordInput = wrapper.find('#password')
    expect(passwordInput.exists()).toBe(true)
    expect(passwordInput.attributes('type')).toBe('password')

    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.exists()).toBe(true)
    expect(submitButton.text()).toContain('Iniciar sesión')
  })

  it('has form validation attributes', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/login', name: 'Login', component: LoginView }]
    })
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, { global: { plugins: [router] } })
    await nextTick()

    const emailInput = wrapper.find('#email')
    expect(emailInput.attributes('required')).toBeDefined()

    const passwordInput = wrapper.find('#password')
    expect(passwordInput.attributes('required')).toBeDefined()
  })

  it('has proper form structure with labels and inputs', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/login', name: 'Login', component: LoginView }]
    })
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, { global: { plugins: [router] } })
    await nextTick()

    const labels = wrapper.findAll('label')
    expect(labels.length).toBeGreaterThanOrEqual(2)

    const form = wrapper.find('form')
    expect(form.exists()).toBe(true)
  })
})

describe('authStore — Phase 18', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const store = useAuthStore()
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(store.isSuperAdmin).toBe(false)
    expect(store.isOwner).toBe(false)
    expect(store.restaurantId).toBeNull()
  })

  it('login stores token and user', async () => {
    mockApi.post.mockResolvedValue({
      data: {
        access_token: 'test_token',
        user: { id: 1, name: 'Owner', email: 'owner@test.com', role: 'owner', restaurant_id: 1 }
      }
    })

    const store = useAuthStore()
    const result = await store.login('owner@test.com', 'password123')

    expect(result.success).toBe(true)
    expect(store.token).toBe('test_token')
    expect(store.user.role).toBe('owner')
    expect(store.isAuthenticated).toBe(true)
    expect(store.isOwner).toBe(true)
    expect(store.restaurantId).toBe(1)
  })

  it('login sets error on failure', async () => {
    mockApi.post.mockRejectedValue(new Error('fail'))

    const store = useAuthStore()
    const result = await store.login('test@test.com', 'password123')

    expect(result.success).toBe(false)
    expect(store.error).toBeDefined()
  })

  it('logout clears state', () => {
    const store = useAuthStore()
    store.token = 'test_token'
    store.user = { id: 1, role: 'owner' }
    store.logout()

    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(store.isOwner).toBe(false)
  })

  it('isSuperAdmin getter works', async () => {
    mockApi.post.mockResolvedValue({
      data: {
        access_token: 'test',
        user: { id: 1, name: 'Admin', email: 'admin@test.com', role: 'superadmin', restaurant_id: null }
      }
    })

    const store = useAuthStore()
    await store.login('admin@test.com', 'password123')

    expect(store.isSuperAdmin).toBe(true)
    expect(store.isOwner).toBe(false)
    expect(store.restaurantId).toBeNull()
  })

  it('isStaff getter works', async () => {
    mockApi.post.mockResolvedValue({
      data: {
        access_token: 'test',
        user: { id: 1, name: 'Waiter', email: 'waiter@test.com', role: 'waiter', restaurant_id: 1 }
      }
    })

    const store = useAuthStore()
    await store.login('waiter@test.com', 'password123')

    expect(store.isStaff).toBe(true)
    expect(store.isOwner).toBe(false)
    expect(store.isSuperAdmin).toBe(false)
  })
})
