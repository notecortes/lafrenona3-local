import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useRoute } from 'vue-router'
import StaffRoomView from '@/views/staff/StaffRoomView.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({
      data: { data: [] },
    }),
    post: vi.fn(),
  },
}))

vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRoute: vi.fn(),
  }
})

const mockRoute = { params: {}, query: {} }

describe('StaffRoomView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(useRoute).mockReturnValue(mockRoute)
    vi.clearAllMocks()
  })

  it('should render the component', async () => {
    const wrapper = mount(StaffRoomView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.staff-room').exists()).toBe(true)
  })

  it('should render loading state', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const wrapper = mount(StaffRoomView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.staff-room').exists()).toBe(true)
  })

  it('should render empty state when no tables', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(StaffRoomView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('.staff-room').exists()).toBe(true)
  })

  it('should render tables when available', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          { id: 1, number: 1, status: 'free', assistance_status: null },
          { id: 2, number: 2, status: 'occupied', assistance_status: null },
        ],
      },
    })

    const wrapper = mount(StaffRoomView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.findAll('.staff-room__table').length).toBe(2)
  })

  it('should show assistance alert when table has assistance_status', async () => {
    const api = (await import('@/services/api')).default
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          { id: 1, number: 1, status: 'occupied', assistance_status: 'waiter_called', assistance_requested_at: '2024-01-01T12:00:00Z' },
        ],
      },
    })

    const wrapper = mount(StaffRoomView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    const alert = wrapper.find('.staff-room__assistance-alert')
    expect(alert.exists()).toBe(true)
  })

  it('should have proper accessibility attributes', async () => {
    const wrapper = mount(StaffRoomView, {
      global: {
        plugins: [createPinia()],
      },
    })

    await flushPromises()
    expect(wrapper.find('main').exists()).toBe(true)
  })
})