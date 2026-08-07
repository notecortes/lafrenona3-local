import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import KitchenMonitorView from '@/views/staff/KitchenMonitorView.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({
      data: { data: [] },
    }),
    put: vi.fn(),
  },
}))

describe('KitchenMonitorView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('should render the component', async () => {
    const wrapper = mount(KitchenMonitorView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.kitchen-monitor').exists()).toBe(true)
  })

  it('should render loading state', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const wrapper = mount(KitchenMonitorView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.kitchen-monitor').exists()).toBe(true)
  })

  it('should render empty state when no items', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(KitchenMonitorView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })

  it('should render items when available', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          { id: 1, status: 'pending', quantity: 2, created_at: '2024-01-01T12:00:00Z', product: { name: 'Pizza' }, order: { number: '123', table: { number: 1 } } },
        ],
      },
    })

    const wrapper = mount(KitchenMonitorView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.findAll('.kitchen-monitor__item').length).toBe(1)
  })

  it('should show stats bar', async () => {
    const wrapper = mount(KitchenMonitorView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.kitchen-monitor__stats').exists()).toBe(true)
  })

  it('should have proper accessibility attributes', async () => {
    const wrapper = mount(KitchenMonitorView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('main').exists()).toBe(true)
  })
})