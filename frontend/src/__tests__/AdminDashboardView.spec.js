import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AdminDashboardView from '@/views/superadmin/AdminDashboardView.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { data: [] } }),
    put: vi.fn(),
  },
}))

describe('AdminDashboardView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('should render the component', async () => {
    const wrapper = mount(AdminDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.superadmin-dashboard').exists()).toBe(true)
  })

  it('should render dashboard section', async () => {
    const wrapper = mount(AdminDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.superadmin-dashboard__stats').exists()).toBe(true)
  })

  it('should render navigation', async () => {
    const wrapper = mount(AdminDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.superadmin-dashboard__nav').exists()).toBe(true)
  })

  it('should have proper accessibility attributes', async () => {
    const wrapper = mount(AdminDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('main').exists()).toBe(true)
  })
})