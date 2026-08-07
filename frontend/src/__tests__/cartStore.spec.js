import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCartStore } from '@/stores/cartStore'

describe('CartStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('should start with empty cart', () => {
    const store = useCartStore()
    expect(store.items).toEqual([])
    expect(store.isEmpty).toBe(true)
    expect(store.total).toBe(0)
    expect(store.itemCount).toBe(0)
  })

  it('should add item to cart', () => {
    const store = useCartStore()
    const product = {
      id: 1,
      name: 'Test Product',
      price: 10.00,
      allergens: [],
    }

    store.addItem(product)

    expect(store.items).toHaveLength(1)
    expect(store.items[0].productId).toBe(1)
    expect(store.items[0].quantity).toBe(1)
    expect(store.isEmpty).toBe(false)
    expect(store.total).toBe(10.00)
  })

  it('should increase quantity when adding same product', () => {
    const store = useCartStore()
    const product = {
      id: 1,
      name: 'Test Product',
      price: 10.00,
    }

    store.addItem(product)
    store.addItem(product)

    expect(store.items).toHaveLength(1)
    expect(store.items[0].quantity).toBe(2)
    expect(store.total).toBe(20.00)
  })

  it('should remove item from cart', () => {
    const store = useCartStore()
    const product = {
      id: 1,
      name: 'Test Product',
      price: 10.00,
    }

    store.addItem(product)
    store.removeItem(0)

    expect(store.items).toHaveLength(0)
    expect(store.isEmpty).toBe(true)
  })

  it('should update quantity', () => {
    const store = useCartStore()
    const product = {
      id: 1,
      name: 'Test Product',
      price: 10.00,
    }

    store.addItem(product)
    store.updateQuantity(0, 1)

    expect(store.items[0].quantity).toBe(2)
  })

  it('should update notes', () => {
    const store = useCartStore()
    const product = {
      id: 1,
      name: 'Test Product',
      price: 10.00,
    }

    store.addItem(product, { notes: 'No onions' })
    store.updateNotes(0, 'Extra sauce')

    expect(store.items[0].notes).toBe('Extra sauce')
  })

  it('should clear cart', () => {
    const store = useCartStore()
    const product = {
      id: 1,
      name: 'Test Product',
      price: 10.00,
    }

    store.addItem(product)
    store.clear()

    expect(store.items).toHaveLength(0)
    expect(store.isEmpty).toBe(true)
  })

  it('should calculate total correctly', () => {
    const store = useCartStore()
    const product1 = { id: 1, name: 'Product 1', price: 10.00 }
    const product2 = { id: 2, name: 'Product 2', price: 15.00 }

    store.addItem(product1)
    store.addItem(product2)

    expect(store.total).toBe(25.00)
  })
})