import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import OwnerDashboardView from '@/views/owner/OwnerDashboardView.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { data: [] } }),
    post: vi.fn(),
    put: vi.fn(),
  },
}))

describe('OwnerDashboardView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('should render the component', async () => {
    const wrapper = mount(OwnerDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.owner-dashboard').exists()).toBe(true)
  })

  it('should render dashboard section', async () => {
    const wrapper = mount(OwnerDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.owner-dashboard__stats').exists()).toBe(true)
  })

  it('should render navigation', async () => {
    const wrapper = mount(OwnerDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.owner-dashboard__nav').exists()).toBe(true)
  })

  it('should have proper accessibility attributes', async () => {
    const wrapper = mount(OwnerDashboardView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('main').exists()).toBe(true)
  })
})