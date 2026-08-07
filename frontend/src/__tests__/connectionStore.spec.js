import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useConnectionStore } from '@/stores/connectionStore'

// Mock navigator.onLine
Object.defineProperty(window, 'navigator', {
  value: { onLine: true },
  writable: true,
})

describe('ConnectionStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    window.navigator.onLine = true
  })

  it('should start as connected', () => {
    const store = useConnectionStore()
    expect(store.online).toBe(true)
    expect(store.status).toBe('connected')
    expect(store.isOffline).toBe(false)
  })

  it('should set offline state', () => {
    const store = useConnectionStore()
    store.setOffline()

    expect(store.online).toBe(false)
    expect(store.status).toBe('disconnected')
    expect(store.isOffline).toBe(true)
  })

  it('should set online state', () => {
    const store = useConnectionStore()
    store.setOffline()
    store.setOnline()

    expect(store.online).toBe(true)
    expect(store.status).toBe('connected')
    expect(store.isOffline).toBe(false)
  })

  it('should track retry count', () => {
    const store = useConnectionStore()
    store.setReconnecting()
    store.setReconnecting()

    expect(store.retryCount).toBe(2)
  })

  it('should reset retry count on online', () => {
    const store = useConnectionStore()
    store.setReconnecting()
    store.setOnline()

    expect(store.retryCount).toBe(0)
  })

  it('should check connection', () => {
    const store = useConnectionStore()
    store.checkConnection()

    expect(store.lastCheck).toBeDefined()
  })
})