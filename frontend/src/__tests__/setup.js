import { vi } from 'vitest'

globalThis.matchMedia = globalThis.matchMedia || (() => ({
  matches: false,
  addListener: vi.fn(),
  removeListener: vi.fn()
}))

const localStorageMock = (() => {
  let store = {}
  return {
    getItem: (key) => store[key] || null,
    setItem: (key, value) => { store[key] = value.toString() },
    removeItem: (key) => { delete store[key] },
    clear: () => { store = {} }
  }
})()

Object.defineProperty(globalThis, 'localStorage', {
  value: localStorageMock,
  writable: true
})
